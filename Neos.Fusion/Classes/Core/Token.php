<?php

namespace Neos\Fusion\Core;

/**
 * Represents a Token.
 */
class Token
{
    /** @var string  */
    private $value;

    /** @var int  */
    private $type;

    /** @var int  */
    private $lineno;

    /** @var int */
    private $column;

    /** @var int */
    private $offset;

    public const EOF = -1;

    public const SLASH_COMMENT = 0;
    public const HASH_COMMENT = 1;
    public const MULTILINE_COMMENT = 2;

    public const NEWLINE = 3;
    public const SPACE = 4;

    public const TRUE = 5;
    public const FALSE = 6;
    public const NULL = 7;
    public const DELETE = 8;
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
    public const AT = 25;

    public const ASSIGNMENT = 26;
    public const COPY = 27;
    public const UNSET = 28;

    public const DIGIT = 29;
    public const LETTER = 30;

    public const STRING = 31;
    public const CHAR = 32;

    public const EEL_EXPRESSION = 33;

    /**
     * @param int $type The type of the token
     * @param string $value The token value
     * @param int $lineno The line position in the source
     * @param int $column The column on the line
     * @param int $offset
     */
    public function __construct(int $type, string $value, int $lineno, int $column, int $offset)
    {
        $this->type = $type;
        $this->value = $value;
        $this->lineno = $lineno;
        $this->column = $column;
        $this->offset = $offset;
    }

    public function __toString(): string
    {
        return sprintf(
            '%s(%s)',
            self::typeToString($this->type),
            $this->value
        );
    }

    public function getLine(): int
    {
        return $this->lineno;
    }

    public function getColumn(): int
    {
        return $this->column;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * Returns the constant representation (internal) of a given type.
     *
     * @param int $type The type as an integer
     *
     * @return string The string representation
     */
    public static function typeToString(int $type): string
    {
        $constants = (new \ReflectionClass(self::class))->getConstants();
        $stringRepresentation = array_search($type, $constants, true);

        if ($stringRepresentation === false) {
            throw new \LogicException(sprintf('Token of type "%s" does not exist.', $type));
        }
        return $stringRepresentation;
    }
}
