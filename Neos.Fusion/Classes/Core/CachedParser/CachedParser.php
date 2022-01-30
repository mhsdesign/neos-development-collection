<?php

namespace Neos\Fusion\Core\CachedParser;

use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Package\FlowPackageInterface;
use Neos\Flow\Package\PackageManager;
use Neos\Flow\ResourceManagement\Streams\ResourceStreamWrapper;
use Neos\Fusion\Core\AstBuilder;
use Neos\Fusion\Core\FilePatternResolver;
use Neos\Fusion\Core\ParserInterface;
use Neos\Utility\Arrays;
use Neos\Utility\Files;
use Neos\Flow\Annotations as Flow;

class CachedParser implements ParserInterface
{
    /**
     * @var VariableFrontend
     */
    protected $fusionFilesObjectTreeCache;

    /**
     * @var ?array
     */
    protected $globalFusionObjectTree;

    /**
     * @Flow\Inject
     * @var PackageManager
     */
    public $packageManager;

    /**
     * Connected in bootstrap.
     *
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

    /**
     * TODO we allow includes everywhere, but they are treated as if they are at the top - this could result in a different result.
     *
     * @param string $sourceCode
     * @param string|null $contextPathAndFilename
     * @param null $objectTreeUntilNow
     * @param bool $buildPrototypeHierarchy
     * @return array
     * @throws \Neos\Fusion\Exception
     */
    public function parse(string $sourceCode, string $contextPathAndFilename = null, $objectTreeUntilNow = null, bool $buildPrototypeHierarchy = true): array
    {
        // we need an anchor point for caching.
        if (empty($contextPathAndFilename)) {
            throw new \InvalidArgumentException('$contextPathAndFilename must be set when using the Cached parser.');
        }

        $this->parseInternal($sourceCode, $contextPathAndFilename);

        if ($buildPrototypeHierarchy) {
            $builder = new AstBuilder();
            $builder->setObjectTree($this->globalFusionObjectTree);
            $builder->buildPrototypeHierarchy();
            $this->globalFusionObjectTree = $builder->getObjectTree();
        }

        // comply to the api, if we got an AstBuilder we need to modify it.
        if ($objectTreeUntilNow instanceof AstBuilder) {
            $objectTreeUntilNow->setObjectTree($this->globalFusionObjectTree);
        }

        $output = $this->globalFusionObjectTree;
        // reset out global tree
        $this->globalFusionObjectTree = null;
        return $output;
    }

    public function parseIncludeFileList(array $fusionAbsoluteIncludes, bool $buildPrototypeHierarchy = true): array
    {
        $this->parseAndMergeIncludeFilesInternal($fusionAbsoluteIncludes);
        if ($buildPrototypeHierarchy) {
            $builder = new AstBuilder();
            $builder->setObjectTree($this->globalFusionObjectTree);
            $builder->buildPrototypeHierarchy();
            $this->globalFusionObjectTree = $builder->getObjectTree();
        }
        $output = $this->globalFusionObjectTree;
        // reset our global tree
        $this->globalFusionObjectTree = null;
        return $output;
    }

    protected function parseInternal(string $sourceCode, string $contextPathAndFilename)
    {
        list(
            $includeFiles,
            $fusionAst,
            $valueUnsets
            ) = $this->getFromCacheOrParse($sourceCode, $contextPathAndFilename);

        // parse the value includes or retrieve ast from cache recursively.
        if (empty($includeFiles) === false) {
            $this->parseAndMergeIncludeFilesInternal($includeFiles, $contextPathAndFilename);
        }

        // we perform a value unset on the included ast and on the ast parsed beforehand.
        if (empty($valueUnsets) === false && isset($this->globalFusionObjectTree)) {
            foreach ($valueUnsets as $valueUnset) {
                $this->globalFusionObjectTree = Arrays::unsetValueByPath($this->globalFusionObjectTree, $valueUnset);
            }
        }

        // after the includes, and the value unsets.
        if (empty($fusionAst) === false) {
            $this->mergeIntoGlobalTree($fusionAst);
        }
    }

    protected function mergeIntoGlobalTree($tree): void
    {
        if (isset($this->globalFusionObjectTree) === false) {
            $this->globalFusionObjectTree = $tree;
            return;
        }
        $this->globalFusionObjectTree = Arrays::arrayMergeRecursiveOverruleWithCallback($this->globalFusionObjectTree, $tree, function ($simple) {
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

        if ($this->fusionFilesObjectTreeCache->has($cacheIdentifier)) {
            return $this->fusionFilesObjectTreeCache->get($cacheIdentifier);
        }

        $watchingAstBuilder = new WatchingAstBuilder();
        $watchingParser = new WatchingParser();
        $fusionAst = $watchingParser->parse($sourceCode, $contextPathAndFilename, $watchingAstBuilder, false);

        $valueUnsets = $watchingAstBuilder->watchedValueUnsets;
        $includeFiles = $watchingParser->watchedFileIncludes;

        $fusionFileCache = [
            $includeFiles,
            $fusionAst,
            $valueUnsets
        ];

        $this->fusionFilesObjectTreeCache->set($cacheIdentifier, $fusionFileCache);

        return $fusionFileCache;
    }

    protected function parseAndMergeIncludeFilesInternal(array $filePatterns, ?string $contextPathAndFilename = null)
    {
        foreach ($filePatterns as $filePattern) {
            $filesToInclude = FilePatternResolver::resolveFilesByPattern($filePattern, $contextPathAndFilename, '.fusion');
            foreach ($filesToInclude as $file) {
                if (is_readable($file) === false) {
                    throw new \Exception("Could not read file '$file' of pattern '$filePattern'.", 1347977021);
                }
                // Check if not trying to recursively include the current file via globbing
                if ($contextPathAndFilename === null
                    || stat($contextPathAndFilename) !== stat($file)) {
                    // this call will merge the contents...
                    $this->parseInternal(file_get_contents($file), $file);
                }
            }
        }
    }

    protected function getCacheIdentifierForPossibleResourcePath(string $contextPathAndFilename): string
    {
        $absolutePath = $contextPathAndFilename;
        if (strpos($contextPathAndFilename, '://') !== false) {
            $absolutePath = $this->getAbsolutePathOfResource($contextPathAndFilename);
        }
        if (strpos($contextPathAndFilename, 'vfs://') === 0) {
            // TODO testing ...
            $realPath = $absolutePath;
        } else {
            $realPath = realpath($absolutePath);
        }
        return self::getCacheIdentifierForRealPath($realPath);
    }

    protected static function getCacheIdentifierForRealPath(string $realFusionFilePath): string
    {
        $flowRoot = defined('FLOW_PATH_ROOT') ? FLOW_PATH_ROOT : '';
        $realFusionFilePathWithoutRoot = str_replace($flowRoot, '', $realFusionFilePath);
        return md5($realFusionFilePathWithoutRoot);
    }

    /**
     * Since the ResourceStreamWrapper has no option for this we build it our self
     *
     */
    protected function getAbsolutePathOfResource(string $requestedPath): string
    {
        $requestPathParts = explode('://', $requestedPath, 2);
        if ($requestPathParts[0] !== ResourceStreamWrapper::getScheme()) {

            // TODO testing... realpath ...
            if ($requestPathParts[0] === 'vfs') {
                return $requestedPath;
            }

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
}
