<?php

namespace Neos\Fusion\Core;
use Neos\Fusion;

class Token
{
    public const EOF = -1;

    public const SLASH_COMMENT = 0;
    public const HASH_COMMENT = 1;
    public const MULTILINE_COMMENT = 2;

    public const NEWLINE = 3;
    public const SPACE = 4;

    public const META_PATH_START = 36;
    public const OBJECT_PATH_PART = 35;

    public const OBJECT_TYPE_PART = 39;

    public const TRUE_VALUE = 5;
    public const FALSE_VALUE = 6;
    public const NULL_VALUE = 7;

    public const INTEGER = 41;
    public const FLOAT = 45;

    public const STRING = 31;
    public const CHAR = 32;

    public const EEL_EXPRESSION = 33;
    public const DSL_EXPRESSION_START = 43;
    public const DSL_EXPRESSION_CONTENT = 44;

    public const PROTOTYPE_START = 13;
    public const PROTOTYPE = 10;
    public const INCLUDE = 11;
    public const NAMESPACE = 12;
    public const UNSET_KEYWORD = 8;

    public const ASSIGNMENT = 26;
    public const COPY = 27;
    public const UNSET = 28;
    public const EXTENDS = 9;

    public const SEMICOLON = 14;
    public const DOT = 15;
    public const COLON = 16;
    public const RPAREN = 18;
    public const LBRACE = 19;
    public const RBRACE = 20;
    public const DOUBLE_QUOTE = 37;
    public const SINGLE_QUOTE = 38;

    public const FILE_PATTERN = 40;

    /** @var string  */
    private $value;

    /** @var int  */
    private $type;

    /**
     * @param int $type The type of the token
     * @param string $value The token value
     */
    public function __construct(int $type, string $value)
    {
        $this->type = $type;
        $this->value = $value;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Returns the constant representation of a given type.
     *
     * @param int $type The type as an integer
     *
     * @return string The string representation
     * @throws Fusion\Exception
     */
    public static function typeToString(int $type): string
    {
        $constants = (new \ReflectionClass(self::class))->getConstants();

        $stringRepresentation = array_search($type, $constants, true);

        if ($stringRepresentation === false) {
            throw new Fusion\Exception("Token of type '$type' does not exist");
        }
        return $stringRepresentation;
    }
}
