<?php

namespace Neos\Fusion\Core;

/**
 * Lexer class.
 *
 * Lazily pulls a token from a stream.
 */
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


    public $string = '';
    public $cursor = 0;
    protected $SPEC = [];

    /**/

    protected function lexerSpec()
    {
        return [
            // TODO: No null at all.

            // Comments
            ['/^\\/\\/.*/', null /* skip */],
            ['/^#.*/', null /* skip */],

            // TODO: /**/*.fusion is not a comment!
            // lookbehind: (?!\*)
            ['/^\/\\*[\\s\\S]*?\\*\/(?!\*)/', null /* skip */],

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
            ['/^prototype\\s*\(/', 'PROTOTYPE_START'],

            // Symbols
            ['/^;/', ';'],
            ['/^{/', '{'],
            ['/^}/', '}'],
            ['/^\./', '.'],
            ['/^:/', ':'],
            ['/^\)/', ')'],
            ['/^-/', '-'],
            ['/^\\*/', '*'],
            ['/^\//', '/'],
            ['/^_/', '_'],
            ['/^@/', '@'],

            ['/^=/', '='],
            ['/^</', '<'],
            ['/^>/', '>'],

            ['/^[0-9]+/', 'DIGIT'],
            ['/^[a-zA-Z]+/', 'LETTER'],

            // Strings
            // /^"(?:\\"|[^"])*"/
            [<<<'REGEX'
            /^"[^"\\]*(?:\\.[^"\\]*)*"/
            REGEX, 'STRING'],

            // /^'(?:\\'|[^'])*'/
            [<<<'REGEX'
            /^'[^'\\]*(?:\\.[^'\\]*)*'/
            REGEX, 'CHAR'],

            // Expressions
            [self::PATTERN_EEL_EXPRESSION, 'EEL_EXPRESSION'],
            // add content to this match?
            ['/^\${/', 'UNCLOSED_EEL_EXPRESSION'],
        ];
    }


    public function tokenize(string $string): TokenStream
    {
        $this->initialize($string);

        $tokenList = [];

        while ($this->hasMoreTokens()) {
            $tokenList[] = $this->getNextToken();
        }

        $tokenList[] = $this->toToken('EOF');

        return new TokenStream($tokenList);
    }


    /**
     * Initializes the string and SPEC.
     */
    protected function initialize(string $string): void
    {
        $this->SPEC = array_reverse($this->lexerSpec());
        $this->string = $string;
    }

    /**
     * Obtains next token.
     */
    protected function getNextToken(): array
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

            $tokenLength = strlen($tokenValue);

            // match will overrule any prev same len match
            $matchedTokens[$tokenLength] = $this->toToken($tokenType, $tokenValue);
        }

        krsort($matchedTokens);

        foreach ($matchedTokens as $tokenLength => $token) {

            if (FLOW_APPLICATION_CONTEXT === 'Development') {
                print_r($token);
            }

            $this->cursor += $tokenLength;

            if ($token['type'] === null) {
                return $this->getNextToken();
            }

            return $token;
        }

        throw new \Exception('this doesnt exists ... unexpected token while lexing: ' . $string[0]);
    }

    public function toToken($tokenName, $tokenValue = ''): array
    {
        return [
            'type' => $tokenName,
            'value' => $tokenValue,
        ];
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
     * Whether we still have more tokens.
     */
    protected function hasMoreTokens(): bool
    {
        return $this->cursor < strlen($this->string);
    }
}
