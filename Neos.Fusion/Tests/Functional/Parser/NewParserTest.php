<?php

namespace Neos\Fusion\Tests\Functional\Parser;

use Neos\Fusion\Core\Parser;
use PHPUnit\Framework\TestCase;

class NewParserTest extends TestCase
{

    public function pathBlockTest()
    {

        return [
            [
                <<<'Fusion'
                a {
                }
                Fusion,
                []
            ],
            [
                <<<'Fusion'
                a = ""
                a {
                }
                Fusion,
                ['a' => '']
            ],
            [
                <<<'Fusion'
                a {
                    b = "c"
                }
                Fusion,
                ['a' => ['b' => 'c']]
            ],
            [
                <<<'Fusion'
                a {
                    b {
                        c {
                            d = "e"
                        }
                    }
                }
                Fusion,
                ['a' => ['b' => ['c' => ['d' => 'e']]]]
            ],
            [
                <<<'Fusion'
                a {
                    b {
                        c {
                        }
                    }
                }
                Fusion,
                []
            ],
            [
                <<<'Fusion'
                a { b = ""
                }
                Fusion,
                ['a' => ['b' => ""]]
            ],
            [
                <<<'Fusion'
                a
                {
                    b = ""
                }
                Fusion,
                ['a' => ['b' => ""]]
            ],
            [
                <<<'Fusion'
                a = ""
                a {
                }
                Fusion,
                ['a' => '']
            ],
        ];


    }


    public function commentsTest()
    {
        $obj = fn(string $name):array => ['__objectType' => $name, '__value' => null, '__eelExpression' => null];
        return[
            [
                <<<'Fusion'
                a = 'b' // hallo ich bin ein comment
                b = -123.4 # hallo ich bin ein comment
                c = Neos.Fusion:Hi /* hallo ich bin ein comment */
                Fusion,
                ['a' => 'b', 'b' => -123.4, 'c' => $obj('Neos.Fusion:Hi')]
            ],
            [
                <<<'Fusion'
                // hallo ich bin ein comment
                # hallo ich bin ein comment
                /* hallo ich bin

                 ein comment */
                Fusion,
                []
            ],
        ];
    }

    public function prototypeDeclarationAndInheritance()
    {
        return [
            [
                <<<'Fusion'
                prototype(Neos.Foo:Bar2) {
                    baz = 'Foo'
                }
                Fusion,
                ['__prototypes' => ['Neos.Foo:Bar2' => ['baz' => 'Foo']]]
            ],
            [
                <<<'Fusion'
                prototype(Neos.Foo:Bar2).baz = 'Foo'
                Fusion,
                ['__prototypes' => ['Neos.Foo:Bar2' => ['baz' => 'Foo']]]
            ],
            [
                <<<'Fusion'
                prototype(Neos.Foo:Bar2) {
                }
                Fusion,
                []
            ],
            [
                <<<'Fusion'
                prototype(Neos.Foo:Bar2) {
                }
                prototype(Neos.Foo:Bar2).baz = 42
                Fusion,
                ['__prototypes' => ['Neos.Foo:Bar2' => ['baz' => 42]]]
            ],
            [
                <<<'Fusion'
                prototype(Neos.Foo:Bar2) {
                    bar = 1
                } hello = "w"
                Fusion,
                ['__prototypes' => ['Neos.Foo:Bar2' => ['bar' => 1]], 'hello' => "w"]
            ],
            [
                <<<'Fusion'
                prototype(Neos.Foo:Bar3) < prototype(Neos.Foo:Bar2) {
                    foo = ''
                }
                Fusion,
                ['__prototypes' => ['Neos.Foo:Bar3' => [
                    '__prototypeObjectName' => 'Neos.Foo:Bar2',
                    '__prototypeChain' => ['Neos.Foo:Bar2'],
                    'foo' => ''
                ]]]
            ],
        ];
    }

    public function throwsTest()
    {
        return [
            ['fwefw/*fwef*/fewfwe1212 = ""'], // no comments inside everywhere
            ['namespace: cms=\-notvalid-\Fusion\Fixtures'], // Checks if a leading slash in the namespace declaration throws an exception
            ['"" = ""'], // no empty strings in path
            ['a = 1354.154.453'], // this is not an object nor a number
            ['a = ....fewf..fwe.1415'], // this is not an object
            ['äüöfwef = ""'], // no utf in path
            ['nomultidots..few = ""'],
            ['.nostartingdot = ""'],
            ['32fe wfwe.f = ""'], // no spaces in path
            ['pat . fewfw  .  fewf       .    = ""'], // no spaces between dots
            ['a = Neos . Fusion:Hi . Stuff'], // no spaces between objects dots
            ['a = Fusion  : Object'], // no spaces between objects  colons
            ['namespace: "a=Neos.Fusion"'], // no namespace with string
            ['{}'], // block out of context
            ['a = ${ fwef fewf {}'], // unclosed eel
            [<<<'Fusion'
            a = "fwfe"
            }
            Fusion], // block out of context
            ['a { b = "" }'], // no end of line detected
            // An exception was thrown while Neos tried to render your page
            [<<<'Fusion'
            a = Neos.Fusion:Value {
              value = Neos.Fusion:Join {
                a = "wfef"
            }
            Fusion],
            [<<<'Fusion'
            baz =
            'Foo'
            Fusion],
            [<<<'Fusion'
            baz
            =
            'Foo'
            Fusion],
            [<<<'Fusion'
            baz
            = 'Foo'
            Fusion],
        ];
    }

