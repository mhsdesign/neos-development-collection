<?php
namespace Neos\Fusion\Command;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Fusion\Core\Parser;
use Neos\Fusion\Core\ParserOld;
use Neos\Fusion\Annotations\LexerMode;



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

//        $parser = new ParserOld();
        $parser = new Parser();

//kaspersObject = Text
//kaspersObject.value = "The end of this line is a backslash\\"
//kaspersObject.bar = "Here comes \\ a backslash in the middle"
// a = \${"effe\"}

//        $input = file_get_contents('/home/macode/untouched/neos-development3/Packages/Neos/Neos.Fusion/Tests/Functional/Parser/lol.fusion');
//        $input = file_get_contents('/home/macode/untouched/neos-development3/Packages/Neos/Neos.Fusion/Tests/Unit/Core/Fixtures/ParserTestFusionComments01.fusion');


//        $input = 'a = \'fewfw\\\\\'';

        $input = <<<'Lol'

include: fwefew/**/*.f.wefwe

Lol;

//        \Neos\Flow\var_dump($input);

//        $input = "       \n                 a=1331  \n";

        $output = $parser->parse($input);

//         var_dump($output["a"]);
        $this->outputLine(json_encode($output, JSON_PRETTY_PRINT));
    }
}
