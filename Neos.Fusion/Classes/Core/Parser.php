<?php
declare(strict_types=1);

namespace Neos\Fusion\Core;

/*
 * This file is part of the Neos.Fusion package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Cache\Frontend\VariableFrontend;
use Neos\Fusion;
use Neos\Fusion\Core\ObjectTreeParser\Ast\FusionFileAst;
use Neos\Fusion\Core\ObjectTreeParser\FilePatternResolver;
use Neos\Fusion\Core\ObjectTreeParser\Lexer;
use Neos\Fusion\Core\ObjectTreeParser\ObjectTree;
use Neos\Fusion\Core\ObjectTreeParser\ObjectTreeAstVisitor;
use Neos\Fusion\Core\ObjectTreeParser\PredictiveParser;
use Neos\Flow\Annotations as Flow;

/**
 * The Fusion Parser
 *
 * @api
 */
class Parser implements ParserInterface
{
    /**
     * Reserved parse tree keys for internal usage.
     *
     * @deprecated use ParserInterface::RESERVED_PARSE_TREE_KEYS
     * @var array
     */
    public static $reservedParseTreeKeys = ParserInterface::RESERVED_PARSE_TREE_KEYS;

    /**
     * @var VariableFrontend
     */
    protected $fusionFilesObjectTreeCache;

    /**
     * @Flow\Inject
     * @var DslFactory
     */
    protected $dslFactory;

    /**
     * Parses the given Fusion source code and returns an object tree
     * as the result.
     *
     * @param string $sourceCode The Fusion source code to parse
     * @param string|null $contextPathAndFilename An optional path and filename to use as a prefix for inclusion of further Fusion files
     * @param array $objectTreeUntilNow Used internally for keeping track of the built object tree
     * @return array A Fusion object tree, generated from the source code
     * @throws Fusion\Exception
     * @api
     */
    public function parse(string $sourceCode, ?string $contextPathAndFilename = null, array $objectTreeUntilNow = []): array
    {
        $fusionFileAst = $this->getFusionFileAst($sourceCode, $contextPathAndFilename);

        $objectTree = new ObjectTree();
        $objectTree->setObjectTree($objectTreeUntilNow);

        $objectTree = $this->getObjectTreeAstVisitor($objectTree)->visitFusionFileAst($fusionFileAst);

        $objectTree->buildPrototypeHierarchy();
        return $objectTree->getObjectTree();
    }

    protected function getObjectTreeAstVisitor(ObjectTree $objectTree): ObjectTreeAstVisitor
    {
        return new ObjectTreeAstVisitor($objectTree, [$this, 'handleFileInclude'], [$this, 'handleDslTranspile']);
    }

    protected function getFusionFileAst(string $sourceCode, ?string $contextPathAndFilename): FusionFileAst
    {
        if ($useCache = ($contextPathAndFilename !== null)) {
            $cacheIdentifier = md5($contextPathAndFilename);
            if ($this->fusionFilesObjectTreeCache->has($cacheIdentifier)) {
                return $this->fusionFilesObjectTreeCache->get($cacheIdentifier);
            }
        }
        $lexer = new Lexer($sourceCode);
        $fusionFileAst = (new PredictiveParser($lexer, $contextPathAndFilename))->parse();
        if ($useCache) {
            $this->fusionFilesObjectTreeCache->set($cacheIdentifier, $fusionFileAst);
        }
        return $fusionFileAst;
    }

    /**
     * @internal Exposed to be testable and easily transformable to closure.
     */
    public function handleFileInclude(ObjectTree $objectTree, string $filePattern, ?string $contextPathAndFilename): void
    {
        $filesToInclude = FilePatternResolver::resolveFilesByPattern($filePattern, $contextPathAndFilename, '.fusion');
        foreach ($filesToInclude as $file) {
            if (is_readable($file) === false) {
                throw new Fusion\Exception("Could not read file '$file' of pattern '$filePattern'.", 1347977017);
            }
            // Check if not trying to recursively include the current file via globbing
            if ($contextPathAndFilename === null
                || stat($contextPathAndFilename) !== stat($file)) {

                $fusionFileAst = $this->getFusionFileAst(file_get_contents($file), $file);
                $this->getObjectTreeAstVisitor($objectTree)->visitFusionFileAst($fusionFileAst);
            }
        }
    }

    /**
     * @internal Exposed to be testable and easily transformable to closure.
     */
    public function handleDslTranspile(string $identifier, string $code)
    {
//        // TODO invalidate cache, when dsls are changed. Also remove cache entries - maybe tag them with fusion main file name...
        $cacheIdentifier = md5($identifier . $code);
        if ($this->fusionFilesObjectTreeCache->has($cacheIdentifier)) {
            return $this->fusionFilesObjectTreeCache->get($cacheIdentifier);
        }

        $dslObject = $this->dslFactory->create($identifier);

        try {
            $transpiledFusion = $dslObject->transpile($code);
        } catch (\Exception $e) {
            // TODO
            // convert all exceptions from dsl transpilation to fusion exception and add file and line info
            throw $e;
        }

//        $cacheIdentifier = md5($identifier . $code);
//        if ($this->fusionFilesObjectTreeCache->has($cacheIdentifier)) {
//            $fusionFileAst = $this->fusionFilesObjectTreeCache->get($cacheIdentifier);
//        } else {
            $lexer = new Lexer('value = ' . $transpiledFusion);
            $parser = new PredictiveParser($lexer);
            $fusionFileAst = $parser->parse();
//            $this->fusionFilesObjectTreeCache->set($cacheIdentifier, $fusionFileAst);
//        }

        $objectTree = $this->getObjectTreeAstVisitor(new ObjectTree())->visitFusionFileAst($fusionFileAst);

        $temporaryAst = $objectTree->getObjectTree();

        $dslValue = $temporaryAst['value'];
        $this->fusionFilesObjectTreeCache->set($cacheIdentifier, $dslValue);
        return $dslValue;
    }
}
