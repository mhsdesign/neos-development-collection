<?php

namespace Neos\Fusion\Core;

/**
 * Lexer class.
 *
 * Lazily pulls a token from a stream.
 */
class Lexer
{
    const StateDefault = 0;
    const StateValueAssigment = 1;
    const StateFusionObject = 2;
    const StateObjectPath = 3;

    const PATTERN_EEL_EXPRESSION = '/
  ^\${(?P<exp>
    (?:
      { (?P>exp) }			# match object literal expression recursively
      |$(*SKIP)(*FAIL)     # Skip and fail, if at end
      |[^{}"\']+				# simple eel expression without quoted strings
      |"[^"\\\\]*				# double quoted strings with possibly escaped double quotes
        (?:
          \\\\.			# escaped character (quote)
          [^"\\\\]*		# unrolled loop following Jeffrey E.F. Friedl
        )*"
      |\'[^\'\\\\]*			# single quoted strings with possibly escaped single quotes
        (?:
          \\\\.			# escaped character (quote)
          [^\'\\\\]*		# unrolled loop following Jeffrey E.F. Friedl
        )*\'
    )*
  )}
  /x';
    const PATTERN_WHITE_SPACE = '/^[ \t]+/';
    const PATTERN_NEWLINES = '/^[\n\r]+/';
    const PATTERN_STRING = '/^"((?:\\\"|[^"])+)"/';
    const PATTERN_CHAR = '/^\'((?:\\\\\'|[^\'])+)\'/';
    const SkipComments = [
        ['/^\\/\\/.*/', null /* skip */],
        ['/^#.*/', null /* skip */],
        ['/^\/\\*[\\s\\S]*?\\*\//', null /* skip */],
    ];
    const SkipWhiteSpace = [
        [self::PATTERN_WHITE_SPACE, null /* skip */],
    ];
    const SkipNewLines = [
        [self::PATTERN_NEWLINES, null /* skip */],
    ];
    const FusionObjectToken = [
        ['/^[a-zA-Z0-9.:]*[a-zA-Z][a-zA-Z0-9.:]*/', 'OBJECT_NAME'] // (?=[^.]) donts start with dot.
    ];

    public $string = '';
    public $cursor = 0;
    protected $lengthToPreviousToken = 0;
    protected $currentLexingState = 0;
    protected $SPEC = [];

    protected function lexerStateDefault()
    {
        return [


            [self::PATTERN_NEWLINES, 'NEWLINE'],
            ...self::SkipWhiteSpace,
            ...self::SkipComments,

            // Keywords
            ['/^delete\\s*:\\s+/', 'DELETE'],
            ['/^extends\\s*:\\s+/', 'EXTENDS'],
            ['/^prototype\\s*:\\s+/', 'PROTOTYPE'],
            ['/^include\\s*:/', 'INCLUDE'],
            ['/^namespace\\s*:/', 'NAMESPACE'],


            // Semicolon as delimiter with optional WhiteSpace and NewLines
            ['/^;/', ';'],
        ];
    }

    protected function lexerStateValueAssigment()
    {
        return [
            ...self::SkipNewLines,
            ...self::SkipWhiteSpace,
            ...self::SkipComments,

            // Keywords
            ['/^(true|TRUE)\b/',            fn() => ['TRUE', true]],
            ['/^(false|FALSE)\b/',          fn() => ['FALSE', false]],
            ['/^(null|NULL)\b/',            fn() => ['NULL', null]],

            [self::PATTERN_STRING, fn($matches) => ['STRING', $matches[1]]],
            [self::PATTERN_CHAR, fn($matches) => ['CHAR', $matches[1]]],

            ...self::FusionObjectToken,

            // Numbers
            ['/^-?[0-9]*(?<decimals>\\.[0-9]+)?/',  fn($matches) => ['NUMBER', isset($matches['decimals']) ? floatval($matches[0]) : intval($matches[0])]],

            // Eel Expression
            [self::PATTERN_EEL_EXPRESSION, fn($matches) => ['EEL_EXPRESSION', str_replace("\n", '', $matches[1])]],
            ['/^\${/', 'UNCLOSED_EEL_EXPRESSION'], // the order is lower than the first which would mach a whole eel expression
        ];
    }

    protected function lexerStateObjectPath()
    {
        return [
            ...self::SkipNewLines,
            ...self::SkipWhiteSpace,
            ...self::SkipComments,

            // Operators
            ['/^=/', '='],
            ['/^</', '<'],
            ['/^>/', '>'],
            ['/^extends\\s*:\\s+/', 'EXTENDS'],

            // Symbols.
            ['/^{/', '{'],
            ['/^}/', '}'],
            ['/^\./', '.'],
            ['/^\)/', ')'],

            // Path Segments
            ['/^prototype\\s*\(/', 'PROTOTYPE_START'],
            ['/^@([a-zA-Z0-9:_\-]+)/', fn($matches) => ['METAPROPERTY', $matches[1]]],
            ['/^[a-zA-Z0-9:_\-]+/', 'IDENTIFIER'],

            [self::PATTERN_STRING, fn($matches) => ['STRING', $matches[1]]],
            [self::PATTERN_CHAR, fn($matches) => ['CHAR', $matches[1]]],

        ];
    }

    protected function lexerStateFusionObject()
    {
        return [
            ...self::SkipNewLines,
            ...self::SkipWhiteSpace,
            ...self::SkipComments,
            ...self::FusionObjectToken
        ];
    }

    /**
     * Initializes the string and SPEC.
     */
    public function init(string $string): void
    {
        $this->SPEC = [
            self::StateDefault => $this->lexerStateDefault(),
            self::StateValueAssigment => $this->lexerStateValueAssigment(),
            self::StateObjectPath => $this->lexerStateObjectPath(),
            self::StateFusionObject => $this->lexerStateFusionObject(),
        ];
        $this->string = $string;
    }

    /**
     * Obtains next token.
     */
    public function getNextToken($lengthToPreviousToken = 0): array
    {
        if ($this->hasMoreTokens() === false) {
            return $this->toToken('EOF', null, $lengthToPreviousToken);
        }

        $string = substr($this->string, $this->cursor);

        foreach ($this->SPEC[$this->currentLexingState] as $value) {

            list($regexp, $tokenBuilder) = $value;

            $matches = $this->match($regexp, $string);

            if ($matches === null) {
                continue;
            }

            $tokenLength = strlen($matches[0]);
            $this->cursor += $tokenLength;
            $lengthToPreviousToken += $tokenLength;

            if (is_string($tokenBuilder)) {
                $tokenValue = $matches[0];
                $tokenType = $tokenBuilder;
            } elseif ($tokenBuilder === null) {
                return $this->getNextToken($lengthToPreviousToken);
            } elseif (is_callable($tokenBuilder)) {
                $functionResult = $tokenBuilder($matches);
                list($tokenType, $tokenValue) = $functionResult;
            }

            return $this->toToken($tokenType, $tokenValue, $lengthToPreviousToken);
        }

        return  $this->toToken('NO_TOKEN_FOUND', $string, $lengthToPreviousToken);

        // if in mode wich justs doesnt has this token and peek Generate A NO_TOKEN_IN_MODE
        throw new Error('this doesnt exists ... unexpected token while lexing: ' . $string[0]);
    }

    public function toToken($tokenName, $tokenValue = null, $lengthToPreviousToken = 0): array
    {
        $this->lengthToPreviousToken = $lengthToPreviousToken;

        return [
            'type' => $tokenName,
            'value' => $tokenValue,
        ];
    }

    public function setLexerStateForNextLexing(int $stateId)
    {
        $this->currentLexingState = $stateId;
    }

    public function setCursorToLastTokenStart(): void
    {
        $this->cursor -= $this->lengthToPreviousToken;
    }

    /**
     * Whether we still have more tokens.
     */
    protected function hasMoreTokens(): bool
    {
        return $this->cursor < strlen($this->string);
    }

    protected function match(string $regexp, string $string)
    {
        $isMatch = preg_match($regexp, $string, $matches);
        if ($isMatch === 0)
            return null;
        if ($isMatch === false) {
            throw new \Exception("the regular expression" . $regexp . 'throws an error on this string:' . $string, 1);
        }
        // 0 length match as position marker is not use full
        if (strlen($matches[0]) === 0) {
            return null;
        }
        return $matches;
    }

    /**
     * If the lexer reached EOF.
     */
    protected function isEOF(): bool
    {
        return $this->cursor === strlen($this->string);
    }
}
