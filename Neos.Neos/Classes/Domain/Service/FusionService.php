<?php
namespace Neos\Neos\Domain\Service;

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\Projection\Content\TraversableNodeInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ControllerContext;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Fusion\Core\CachedParser\CachedParser;
use Neos\Fusion\Core\ParserOld;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Utility\Files;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\Fusion\Core\Parser;
use Neos\Fusion\Core\Runtime;
use PHPUnit\Framework\TestCase;

/**
 * The Fusion Service
 *
 * @Flow\Scope("prototype")
 * @api
 */
class FusionService
{
    /**
     * @Flow\Inject
     * @var Parser
     */
    protected $fusionParser;

    /**
     * @Flow\Inject
     * @var SiteRepository
     */
    protected $siteRepository;

    /**
     * @Flow\Inject
     * @var ObjectManagerInterface
     */
    protected $objectManager = null;

    /**
     * Pattern used for determining the Fusion root file for a site
     *
     * @var string
     */
    protected $siteRootFusionPattern = 'resource://%s/Private/Fusion/Root.fusion';

    /**
     * Pattern used for determining the Fusion root file for autoIncludes
     *
     * @var string
     */
    protected $autoIncludeFusionPattern = 'resource://%s/Private/Fusion/Root.fusion';

    /**
     * Array of Fusion files to include before the site Fusion
     *
     * Example:
     *
     *     array(
     *         'resources://MyVendor.MyPackageKey/Private/Fusion/Root.fusion',
     *         'resources://SomeVendor.OtherPackage/Private/Fusion/Root.fusion'
     *     )
     *
     * @var array
     */
    protected $prependFusionIncludes = [];

    /**
     * Array of Fusion files to include after the site Fusion
     *
     * Example:
     *
     *     array(
     *         'resources://MyVendor.MyPackageKey/Private/Fusion/Root.fusion',
     *         'resources://SomeVendor.OtherPackage/Private/Fusion/Root.fusion'
     *     )
     *
     * @var array
     */
    protected $appendFusionIncludes = [];

    /**
     * @Flow\InjectConfiguration("fusion.autoInclude")
     * @var array
     */
    protected $autoIncludeConfiguration = [];

    /**
     * @Flow\InjectConfiguration("fusion.singleFileCache")
     * @var bool
     */
    protected $singleFileCacheConfiguration = true;

    /**
     * @Flow\Inject
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * @Flow\Inject
     * @var \Neos\Flow\Package\PackageManager
     */
    protected $packageManager;

    /**
     * Create a runtime for the given site node
     *
     * @param TraversableNodeInterface $currentSiteNode
     * @param ControllerContext $controllerContext
     * @return Runtime
     * @throws \Neos\Fusion\Exception
     * @throws \Neos\Neos\Domain\Exception
     */
    public function createRuntime(TraversableNodeInterface $currentSiteNode, ControllerContext $controllerContext)
    {
        $fusionObjectTree = $this->getMergedFusionObjectTree($currentSiteNode);
        $fusionRuntime = new Runtime($fusionObjectTree, $controllerContext);
        return $fusionRuntime;
    }

    /**
     * Returns a merged Fusion object tree in the context of the given nodes
     *
     * @param TraversableNodeInterface $startNode Node marking the starting point (i.e. the "Site" node)
     * @return array The merged object tree as of the given node
     * @throws \Neos\Neos\Domain\Exception
     * @throws \Neos\Fusion\Exception
     */
    public function getMergedFusionObjectTree(TraversableNodeInterface $startNode)
    {
        $siteResourcesPackageKey = $this->getSiteForSiteNode($startNode)->getSiteResourcesPackageKey();

        $siteRootFusionPathAndFilename = sprintf($this->siteRootFusionPattern, $siteResourcesPackageKey);
        $siteRootFusionCode = $this->readExternalFusionFile($siteRootFusionPathAndFilename);

        $mergedFusionCode = '';
        $mergedFusionCode .= $this->generateNodeTypeDefinitions();
        $mergedFusionCode .= $this->getFusionIncludes($this->prepareAutoIncludeFusion());
        $mergedFusionCode .= $this->getFusionIncludes($this->prependFusionIncludes);
        $mergedFusionCode .= $siteRootFusionCode;
        $mergedFusionCode .= $this->getFusionIncludes($this->appendFusionIncludes);

        // TODO
        // CachedParser:    100x  2.4       10x 590              1x 380
        // ParserOld:       100x  4.5       10x 830              1x 400
        // Parser:          100x  7.2       10x 1.11             1x 444
//        for ($x = 0; $x <= 1; $x++) {

        $a = (new CachedParser())->parse($mergedFusionCode, $siteRootFusionPathAndFilename);
//        $b = (new ParserOld())->parse($mergedFusionCode, $siteRootFusionPathAndFilename);
//              $c = (new Parser())->parse($mergedFusionCode, $siteRootFusionPathAndFilename);
//        TestCase::assertSame($a, $b);

//        }
        return $a;

        // TODO now includes which dont belong to the file Demo/Root.fusion will be cached. (solution parse file list api)
        // TODO use parseIncludeFileList so that appendFusionIncludes are really appended
//        $siteRootFusionPathAndFilename = sprintf($this->siteRootFusionPattern, $siteResourcesPackageKey);
//        $siteRootFusionFileInclude = is_file($siteRootFusionPathAndFilename) ? [$siteRootFusionPathAndFilename] : [];
//
//        $fusionIncludes = array_merge(
//            $this->prepareAutoIncludeFusion(),
//            $this->prependFusionIncludes,
//            $siteRootFusionFileInclude,
//            $this->appendFusionIncludes
//        );
//        $result = (new CachedParser())->parseIncludeFileList($fusionIncludes);
    }

