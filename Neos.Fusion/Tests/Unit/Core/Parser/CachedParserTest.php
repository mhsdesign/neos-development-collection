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


use Neos\Flow\Tests\FunctionalTestCase;
use Neos\Flow\Tests\UnitTestCase;
use Neos\Fusion\Core\CachedParser;
use Neos\Fusion\Core\Parser;



class CachedParserTest extends FunctionalTestCase
{

    public function testThatTheyMatch()
    {
        $fusionIncludes = array (
            0 => 'resource://Neos.Fusion/Private/Fusion/Root.fusion',
            1 => 'resource://Neos.Fusion.Form/Private/Fusion/Root.fusion',
            2 => 'resource://Neos.Neos/Private/Fusion/Root.fusion',
            3 => 'resource://Neos.NodeTypes.ContentReferences/Private/Fusion/Root.fusion',
            4 => 'resource://Neos.Neos.Ui/Private/Fusion/Root.fusion',
            5 => 'resource://Neos.Seo/Private/Fusion/Root.fusion',
            6 => 'resource://Neos.NodeTypes.ColumnLayouts/Private/Fusion/Root.fusion',
            7 => 'resource://Neos.NodeTypes.AssetList/Private/Fusion/Root.fusion',
            8 => 'resource://Neos.NodeTypes.Navigation/Private/Fusion/Root.fusion',
            9 => 'resource://Neos.NodeTypes.Form/Private/Fusion/Root.fusion',
            10 => 'resource://Neos.NodeTypes.Html/Private/Fusion/Root.fusion',
            11 => 'resource://Neos.NodeTypes/Private/Fusion/Root.fusion',
            12 => 'resource://Flowpack.Neos.FrontendLogin/Private/Fusion/Root.fusion',
            13 => 'resource://Neos.Demo/Private/Fusion/Root.fusion',
        );


        $a = (new Parser())->parseIncludeFileList($fusionIncludes);

        $b = (new CachedParser())->parseIncludeFileList($fusionIncludes);
//
//        var_export($a['root']);
//        var_export($b['root']);
//        die();

        self::assertEquals($a['root'], $b['root']);

    }

}
