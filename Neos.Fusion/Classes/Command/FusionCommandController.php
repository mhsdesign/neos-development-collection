<?php
namespace Neos\Fusion\Command;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Fusion\Core\Parser;
use Neos\Fusion\Core\ParserOld;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class FusionCommandController extends CommandController
{



    public function showCommand()
    {


//        ['/^\\d+(?<decimals>\\.\\d*)?/', fn($matches) => ['NUMBER', isset($matches['decimals']) ? floatval($matches[0]) : intval($matches[0])]],

//        $a = '/^\\d*(?<decimals>\\.\\d+)?/';
//
//        preg_match($a, '.46546', $m);
//
//
//        var_dump($m);
//
//        // $this->hello();

//        $parser = new ParserOld;

//
//        v = \${"wfef"
//    ()
//    }
//
//        a.
//        wfefwef.
//        wow
//            =
//            54f56561
//        {
//            c = "fwef"
//        }f=false{ g=fwef;}



//        45445.145-45 = 4f.Fusion
//        fwefew = lol:moin {
//        }
//
//        namespace: lol = Mhs.Design
//
//        lol = 4458454
//
//
//        c = lol:moin {
//            }
//
//        prototype:a extends:b {
//                lol = "fwef"
//            delete: .lol
//        }
//        include: fwefw/f/**/*.fwefwe

//        $parserOld = new ParserOld();
        $parser = new Parser();

//kaspersObject = Text
//kaspersObject.value = "The end of this line is a backslash\\"
//kaspersObject.bar = "Here comes \\ a backslash in the middle"
// a = \${"effe\"}

//        $input = file_get_contents('/home/macode/untouched/neos-development3/Packages/Neos/Neos.Fusion/Tests/Functional/Parser/lol.fusion');
//        $input = file_get_contents('/home/macode/untouched/neos-development3/Packages/Neos/Neos.Fusion/Tests/Unit/Core/Fixtures/ParserTestFusionComments01.fusion');


//        $input = 'a = \'fewfw\\\\\'';
# include: fwefew/**/*.f.wefwe

//        a = "" /*
//                    multiline after
//                */ b = 123 /*
//                    multiline wrap
//                */

        // prototype(a) < path.simple eof error when at end
        $input = <<<'Lol'

include: **/*fweefw*fewf

Lol;


//        \Neos\Flow\var_dump($input);

//        $input = "       \n                 a=1331  \n";

        $file = "/home/macode/dba/dba-codes_website-main/DistributionPackages/Resources/Private/Fusion/Root.fusion";



        $output = $parser->parse($input, $file);
//
//        try {
//            $outputOld = $parserOld->parse($input, $file);
//
//        }catch (\Exception $e) {
//
//        }

        $this->outputLine(json_encode($output, JSON_PRETTY_PRINT));
//        $this->outputLine(json_encode($outputOld, JSON_PRETTY_PRINT));

//        if (isset($outputOld)) {
//
////            \Neos\Flow\var_dump($outputOld);
////
////            \Neos\Flow\var_dump($outputOld);
////            \Neos\Flow\var_dump($output);
//
////            $a->testStuff($outputOld, $output);
////
//
////            TestCase::assertEquals($outputOld, $output);
////        } else {
////            $this->outputLine("<error>old parser dint eat it</error>");
////            throw $e;
////        }
//
//        $this->outputLine('<success>This shit matched</success>');

//         var_dump($output["a"]);
//        $this->outputLine(json_encode($hi, JSON_PRETTY_PRINT));
    }


    public function compareCommand()
    {
        $fusion = <<<'Fusion'

include: File with space.fusion /**/

//include: /**/*
//
//include: resource://Neos.Demo/Private/Fusion/**/*
//include: resource://Neos.Fusion.Form/Private/Fusion/**/*
//include: resource://Neos.Fusion/Private/Fusion/**/*
//include: resource://Neos.Neos/Private/Fusion/**/*

Fusion;
        $context = 'resource://Neos.Demo/Private/Fusion/Root.fusion';
//        $context = "/home/macode/dba/dba-codes_website-main/DistributionPackages/Resources/Private/Fusion/Root.fusion";

        try {
            $new = (new Parser())->parse($fusion, $context);
        } catch (\Exception $eNew) {
            $new = false;
        }

        try {
            $old = (new ParserOld())->parse($fusion, $context);
        } catch (\Exception $eOld) {
            $old = false;
        }


        if ($new !== false && $old !== false) {
            $this->dump($new);
            Assert::assertSame($old, $new, 'Old ast does not eq new');
            $this->outputLine('<success>Both eq</success>');
            return;
        }

        $new === false ?: $this->dump($new);
        $old === false ?: $this->dump($old);

        if ($new === false) {
            $this->outputLine('<error>New one dint like it.</error>');
            $this->outputLine($eNew);
        }

        if ($old === false) {
            $this->outputLine('<error>Old one dint like it.</error>');
            $this->outputLine($eOld);
        }

    }


    public function newCommand()
    {
        $fusion = <<<'Fusion'

        include: "resource://Neos.Demo/Private/Fusion/Root.fusion"
        include: "resource://Neos.Demo/Private/Fusion/Root.fusion"
        include: "resource://Neos.Demo/Private/Fusion/Root.fusion"
        include: "resource://Neos.Demo/Private/Fusion/Root.fusion"
        include: "resource://Neos.Demo/Private/Fusion/Root.fusion"
        include: "resource://Neos.Demo/Private/Fusion/Root.fusion"

Fusion;

//        $context = 'resource://Neos.Demo/Private/Fusion/Root.fusion';
        $context = "/home/macode/dba/dba-codes_website-main/DistributionPackages/Resources/Private/Fusion/Root.fusion";
        $this->dump((new Parser())->parse($fusion,  $context));
    }

    public function oldCommand()
    {
        $fusion = <<<'Fusion'
        include: /**/*
        # unset: a
        //        a = "fw"
        //        include: Lol.fusion
        Fusion;

        $context = 'resource://Neos.Demo/Private/Fusion/Root.fusion';
        $this->dump((new ParserOld())->parse($fusion, $context));
    }


    protected function dump($array)
    {
        $this->outputLine(json_encode($array, JSON_PRETTY_PRINT));
    }

}
