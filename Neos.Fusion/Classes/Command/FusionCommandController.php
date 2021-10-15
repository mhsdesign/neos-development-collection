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


        $parser = new Parser;
        $input = <<<Fusion



        a = "b" {
                prototype: a {
            lol = "fwef"
        }

            c = "fwfe"
        }

        Fusion;

        $output = $parser->parse($input);

//         var_dump($output["a"]);
        $this->outputLine(json_encode($output, JSON_PRETTY_PRINT));
    }
}
