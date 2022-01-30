<?php
namespace Neos\Fusion\Tests\Unit\Core\Parser;

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
use Neos\Flow\Tests\FunctionalTestCase;
use Neos\Flow\Tests\UnitTestCase;
use Neos\Fusion\Core\CachedParser\CachedParser;
use Neos\Fusion\Core\Parser;
use Neos\Fusion\Core\ParserOld;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;


class CachedParserTest extends TestCase
{

    public function fusionFiles()
    {
        yield [[
            'root.fusion' => 'bar = true',
            'file.fusion' => 'baz = true',
        ]];

        yield [[
            'root.fusion' => 'foo = 123',
            'file.fusion' => 'foo >',
        ]];

        yield [[
            'root.fusion' => 'but = 123',
            'file.fusion' => 'but.foo = "baz"',
        ]];

        yield [[
            'root.fusion' => 'root = afx`<div/><div><div/></div>`',
            'file.fusion' => 'root >
            root = "hi"',
        ]];

        yield [[
            'root.fusion' => 'root = Neos.Fusion:Tag',
            'file.fusion' => 'root >
            root = Neos.Fusion:Value',
        ]];
    }

    /**
     * @dataProvider fusionFiles
     * @throws \Neos\Fusion\Exception
     */
    public function testFusionMatches($directory)
    {
        $directory += [
            'entry.fusion' => ''
        ];

        vfsStream::setup('fusion', null, $directory);

        $fusionIncludes = join("\n", array_map(function ($p) {
            return "include: vfs://fusion/$p";
        }, array_keys($directory)));

        $fusionFileCache = $this->getMockBuilder(VariableFrontend::class)->disableOriginalConstructor()->getMock();
//        $testCachedParser = new class($fusionFileCache) extends CachedParser {
//        };

        $cachedParser = new CachedParser();
        $cachedParser->injectFusionFilesObjectTreeCache($fusionFileCache);

        $normal = (new Parser())->parse($fusionIncludes);
        $cached = $cachedParser->parse($fusionIncludes, 'vfs://fusion/entry.fusion');

        self::assertEquals($normal, $cached);
    }

    public function ftestThatTheyMatch()
    {
        // TODO ... WIP
        $fusionIncludes = "
        include: resource://Neos.Fusion/Private/Fusion/Root.fusion
        include: resource://Neos.Fusion.Form/Private/Fusion/Root.fusion
        include: resource://Neos.Neos/Private/Fusion/Root.fusion
        include: resource://Neos.NodeTypes.ContentReferences/Private/Fusion/Root.fusion
        include: resource://Neos.NodeTypes.AssetList/Private/Fusion/Root.fusion
        include: resource://Neos.NodeTypes.Form/Private/Fusion/Root.fusion
        include: resource://Neos.NodeTypes.ColumnLayouts/Private/Fusion/Root.fusion
        include: resource://Neos.NodeTypes.Html/Private/Fusion/Root.fusion
        include: resource://Neos.NodeTypes.Navigation/Private/Fusion/Root.fusion
        include: resource://Neos.NodeTypes/Private/Fusion/Root.fusion
        include: resource://Neos.Neos.Ui/Private/Fusion/Root.fusion
        include: resource://Neos.Seo/Private/Fusion/Root.fusion
        include: resource://Flowpack.Neos.FrontendLogin/Private/Fusion/Root.fusion

        ##
        # Include all .fusion files
        #
        include: **/*.fusion
        ";

        $rel = "resource://Neos.Demo/Private/Fusion/Root.fusion";

        $fusionFileCache = $this->getMockBuilder(VariableFrontend::class)->disableOriginalConstructor()->getMock();
        $testCachedParser = new class($fusionFileCache) extends CachedParser {
            public function __construct(VariableFrontend $fusionFileCache)
            {
                parent::__construct($fusionFileCache);
            }
        };


        $a = (new Parser())->parse($fusionIncludes, $rel);
        $b = $testCachedParser->parse($fusionIncludes, $rel);

        self::assertEquals($a, $b);
    }
}
