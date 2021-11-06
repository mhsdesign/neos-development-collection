<?php

namespace Neos\Fusion\Core;
use Neos\Fusion;

/**
 * Represents a Token.
 */
class Token
{
    public const OBJECT_PATH_PART = 35;
    public const META_PATH_START = 36;
    public const DOUBLE_QUOTE = 37;
    public const SINGLE_QUOTE = 38;
    public const OBJECT_TYPE_PART = 39;
    public const FILE_PATTERN = 40;
    public const INTEGER = 41;
    public const DECIMAL = 42;
    public const DSL_EXPRESSION_START = 43;
    public const DSL_EXPRESSION_CONTENT = 44;
    const FLOAT = 45;

    /** @var string  */
    private $value;

    /** @var int  */
    private $type;

    public const EOF = -1;

    public const SLASH_COMMENT = 0;
    public const HASH_COMMENT = 1;
    public const MULTILINE_COMMENT = 2;

    public const NEWLINE = 3;
    public const SPACE = 4;

    public const TRUE_VALUE = 5;
    public const FALSE_VALUE = 6;
    public const NULL_VALUE = 7;
    public const UNSET_KEYWORD = 8;
    public const EXTENDS = 9;
    public const PROTOTYPE = 10;
    public const INCLUDE = 11;
    public const NAMESPACE = 12;
    public const PROTOTYPE_START = 13;

    public const SEMICOLON = 14;
    public const DOT = 15;
    public const COLON = 16;
//    public const LPAREN = 17;
    public const RPAREN = 18;
    public const LBRACE = 19;
    public const RBRACE = 20;
    public const MINUS = 21;
    public const STAR = 22;
    public const SLASH = 23;
    public const UNDERSCORE = 24;

    public const ASSIGNMENT = 26;
    public const COPY = 27;
    public const UNSET = 28;

    public const LETTER = 30;

    public const STRING = 31;
    public const CHAR = 32;

    public const EEL_EXPRESSION = 33;

    /**
     * @param int $type The type of the token
     * @param string $value The token value
     */
    public function __construct(int $type, string $value)
    {
        $this->type = $type;
        $this->value = $value;
    }

    public function __toString(): string
    {
        return sprintf(
            '%s(%s)',
            self::typeToString($this->type),
            $this->value
        );
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    protected static $constants;

    /**
     * Returns the constant representation (internal) of a given type.
     *
     * @param int $type The type as an integer
     *
     * @return string The string representation
     */
    public static function typeToString(int $type): string
    {
        // TODO: ensure that all constants have unique values.?
        self::$constants ?? self::$constants = (new \ReflectionClass(self::class))->getConstants();

        $stringRepresentation = array_search($type, self::$constants, true);

        if ($stringRepresentation === false) {
            throw new Fusion\Exception(sprintf('Token of type "%s" does not exist.', $type));
        }
        return $stringRepresentation;
    }
}
