<?php

namespace Neos\Fusion\Core;
use Neos\Fusion;

/**
 * @internal
 */
final class Lexer
{
    // Added an atomic group (to prevent catastrophic backtracking) and removed the end anchor $
    protected const PATTERN_EEL_EXPRESSION = <<<'REGEX'
    /
      ^\${(?P<exp>
        (?>
          { (?P>exp) }          # match object literal expression recursively
          |[^{}"']+	            # simple eel expression without quoted strings
          |"[^"\\]*			    # double quoted strings with possibly escaped double quotes
            (?:
              \\.			# escaped character (quote)
              [^"\\]*		# unrolled loop following Jeffrey E.F. Friedl
            )*"
          |'[^'\\]*			# single quoted strings with possibly escaped single quotes
            (?:
              \\.			# escaped character (quote)
              [^'\\]*		# unrolled loop following Jeffrey E.F. Friedl
            )*'
        )*
      )}
    /x
    REGEX;

    protected const TOKEN_REGEX = [
        Token::SLASH_COMMENT => '/^\\/\\/.*/',
        Token::HASH_COMMENT => '/^#.*/',
        Token::MULTILINE_COMMENT => <<<'REGEX'
        `^
          /\*               # start of a comment '/*'
          [^*]*             # match everything until special case '*'
          (?:
            \*[^/]          # if after the '*' there is a '/' break, else continue
            [^*]*           # until the special case '*' is encountered - unrolled loop following Jeffrey Friedl
          )*
          \*/               # the end of a comment.
        `x
        REGEX,

        Token::NEWLINE => '/^[\n\r]+/',
        Token::SPACE => '/^[ \t]+/',

        // all these values are in atomic groups (to disable backtracking)
        // followed by non . : or alphanumeric, to determine the difference if the value is standalone
        // or part of a fusion object.
        // alternatively: '/^(true|TRUE)\b/',
        // VALUE ASSIGNMENT
        Token::TRUE_VALUE => '/^(?>true|TRUE)(?![a-zA-Z0-9.:])/',
        Token::FALSE_VALUE => '/^(?>false|FALSE)(?![a-zA-Z0-9.:])/',
        Token::NULL_VALUE => '/^(?>null|NULL)(?![a-zA-Z0-9.:])/',
        Token::INTEGER => '/^(?>-?[0-9]+)(?![a-zA-Z.:])/',
        Token::FLOAT => '/^(?>-?[0-9]+\.[0-9]+)(?![a-zA-Z.:])/',

        Token::DSL_EXPRESSION_START => '/^[a-zA-Z0-9\.]+(?=`)/',
        Token::DSL_EXPRESSION_CONTENT => '/^`[^`]*`/',
        Token::EEL_EXPRESSION => self::PATTERN_EEL_EXPRESSION,

        // Object type part
        Token::OBJECT_TYPE_PART => '/^[0-9a-zA-Z.]+/',

        // Keywords
        Token::INCLUDE => '/^include\\s*:/',
        Token::NAMESPACE => '/^namespace\\s*:/',
        Token::PROTOTYPE => '/^prototype\\s*:/',
        Token::UNSET_KEYWORD => '/^unset\\s*:/',

        // Objectpathsegments
        Token::PROTOTYPE_START => '/^prototype\(/',
        Token::META_PATH_START => '/^@/',
        Token::OBJECT_PATH_PART => '/^[a-zA-Z0-9_:-]+/',

        // Operators
        Token::ASSIGNMENT => '/^=/',
        Token::COPY => '/^</',
        Token::UNSET => '/^>/',
        Token::EXTENDS => '/^extends\b/',

        // Symbols
        Token::DOT => '/^\./',
        Token::COLON => '/^:/',
        Token::RPAREN => '/^\)/',
        Token::LBRACE => '/^{/',
        Token::RBRACE => '/^}/',
        Token::DOUBLE_QUOTE => '/^"/',
        Token::SINGLE_QUOTE => '/^\'/',
        Token::SEMICOLON => '/^;/',

        // Strings
        Token::STRING => <<<'REGEX'
        /^
          "[^"\\]*              # double quoted strings with possibly escaped double quotes
            (?:
              \\.               # escaped character (quote)
              [^"\\]*           # unrolled loop following Jeffrey E.F. Friedl
            )*
          "
        /x
        REGEX,
        Token::CHAR => <<<'REGEX'
        /^
          '[^'\\]*              # single quoted strings with possibly escaped single quotes
            (?:
              \\.               # escaped character (quote)
              [^'\\]*           # unrolled loop following Jeffrey E.F. Friedl
            )*
          '
        /x
        REGEX,

        Token::FILE_PATTERN => '`^[a-zA-Z0-9.*:/_-]+`',
    ];

    /**
     * @var string
     */
    protected $code = '';

    /**
     * @var int
     */
    protected $cursor = 0;

    /**
     * @var Token|null
     */
    protected $lookahead = null;

    public function getCode(): string
    {
        return $this->code;
    }

    public function getCursor(): int
    {
        return $this->cursor;
    }

    public function getLookahead(): ?Token
    {
        return $this->lookahead;
    }

    /**
     * Initializes the code
     */
    public function initialize(string $code): void
    {
        $code = str_replace(["\r\n", "\r"], "\n", $code);
        $this->code = $code;
    }

    public function consumeLookahead(): Token
    {
        if ($this->lookahead === null) {
            throw new Fusion\Exception("cannot consume if no token was generated", 1635708717);
        }
        if ($this->lookahead->getType() === Token::EOF) {
            throw new Fusion\Exception("cannot consume <EOF>", 1635708718);
        }
        $token = $this->lookahead;
        $this->lookahead = null;
        return $token;
    }

    public function tryGenerateLookahead(int $tokenType): ?Token
    {
        if ($this->lookahead !== null) {
            throw new Fusion\Exception("cannot generate token if one was generated", 1635708719);
        }
        if ($this->isEof()){
            return $this->lookahead = new Token(Token::EOF, '');
        }
        if ($tokenType === Token::EOF) {
            return null;
        }

        $regexForToken = self::TOKEN_REGEX[$tokenType];

        $remaindingCode = substr($this->code, $this->cursor);

        $match = self::matchRegex($regexForToken, $remaindingCode);

        if ($match === null) {
            return null;
        }

        $this->cursor += strlen($match);

        return $this->lookahead = new Token($tokenType, $match);
    }

    protected function isEof(): bool
    {
        return $this->cursor === strlen($this->code);
    }

    protected static function matchRegex(string $regexp, string $string): ?string
    {
        $isMatch = preg_match($regexp, $string, $matches);
        if ($isMatch === 0) {
            return null;
        }
        if ($isMatch === false) {
            throw new Fusion\Exception("the regular expression: '$regexp' throws an error on this string: '$string'", 1635708720);
        }
        if (strlen($matches[0]) === 0) {
            throw new Fusion\Exception("the regular expression: '$regexp' matches only a position marker on this string: '$string'", 1635708721);
        }
        return $matches[0];
    }
}
