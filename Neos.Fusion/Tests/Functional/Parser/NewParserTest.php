<?php

namespace Neos\Fusion\Tests\Functional\Parser;

use Neos\Fusion\Core\Parser;
use PHPUnit\Framework\TestCase;

class NewParserTest extends TestCase
{



    /**
     * @test
     */
    public function testwefwf(): void
    {
        $a = "
        fewf

        ";

        $fusion = <<<FUSION
        a = "b"
        FUSION;
        $expectedAst = [
            "a" => "b"
        ];
        self::evaluateTest($fusion, $expectedAst);
    }

    static protected function evaluateTest($fusion, $expectedAst)
    {
        $parser = new Parser;
        $parsedFusionAst = $parser->parse($fusion);

        self::assertEquals($parsedFusionAst, $expectedAst);
    }

}
