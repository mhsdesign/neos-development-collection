<?php

namespace Neos\Fusion\Core\CachedParser;

use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\ResourceManagement\Streams\ResourceStreamWrapper;
use Neos\Fusion\Core\Parser\AstBuilder;
use Neos\Fusion\Core\Parser\FilePatternResolver;
use Neos\Fusion\Core\ParserInterface;
use Neos\Utility\Arrays;

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

    /**
     * TODO this is alternative api better for a fusion service...
     */
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
        $cacheIdentifier = self::getCacheIdentifierForPossibleUnresolvedResourcePath($contextPathAndFilename);

        if ($this->fusionFilesObjectTreeCache->has($cacheIdentifier)) {
            return $this->fusionFilesObjectTreeCache->get($cacheIdentifier);
        }

        $watchingAstBuilder = new WatchingAstBuilder();
        $watchingParser = new WatchingParser();
        try {
            $fusionAst = $watchingParser->parse($sourceCode, $contextPathAndFilename, $watchingAstBuilder, false);
        // TODO WIP custom unsupported copy operation exception.
        } catch (\BadMethodCallException $e) {
            throw new \Exception("Couldn't parse '$contextPathAndFilename'", 1643560139, $e);
        }

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

    public static function getCacheIdentifierForPossibleUnresolvedResourcePath(string $contextPathAndFilename): string
    {
        $realPath = self::getRealPath($contextPathAndFilename);
        $flowRoot = defined('FLOW_PATH_ROOT') ? FLOW_PATH_ROOT : '';
        $realFusionFilePathWithoutRoot = str_replace($flowRoot, '', $realPath);
        return md5($realFusionFilePathWithoutRoot);
    }

    protected static function getRealPath(string $requestedPath): string
    {
        if (strpos($requestedPath, '://') === false) {
            return realpath($requestedPath);
        }

        list($protocol) = explode('://', $requestedPath, 2);
        switch ($protocol) {
            case ResourceStreamWrapper::getScheme():
                $absolutePath = (new ResourceStreamWrapperHelper())->getAbsolutePath($requestedPath);
                return realpath($absolutePath);
            case 'vfs':
                if (strpos($requestedPath, './') !== false) {
                    throw new \Exception("relative path '$requestedPath' is not allowed for 'vfs'", 1643541301);
                }
                return $requestedPath;
            default:
                throw new \Exception("Scheme '$protocol' is not supported.", 1643541360);
        }
    }
}