    /**
     * @param TraversableNodeInterface $siteNode
     * @return Site
     */
    protected function getSiteForSiteNode(TraversableNodeInterface $siteNode)
    {
        return $this->siteRepository->findOneByNodeName((string)$siteNode->getNodeName());
    }

    /**
     * Reads the Fusion file from the given path and filename.
     * If it doesn't exist, this function will just return an empty string.
     *
     * @param string $pathAndFilename Path and filename of the Fusion file
     * @return string The content of the .fusion file, plus one chr(10) at the end
     */
    protected function readExternalFusionFile($pathAndFilename)
    {
        return (is_file($pathAndFilename)) ? Files::getFileContents($pathAndFilename) . chr(10) : '';
    }

    /**
     * Generate Fusion prototype definitions for all node types
     *
     * Only fully qualified node types (e.g. MyVendor.MyPackage:NodeType) will be considered.
     *
     * @return string
     * @throws \Neos\Neos\Domain\Exception
     */
    protected function generateNodeTypeDefinitions()
    {
        $code = '';
        /** @var NodeType $nodeType */
        foreach ($this->nodeTypeManager->getNodeTypes(false) as $nodeType) {
            $code .= $this->generateFusionForNodeType($nodeType);
        }
        return $code;
    }

    /**
     * Generate a Fusion prototype definition for a given node type
     *
     * A prototype will be rendererd with the generator-class defined in the
     * nodeType-configuration 'fusion.prototypeGenerator'
     *
     * @param NodeType $nodeType
     * @return string
     * @throws \Neos\Neos\Domain\Exception
     */
    protected function generateFusionForNodeType(NodeType $nodeType)
    {
        if ($nodeType->hasConfiguration('options.fusion.prototypeGenerator') && $nodeType->getConfiguration('options.fusion.prototypeGenerator') !== null) {
            $generatorClassName = $nodeType->getConfiguration('options.fusion.prototypeGenerator');
            if (!class_exists($generatorClassName)) {
                throw new \Neos\Neos\Domain\Exception('Fusion prototype-generator Class ' . $generatorClassName . ' does not exist');
            }
            $generator = $this->objectManager->get($generatorClassName);
            if (!$generator instanceof DefaultPrototypeGeneratorInterface) {
                throw new \Neos\Neos\Domain\Exception('Fusion prototype-generator Class ' . $generatorClassName . ' does not implement interface ' . DefaultPrototypeGeneratorInterface::class);
            }
            return $generator->generate($nodeType);
        }
        return '';
    }

    /**
     * Concatenate the given Fusion resources with include statements
     *
     * @param array $fusionResources An array of Fusion resource URIs
     * @return string A string of include statements for all resources
     */
    protected function getFusionIncludes(array $fusionResources)
    {
        $code = chr(10);
        foreach ($fusionResources as $fusionResource) {
            $code .= 'include: ' . (string)$fusionResource . chr(10);
        }
        $code .= chr(10);
        return $code;
    }

    /**
     * Prepares an array with Fusion paths to auto include before the Site Fusion.
     *
     * @return array
     */
    protected function prepareAutoIncludeFusion()
    {
        $autoIncludeFusion = [];
        foreach (array_keys($this->packageManager->getAvailablePackages()) as $packageKey) {
            if (isset($this->autoIncludeConfiguration[$packageKey]) && $this->autoIncludeConfiguration[$packageKey] === true) {
                $autoIncludeFusionFile = sprintf($this->autoIncludeFusionPattern, $packageKey);
                if (is_file($autoIncludeFusionFile)) {
                    $autoIncludeFusion[] = $autoIncludeFusionFile;
                }
            }
        }

        return $autoIncludeFusion;
    }

    /**
     * Set the pattern for including the site root Fusion
     *
     * @param string $siteRootFusionPattern A string for the sprintf format that takes the site package key as a single placeholder
     * @return void
     */
    public function setSiteRootFusionPattern($siteRootFusionPattern)
    {
        $this->siteRootFusionPattern = $siteRootFusionPattern;
    }

    /**
     * Get the Fusion resources that are included before the site Fusion.
     *
     * @return array
     */
    public function getPrependFusionIncludes()
    {
        return $this->prependFusionIncludes;
    }

    /**
     * Set Fusion resources that should be prepended before the site Fusion,
     * it defaults to the Neos Root.fusion Fusion.
     *
     * @param array $prependFusionIncludes
     * @return void
     */
    public function setPrependFusionIncludes(array $prependFusionIncludes)
    {
        $this->prependFusionIncludes = $prependFusionIncludes;
    }

    /**
     * Get Fusion resources that will be appended after the site Fusion.
     *
     * @return array
     */
    public function getAppendFusionIncludes()
    {
        return $this->appendFusionIncludes;
    }

    /**
     * Set Fusion resources that should be appended after the site Fusion,
     * this defaults to an empty array.
     *
     * @param array $appendFusionIncludes An array of Fusion resource URIs
     * @return void
     */
    public function setAppendFusionIncludes(array $appendFusionIncludes)
    {
        $this->appendFusionIncludes = $appendFusionIncludes;
    }
}