    public function unexpectedCopyAssigment()
    {
        return [
            ['a < b', ['b' => [], 'a' => []]],
            ['n.a < b', ['b' => [], 'n' => ['a' => []]]],

            [<<<'Fusion'
            b = "hui"
            a < b
            Fusion, ['b' => 'hui', 'a' => 'hui']],

            [<<<'Fusion'
            b = "hui"
            a < .b
            Fusion, ['b' => 'hui', 'a' => 'hui']],

            [<<<'Fusion'
            b = "hui"
            a < b
            b = "wont change a"
            Fusion, ['b' => 'wont change a', 'a' => 'hui']],

            [<<<'Fusion'
            n.b = "hui"
            n.a < .b
            Fusion, ['n' => ['b' => 'hui', 'a' => 'hui']]],

            [<<<'Fusion'
            n.m {
                b = "hui"
                a < .b
            }
            Fusion, ['n' => ['m' => ['b' => 'hui', 'a' => 'hui']]]],
        ];
    }

    public function unexpectedObjectPaths()
    {
        return [
            ['0 = ""', [0 => '']],
            ['125646531 = ""', [125646531 => '']],
            ['0.1 = ""', [0 => [1 => '']]],
            ['TRUE = ""', ['TRUE' => '']],
            ['FALSE = ""', ['FALSE' => '']],
            ['NULL = ""', ['NULL' => '']],
            [': = ""', [':' => '']],
            ['- = ""', ['-' => '']],
            ['_ = ""', ['_' => '']],
            ['something: = ""', ['something:' => '']],
            ['-_-:so-:m33_24et---hing00: = ""', ['-_-:so-:m33_24et---hing00:' => '']],
            ['"a.b" = ""', ['a.b' => '']],
            ['\'a.b\' = ""', ['a.b' => '']],
        ];
    }

    public function nestedObjectPaths()
    {
        return [
            ['a.b.c = ""', ['a' => ['b' => ['c' => '']]]],
            ['0.60.hello = ""', [0 => [60 => ['hello' => '']]]],
            ['"a.b.c".132.hel-lo.what: = ""', ['a.b.c' => [132 => ['hel-lo' => ['what:' => '']]]]],
        ];
    }

    public function simpleValueAssign()
    {
        return [
            ['a="b"', ['a' => 'b']],
            ['a = "b"', ['a' => 'b']],
            ['   a   =  "b"     ', ['a' => 'b']],
            ['a =  "b"
                                ', ['a' => 'b']],
            ['

                    a =  "b"', ['a' => 'b']],
            ['

                     a =  "b"

                     ', ['a' => 'b']],
            ['a = 123', ['a' => 123]],
            ['a = -123', ['a' => -123]],
            ['a = 1.123', ['a' => 1.123]],
            ['a = -1.123', ['a' => -1.123]],
            ['a = FALSE', ['a' => false]],
            ['a = false', ['a' => false]],
            ['a = TRUE', ['a' => true]],
            ['a = true', ['a' => true]],
            ['a = NULL', ['a' => null]],
            ['a = null', ['a' => null]],
        ];
    }

    // eel funny nested
    // eel wiht ${"fewfg\\"} // backslash before "

    public function stringAndCharValueAssign()
    {
        return [
            [<<<'Fusion'
            a = 'The end of this line is one escaped backslash \\'
            Fusion, ['a' => 'The end of this line is one escaped backslash \\']],
            ['a = ""', ['a' => '']],
            ['a = \'\'', ['a' => '']],
            ['a = "a\"b"', ['a' => 'a"b']],
            ['a = "a\nb"', ['a' => 'a'. chr(10) . 'b']],
            ['a = \'a"b\'', ['a' => 'a"b']],
            ['a = \'a"b\'', ['a' => 'a"b']],
            ['a = \'a\nb\'', ['a' => 'a\nb']],
//            ['a = "a\b"', ['a' => 'a\b']],
        ];
    }

    public function fusionObjectNameEdgeCases() {
        $obj = fn(string $name):array => ['__objectType' => $name, '__value' => null, '__eelExpression' => null];

        return [
            ['a = Foo.null.Bar', ['a' => $obj('Neos.Fusion:Foo.null.Bar')]],
            ['a = 101.Bar', ['a' => $obj('Neos.Fusion:101.Bar')]],
            ['a = true.101.Bar', ['a' => $obj('Neos.Fusion:true.101.Bar')]],
            ['a = 4Foo.Bar', ['a' => $obj('Neos.Fusion:4Foo.Bar')]],
            ['a = 3Vendor:Name', ['a' => $obj('3Vendor:Name')]],
            ['a = V3ndor:Name', ['a' => $obj('V3ndor:Name')]],
            ['a = TRUE.Vendor:Object', ['a' => $obj('TRUE.Vendor:Object')]],
        ];
    }

    /**
     * @test
     * @dataProvider commentsTest
     * @dataProvider simpleValueAssign
     * @dataProvider unexpectedCopyAssigment
     * @dataProvider unexpectedObjectPaths
     * @dataProvider nestedObjectPaths
     * @dataProvider pathBlockTest
     * @dataProvider stringAndCharValueAssign
     * @dataProvider prototypeDeclarationAndInheritance
     * @dataProvider fusionObjectNameEdgeCases
     */
    public function evaluateTests($fusion, $expectedAst): void
    {
        self::assertFusionAst($fusion, $expectedAst);
    }

    /**
     * @test
     * @dataProvider throwsTest
     */
    public function evaluateThrowing($fusion): void
    {
        self::expectException(\Exception::class);

        $parser = new Parser;
        $parser->parse($fusion);
    }

    static protected function assertFusionAst($fusion, $expectedAst)
    {
        $parser = new Parser;
        $parsedFusionAst = $parser->parse($fusion);

        self::assertEquals($expectedAst, $parsedFusionAst);
    }

}
