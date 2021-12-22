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
    protected $isInClosedParseMode = false;

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
        foreach (array_keys($changedFiles) as $changedFile) {

            $flowRoot = defined('FLOW_PATH_ROOT') ? FLOW_PATH_ROOT : '';
            $fusionPathWithoutRoot = str_replace($flowRoot, '', $changedFile);

            $cacheIdentifier = md5($fusionPathWithoutRoot);

            if ($fusionFileCache->has($cacheIdentifier)) {
//                echo "cache flushed for $fusionPathWithoutRoot";
                $fusionFileCache->remove($cacheIdentifier);
            }
        }
    }

    public function merge($tree)
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

    public function parse(string $sourceCode, string $contextPathAndFilename = null, $objectTreeUntilNow = null, bool $buildPrototypeHierarchy = true): array
    {
        if ($contextPathAndFilename === null) {
            throw new \Exception("not supported");
        }

        $cacheIdentifier = $this->getCacheIdentifier($contextPathAndFilename);

        if ($this->fusionFileCache->has($cacheIdentifier)) {
            list($includeFiles, $fusionAst, $valueUnsets) = $this->fusionFileCache->get($cacheIdentifier);
        } else {
            $watchingAstBuilder = new class extends AstBuilder {
                public $valueUnsets = [];
                public function removeValueInObjectTree(array $targetObjectPath): void
                {
                    $this->valueUnsets[] = $targetObjectPath;
                    parent::removeValueInObjectTree($targetObjectPath);
                }
            };

            $this->isInClosedParseMode = true;
            $fusionAst = parent::parse($sourceCode, $contextPathAndFilename, $watchingAstBuilder, false);
            $this->isInClosedParseMode = false;

            $valueUnsets = $watchingAstBuilder->valueUnsets;
            $includeFiles = $this->includeFiles;

//            $this->fusionFileCache->set($cacheIdentifier, [
//                $includeFiles, $fusionAst, $valueUnsets
//            ]);
        }

        if (empty($includeFiles) === false) {
            $includeAst = $this->parseIncludeFileList($includeFiles, $contextPathAndFilename, null, false);
            $this->includeFiles = [];
            $this->merge($includeAst);
        }

        if (empty($valueUnsets) === false) {
            $builder = new AstBuilder();
            $builder->setObjectTree(static::$globalFusionObjectTree);
            foreach ($valueUnsets as $valueUnset) {
                $builder->setValueInObjectTree($valueUnset, null);
            }
            static::$globalFusionObjectTree = $builder->getObjectTree();
        }

        if (empty($fusionAst) === false) {
            $this->merge($fusionAst);
        }

        if ($objectTreeUntilNow instanceof AstBuilder) {
            $objectTreeUntilNow->setObjectTree(static::$globalFusionObjectTree);
        }

        return static::$globalFusionObjectTree;
    }

    public function parseIncludeFileList(array $filePatterns, $contextPathAndFilename = null, $objectTreeUntilNow = null, bool $buildPrototypeHierarchy = true): array
    {
//        parseIncludeFileList is called from outside:
//        do nothing as this is fine.
        if ($this->isInClosedParseMode) {
            $this->includeFiles = $filePatterns;
            return [];
        }
        return parent::parseIncludeFileList($filePatterns, $contextPathAndFilename, null, true);
    }

    protected function absolutePathOfResource(string $requestedPath): string
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

        if (!$package instanceof FlowPackageInterface) {
            return false;
        }

        return Files::concatenatePaths([$package->getResourcesPath(), $path]);
    }

    protected function getCacheIdentifier(string $contextPathAndFilename): string
    {
        $absolutePath = $contextPathAndFilename;
        if (strpos($contextPathAndFilename, '://') !== false) {
            $absolutePath = $this->absolutePathOfResource($contextPathAndFilename);
        }
        $realPath = realpath($absolutePath);

        $flowRoot = defined('FLOW_PATH_ROOT') ? FLOW_PATH_ROOT : '';
        $fusionPathWithoutRoot = str_replace($flowRoot, '', $realPath);

        return md5($fusionPathWithoutRoot);
    }
}
