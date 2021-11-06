<?php

namespace Neos\Fusion\Core;
use Neos\Fusion;

class Lexer
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

        /**
         * all these values are in atomic groups (to disable backtracking) followed by not a . : or alphanumeric, to determine the difference if the value is stanalone or part of an fusion object.
         */
//        Token::TRUE_VALUE => '/^(true|TRUE)\b/',
        Token::TRUE_VALUE => '/^(?>true|TRUE)(?![a-zA-Z0-9.:])/',
        Token::FALSE_VALUE => '/^(?>false|FALSE)(?![a-zA-Z0-9.:])/',
        Token::NULL_VALUE => '/^(?>null|NULL)(?![a-zA-Z0-9.:])/',
        Token::INTEGER => '/^(?>-?[0-9]+)(?![a-zA-Z.:])/',
        Token::FLOAT => '/^(?>-?[0-9]+\.[0-9]+)(?![a-zA-Z.:])/',

        Token::OBJECT_TYPE_PART => <<<'REGEX'
        /^[0-9a-zA-Z.]+/
        REGEX,

        // TODO: strict rule for object types?
//        Token::OBJECT_TYPE_PART => <<<'REGEX'
//        /^[0-9a-zA-Z]*[a-zA-Z][0-9a-zA-Z]*(?:\.[0-9a-zA-Z]+)*/
//        REGEX,

        Token::UNSET_KEYWORD => '/^unset\\s*:\\s+/',
        Token::PROTOTYPE => '/^prototype\\s*:\\s+/',
        Token::INCLUDE => '/^include\\s*:/',
        Token::NAMESPACE => '/^namespace\\s*:/',
        Token::PROTOTYPE_START => '/^prototype\(/',
        Token::META_PATH_START => '/^@/',

        Token::OBJECT_PATH_PART => '/^[a-zA-Z0-9_:-]+/',

        Token::FILE_PATTERN => '`^[a-zA-Z0-9.*:/_-]+`',

        // Symbols
        Token::SEMICOLON => '/^;/',
        Token::DOT => '/^\./',
        Token::COLON => '/^:/',
        Token::RPAREN => '/^\)/',
        Token::LBRACE => '/^{/',
        Token::RBRACE => '/^}/',
        Token::MINUS => '/^-/',
        Token::STAR => '/^\\*/',
        Token::SLASH => '/^\//',
        Token::UNDERSCORE => '/^_/',
        Token::ASSIGNMENT => '/^=/',
        Token::DOUBLE_QUOTE => '/^"/',
        Token::SINGLE_QUOTE => '/^\'/',

        Token::COPY => '/^</',
        Token::UNSET => '/^>/',
        Token::EXTENDS => '/^extends\b/',


        Token::LETTER => '/^[a-zA-Z]+/',

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

        Token::DSL_EXPRESSION_START => '/^[a-zA-Z0-9\.]+(?=`)/',

        Token::DSL_EXPRESSION_CONTENT => '/^`[^`]*`/',

        Token::EEL_EXPRESSION => self::PATTERN_EEL_EXPRESSION
    ];

    protected $code = '';
    protected $cursor = 0;
    protected $SPEC = [];

    /**
     * @var Token|null
     */
    protected $lookahead = null;


    /**
     * Initializes the string
     */
    public function initialize(string $string): void
    {
        $string = str_replace(["\r\n", "\r"], "\n", $string);
        $this->code = $string;
    }

    public function getLookahead(): ?Token
    {
        return $this->lookahead;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getCursor(): int
    {
        return $this->cursor;
    }

    public function consumeLookahead(): Token
    {
        if ($this->lookahead === null) {
            throw new Fusion\Exception("cannot consume if no token was generated", 1635708717);
        } elseif ($this->lookahead->getType() === Token::EOF) {
            throw new Fusion\Exception("cannot consume <EOF>", 1635708717);
        }
        $token = $this->lookahead;
        $this->lookahead = null;
        return $token;
    }

    public function tryGenerateLookahead(int $type): ?Token
    {
        if ($this->lookahead !== null) {
            throw new Fusion\Exception("cannot generate token if one was generated", 1635708717);
        }

        if ($this->isEof()){
            return $this->lookahead = $this->toToken(Token::EOF);
        } elseif ($type === Token::EOF) {
            return null;
        }


        $regex = self::TOKEN_REGEX[$type];

        $string = substr($this->code, $this->cursor);

        $match = $this->match($regex, $string);

        if ($match === null) {
            return null;
        }
        $this->cursor += strlen($match);

        return $this->lookahead = $this->toToken($type, $match);
    }



    public function toToken(int $tokenType, $tokenValue = ''): Token
    {
//        if (FLOW_APPLICATION_CONTEXT === 'Development') {
//            print_r([
//                'type' => TOKEN::typeToString($tokenType),
//                'value' => $tokenValue,
//            ]);
//        }
        return new Token($tokenType, $tokenValue);
    }

    protected function match(string $regexp, string $string): ?string
    {
        $isMatch = preg_match($regexp, $string, $matches);
        if ($isMatch === 0)
            return null;
        if ($isMatch === false) {
            throw new Fusion\Exception("the regular expression" . $regexp . 'throws an error on this string:' . $string, 1635708717);
        }
        if (strlen($matches[0]) === 0) {
            throw new Fusion\Exception("the regular expression" . $regexp . 'is only a position marker on this string:' . $string, 1635708717);
        }
        return $matches[0];
    }

    public function isEof(): bool
    {
        return $this->cursor === strlen($this->code);
    }
}
