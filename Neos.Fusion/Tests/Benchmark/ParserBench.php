<?php
namespace Neos\Fusion\Tests\Benchmark;

/*
 * This file is part of the Neos.Fusion package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Fusion\Core\Parser;
use Neos\Fusion\Core\ParserOld;

/**
 * A benchmark to test the Fusion Parser
 *
 * @BeforeMethods({"init"})
 */
class ParserBench
{
    /**
     * @var ParserOld
     */
    protected $parserOld;

    /**
     * @var Parser
     */
    protected $newParser;

    protected $bigFusionToBeParsed;
    protected $mediumFusionToBeParsed;
    protected $smallFusionToBeParsed;
    protected $fusionFileContext;


    public function init()
    {
        $this->newParser = new Parser();
        $this->parserOld = new ParserOld();
        $this->fusionFileContext = null;

//        $this->fusionFileContext = "/home/some/path/to/private/site/to/test/many/files";
//        $this->bigFusionToBeParsed = <<<'Fusion'
//        include: **/*.fusion
//        include: "resource://Neos.Fusion/Private/Fusion/Root.fusion"
//        include: "resource://Neos.Neos/Private/Fusion/Root.fusion"
//        Fusion;

        $this->mediumFusionToBeParsed = <<<'Fusion'
        include: "resource://Neos.Demo/Private/Fusion/Root.fusion"
        include: "resource://Neos.Fusion/Private/Fusion/Root.fusion"
        include: "resource://Neos.Neos/Private/Fusion/Root.fusion"
        Fusion;

        $this->smallFusionToBeParsed = <<<'Fusion'
        include: "resource://Neos.Fusion/Private/Fusion/Root.fusion"
        root = "hello world"
        root >
        root = Neos.Fusion:Case {
            eel = ${"stuff" + "stuff"}
            a = "hello"
            b = afx`
                <h1>Neos</h1>
                <a>hi</a>
            `
        }
        Fusion;
    }

//    /**
//     * @Iterations(10)
//     * @Revs(25)
//     */
//    public function bench_parser_old_big_ast()
//    {
//        $this->parserOld->parse($this->bigFusionToBeParsed, $this->fusionFileContext);
//    }
//
//    /**
//     * @Iterations(10)
//     * @Revs(25)
//     */
//    public function bench_parser_new_big_ast()
//    {
//        $this->newParser->parse($this->bigFusionToBeParsed, $this->fusionFileContext);
//    }

    /**
     * @Iterations(10)
     * @Revs(50)
     */
    public function bench_parser_old_medium_ast()
    {
        $this->parserOld->parse($this->mediumFusionToBeParsed, $this->fusionFileContext);
    }

    /**
     * @Iterations(10)
     * @Revs(50)
     */
    public function bench_parser_new_medium_ast()
    {
        $this->newParser->parse($this->mediumFusionToBeParsed, $this->fusionFileContext);
    }


    /**
     * @Iterations(10)
     * @Revs(1000)
     */
    public function bench_parser_old_small_ast()
    {
        $this->parserOld->parse($this->smallFusionToBeParsed, $this->fusionFileContext);
    }

    /**
     * @Iterations(10)
     * @Revs(1000)
     */
    public function bench_parser_new_small_ast()
    {
        $this->newParser->parse($this->smallFusionToBeParsed, $this->fusionFileContext);
    }
}
