<?php

namespace Neos\Fusion\Core;

class Lexer
{
    const PATTERN_EEL_EXPRESSION = <<<'REGEX'
    /
      ^\${(?P<exp>
        (?:
          { (?P>exp) }          # match object literal expression recursively
          |$(*SKIP)(*FAIL)      # Skip and fail, if at end
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

    protected $string = '';
    protected $cursor = 0;
    protected $SPEC = [];

    protected function lexerSpec()
    {
        return [
            // Comments
            ['/^\\/\\/.*/', Token::SLASH_COMMENT],
            ['/^#.*/', Token::HASH_COMMENT],
//            ['/^\/\\*[\\s\\S]*?\\*\//', '/*COMMENT*/'],
//            [<<<'REGEX'
//            /^
//              \/\*          # Comment Start
//              [^\*]*        # Normal Case no
//              (?:
//                \*[^\/]     # special * was encountered next char should not be / (see */)
//                [^\*]*        # unrolled loop jfriedel -> normal case
//              )*
//              \*\/         # end of comment */
//            /
//            REGEX, '/*COMMENT*/' /* skip */],

            [<<<'REGEX'
            /^\/\*[^\*]*(?:\*[^\/][^\*]*)*\*\//
            REGEX, Token::MULTILINE_COMMENT],

            ['/^[\n\r]+/', Token::NEWLINE],
            ['/^[ \t]+/', Token::SPACE],

            // Keywords
            ['/^(true|TRUE)\b/', Token::TRUE_VALUE],
            ['/^(false|FALSE)\b/', Token::FALSE_VALUE],
            ['/^(null|NULL)\b/', Token::NULL_VALUE],

            ['/^unset\\s*:\\s+/', Token::UNSET_KEYWORD],
            ['/^prototype\\s*:\\s+/', Token::PROTOTYPE],
            ['/^include\\s*:/', Token::INCLUDE],
            ['/^namespace\\s*:/', Token::NAMESPACE],
            ['/^prototype\\s*\(/', Token::PROTOTYPE_START],

            // Symbols
            ['/^;/', Token::SEMICOLON],
            ['/^\./', Token::DOT],
            ['/^:/', Token::COLON],
            ['/^\)/', Token::RPAREN],
            ['/^{/', Token::LBRACE],
            ['/^}/', Token::RBRACE],
            ['/^-/', Token::MINUS],
            ['/^\\*/', Token::STAR],
            ['/^\//', Token::SLASH],
            ['/^_/', Token::UNDERSCORE],
            ['/^@/', Token::AT],

            ['/^=/', Token::ASSIGNMENT],
            ['/^</', Token::COPY],
            ['/^>/', Token::UNSET],
            ['/^extends\b/', Token::EXTENDS],


            ['/^[0-9]+/', Token::DIGIT],
            ['/^[a-zA-Z]+/', Token::LETTER],

            // Strings
            [<<<'REGEX'
            /^"[^"\\]*(?:\\.[^"\\]*)*"/
            REGEX, Token::STRING],

            [<<<'REGEX'
            /^'[^'\\]*(?:\\.[^'\\]*)*'/
            REGEX, Token::CHAR],

            // Expressions
            [self::PATTERN_EEL_EXPRESSION, Token::EEL_EXPRESSION],
            // add content to this match?
            // unclosed multiline strings ...
//            ['/^\${/', 'UNCLOSED_EEL_EXPRESSION'],
        ];
    }


    public function tokenize(string $string): TokenStream
    {
        $this->initialize($string);

        $tokenList = [];

        while ($this->hasMoreTokens()) {
            $tokenList[] = $this->getNextToken();
        }

        $tokenList[] = $this->toToken(Token::EOF);

        return new TokenStream($tokenList);
    }


    /**
     * Initializes the string and SPEC.
     */
    protected function initialize(string $string): void
    {
        $this->SPEC = $this->lexerSpec();
        $this->string = $string;
    }

    /**
     * Obtains next token.
     */
    protected function getNextToken(): ?Token
    {
        // hasMoreTokens
        $string = substr($this->string, $this->cursor);

        foreach ($this->SPEC as $value) {

            list($regexp, $tokenType) = $value;

            $tokenValue = $this->match($regexp, $string);

            if ($tokenValue === null) {
                continue;
            }

            // TODO: MB STRLEN?
            $this->cursor += strlen($tokenValue);

            return $this->toToken($tokenType, $tokenValue);
        }
        throw new \Exception('this doesnt exists ... unexpected token while lexing: ' . $string[0]);
    }

    public function toToken(int $tokenType, $tokenValue = ''): Token
    {
        if (FLOW_APPLICATION_CONTEXT === 'Development') {
            print_r([
                'type' => TOKEN::typeToString($tokenType),
                'value' => $tokenValue,
            ]);
        }
        return new Token($tokenType, $tokenValue, 0, 0, 0);
    }

    protected function match(string $regexp, string $string): ?string
    {
        $isMatch = preg_match($regexp, $string, $matches);
        if ($isMatch === 0)
            return null;
        if ($isMatch === false) {
            throw new \Exception("the regular expression" . $regexp . 'throws an error on this string:' . $string, 1);
        }
        // 0 length match as position marker is not useful
        if (strlen($matches[0]) === 0) {
            if (FLOW_APPLICATION_CONTEXT === 'Development') {
                \Neos\Flow\var_dump($regexp);
                \Neos\Flow\var_dump($string);
            }
            return null;
        }
        return $matches[0];
    }



    /**
     * Whether we still have more tokens.
     */
    protected function hasMoreTokens(): bool
    {
        return $this->cursor < strlen($this->string);
    }
}
