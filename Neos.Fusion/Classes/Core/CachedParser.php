<?php

namespace Neos\Fusion\Core;

use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Package\FlowPackageInterface;
use Neos\Flow\Package\PackageManager;
use Neos\Flow\ResourceManagement\Streams\ResourceStreamWrapper;
use Neos\Fusion\Core\AstBuilder;
use Neos\Utility\Arrays;
use Neos\Utility\Files;
use Neos\Flow\Annotations as Flow;

class CachedParser extends Parser
{
    /**
     * @var VariableFrontend
     */
    protected $fusionFileCache;

    /**
     * @var array
     */
    protected static $globalFusionObjectTree;

    /**
     * @Flow\Inject
     * @var PackageManager
     */
    protected $packageManager;

    /**
     * @var bool
     */
    protected $isInNestedIsolatedParseMode = false;

    /**
     * @var array
     */
    protected $includeFiles = [];

    /**
     * @param array $changedFiles
     * @return void
     */
    public static function flushFusionFileCacheOnFileChanges(VariableFrontend $fusionFileCache, array $changedFiles)
    {
        foreach ($changedFiles as $changedFile => $status) {

            $cacheIdentifier = self::getCacheIdentifierForRealPath($changedFile);

            if ($fusionFileCache->has($cacheIdentifier)) {
                $fusionFileCache->remove($cacheIdentifier);
            }
        }
    }

    protected static function getCacheIdentifierForRealPath(string $realFusionFilePath): string
    {
        $flowRoot = defined('FLOW_PATH_ROOT') ? FLOW_PATH_ROOT : '';
        $realFusionFilePathWithoutRoot = str_replace($flowRoot, '', $realFusionFilePath);
        return md5($realFusionFilePathWithoutRoot);
    }

    protected function merge($tree)
    {
        if (isset(static::$globalFusionObjectTree) === false) {
            static::$globalFusionObjectTree = $tree;
            return;
        }
        static::$globalFusionObjectTree = Arrays::arrayMergeRecursiveOverruleWithCallback(static::$globalFusionObjectTree, $tree, function ($simple) {
            return [
                '__value' => $simple,
                '__eelExpression' => null,
                '__objectType' => null
            ];
        });
    }

    protected function getFromCacheOrParse(string $sourceCode, ?string $contextPathAndFilename): array
    {
        $cacheIdentifier = $this->getCacheIdentifierForPossibleResourcePath($contextPathAndFilename);

        if ($this->fusionFileCache->has($cacheIdentifier)) {
            return $this->fusionFileCache->get($cacheIdentifier);
        }

        $watchingAstBuilder = new class extends AstBuilder {
            public $valueUnsets = [];
            public function removeValueInObjectTree(array $targetObjectPath): void
            {
                $this->valueUnsets[] = $targetObjectPath;
                parent::removeValueInObjectTree($targetObjectPath);
            }
        };

        // TODO: not well solved with parseIncludeFileList, as this is an implementation detail. The parser could have used new self()
        $this->isInNestedIsolatedParseMode = true;
        $fusionAst = parent::parse($sourceCode, $contextPathAndFilename, $watchingAstBuilder, false);
        $this->isInNestedIsolatedParseMode = false;

        $valueUnsets = $watchingAstBuilder->valueUnsets;
        $includeFiles = $this->includeFiles;

        $fusionFileCache = [
            $includeFiles,
            $fusionAst,
            $valueUnsets
        ];

//        $this->fusionFileCache->set($cacheIdentifier, $fusionFileCache);

        return $fusionFileCache;
    }

    public function parse(string $sourceCode, string $contextPathAndFilename = null, $objectTreeUntilNow = null, bool $buildPrototypeHierarchy = true): array
    {
        if ($contextPathAndFilename === null) {
            throw new \InvalidArgumentException('$contextPathAndFilename must be set when using the Cached parser.');
        }

        list(
            $includeFiles,
            $fusionAst,
            $valueUnsets
            ) = $this->getFromCacheOrParse($sourceCode, $contextPathAndFilename);


        if (empty($includeFiles) === false) {
            $includeAst = $this->parseIncludeFileList($includeFiles, $contextPathAndFilename, null, false);
            $this->includeFiles = [];
            $this->merge($includeAst);
        }

        if (empty($valueUnsets) === false) {
            foreach ($valueUnsets as $valueUnset) {
                static::$globalFusionObjectTree = Arrays::unsetValueByPath(static::$globalFusionObjectTree, $valueUnset);
            }
        }

        if (empty($fusionAst) === false) {
            $this->merge($fusionAst);
        }

        if ($buildPrototypeHierarchy) {
            $builder = new AstBuilder();
            $builder->setObjectTree(static::$globalFusionObjectTree);
            $builder->buildPrototypeHierarchy();
            static::$globalFusionObjectTree = $builder->getObjectTree();
        }

        if ($objectTreeUntilNow instanceof AstBuilder) {
            $objectTreeUntilNow->setObjectTree(static::$globalFusionObjectTree);
        }


//        if (isset(static::$globalFusionObjectTree['root'])) {
//            echo $contextPathAndFilename . ' ';
//            var_export(static::$globalFusionObjectTree['root']);
//            echo "\n";
//        }

        return static::$globalFusionObjectTree;
    }

    public function parseIncludeFileList(array $filePatterns, $contextPathAndFilename = null, $objectTreeUntilNow = null, bool $buildPrototypeHierarchy = true): array
    {
        if ($this->isInNestedIsolatedParseMode) {
            $this->includeFiles = $filePatterns;
            return [];
        }
        // parseIncludeFileList is called from outside.
        return parent::parseIncludeFileList($filePatterns, $contextPathAndFilename, null, true);
    }

    protected function getAbsolutePathOfResource(string $requestedPath): string
    {
        $requestPathParts = explode('://', $requestedPath, 2);
        if ($requestPathParts[0] !== ResourceStreamWrapper::getScheme()) {
            throw new \InvalidArgumentException('The ' . __CLASS__ . ' only supports the \'' . ResourceStreamWrapper::getScheme() . '\' scheme.', 123);
        }

        if (isset($requestPathParts[1]) === false) {
            throw new \InvalidArgumentException('Incomplete $requestedPath', 123);
        }

        $resourceUriWithoutScheme = $requestPathParts[1];

        if (strpos($resourceUriWithoutScheme, '/') === false && preg_match('/^[0-9a-f]{40}$/i', $resourceUriWithoutScheme) === 1) {
            throw new \InvalidArgumentException('Can process resource', 123);
        }

        list($packageName, $path) = explode('/', $resourceUriWithoutScheme, 2);

        try {
            $package = $this->packageManager->getPackage($packageName);
        } catch (\Neos\Flow\Package\Exception\UnknownPackageException $packageException) {
            throw new \Exception(sprintf('Invalid resource URI "%s": Package "%s" is not available.', $requestedPath, $packageName), 123, $packageException);
        }

        if ($package instanceof FlowPackageInterface === false) {
            return false;
        }

        return Files::concatenatePaths([$package->getResourcesPath(), $path]);
    }

    protected function getCacheIdentifierForPossibleResourcePath(string $contextPathAndFilename): string
    {
        $absolutePath = $contextPathAndFilename;
        if (strpos($contextPathAndFilename, '://') !== false) {
            $absolutePath = $this->getAbsolutePathOfResource($contextPathAndFilename);
        }
        $realPath = realpath($absolutePath);
        return self::getCacheIdentifierForRealPath($realPath);
    }
}
