<?php

namespace Neos\Fusion\Core;

/**
 * Lexer class.
 *
 * Lazily pulls a token from a stream.
 */
class Lexer
{
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

    public $string = '';
    public $cursor = 0;
    protected $SPEC = [];

    protected function lexerSpec()
    {
        return [
            ['/^\\/\\/.*/', null /* skip */],
            ['/^#.*/', null /* skip */],
            ['/^\/\\*[\\s\\S]*?\\*\//', null /* skip */],

            ['/^[\n\r]+/', 'NEWLINE'],
            ['/^[ \t]+/', 'SPACE'],

            // Keywords
            ['/^(true|TRUE)\b/', 'TRUE'],
            ['/^(false|FALSE)\b/', 'FALSE'],
            ['/^(null|NULL)\b/', 'NULL'],
            ['/^delete\\s*:\\s+/', 'DELETE'],
            ['/^extends\\s*:\\s+/', 'EXTENDS'],
            ['/^prototype\\s*:\\s+/', 'PROTOTYPE'],
            ['/^include\\s*:/', 'INCLUDE'],
            ['/^namespace\\s*:/', 'NAMESPACE'],

            // Symbols
            // Semicolon as delimiter with optional WhiteSpace and NewLines
            ['/^;/', ';'],
            ['/^{/', '{'],
            ['/^}/', '}'],
            ['/^\./', '.'],
            ['/^:/', ':'],
            ['/^\)/', ')'],
            ['/^@/', '@'],


            // Operators
            ['/^=/', '='],
            ['/^</', '<'],
            ['/^>/', '>'],


            ['/^-?[0-9]+/', 'INTEGER'],
            ['/^\\.[0-9]+/', 'DECIMAL'],

            // Path Segments
            ['/^prototype\\s*\(/', 'PROTOTYPE_START'],


            // colons in object name
            ['/^[a-zA-Z0-9]+/', 'ALPHANUMERIC'],

            // should be good to go as al
            // dont end with : - would solve exentensibility and that fuison object problem
            ['/^[a-zA-Z0-9_\-]+/', 'a-zA-Z0-9_\-'],


            // dot cannot be present here!
            ['/^[a-zA-Z0-9*\\/_-]+/', 'FILEPATH'],



            ['/^"(?:\\\"|[^"])+"/', 'STRING'],
            ['/^\'(?:\\\\\'|[^\'])+\'/', 'CHAR'],



            [self::PATTERN_EEL_EXPRESSION, 'EEL_EXPRESSION'],
            ['/^\${/', 'UNCLOSED_EEL_EXPRESSION'], // the order is lower than the first which would mach a whole eel expression
        ];
    }

    /**
     * Initializes the string and SPEC.
     */
    public function init(string $string): void
    {
        $this->SPEC = array_reverse($this->lexerSpec());
        $this->string = $string;
    }

    /**
     * Obtains next token.
     */
    public function getNextToken(): array
    {
        if ($this->hasMoreTokens() === false) {
            return $this->toToken('EOF');
        }

        $string = substr($this->string, $this->cursor);

        $matchedTokens = [];

        foreach ($this->SPEC as $value) {

            list($regexp, $tokenType) = $value;

            $tokenValue = $this->match($regexp, $string);

            if ($tokenValue === null) {
                continue;
            }

            if ($tokenType === null) {
                return $this->getNextToken();
            }

            $tokenLength = strlen($tokenValue);

            // match will overrule any prev same len match
            $matchedTokens[$tokenLength] = $this->toToken($tokenType, $tokenValue);
        }

        krsort($matchedTokens);

        foreach ($matchedTokens as $tokenLength => $token) {
            print_r($token);
            $this->cursor += $tokenLength;
            return $token;
        }

        throw new \Error('this doesnt exists ... unexpected token while lexing: ' . $string[0]);
    }

    public function toToken($tokenName, $tokenValue = null): array
    {
        return [
            'type' => $tokenName,
            'value' => $tokenValue,
        ];
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
        return $matches[0];
    }

    /**
     * If the lexer reached EOF.
     */
    protected function isEOF(): bool
    {
        return $this->cursor === strlen($this->string);
    }
}
