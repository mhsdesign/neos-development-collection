<?php

namespace Neos\Fusion\Core;

/*
 * This file is part of the Neos.Fusion package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Fusion;

abstract class AbstractParser
{
    /**
     * @var TokenStream
     */
    protected TokenStream $tokenStream;

    /**
     * get the current token from the token stream
     * @param int $offset
     * @return Token|null
     */
    protected function peek(int $offset = 0): ?Token
    {
        return $this->tokenStream->getTokenAt($this->tokenStream->getPointer() + $offset);
    }

    /**
     * get next token but ignore passed tokens
     * @param array $ignoreTokens
     * @return Token|null
     */
    protected function peekIgnore(array $ignoreTokens): ?Token
    {
        return $this->tokenStream->getNextNotIgnoredToken($this->tokenStream->getPointer(), $ignoreTokens);
    }

    /**
     * consume the current token and move forward
     * @return Token
     * @throws Fusion\Exception
     */
    protected function consume(): Token
    {
        $token = $this->peek();
        if ($token->getType() === Token::EOF) {
            throw new Fusion\Exception("end of input cannot consume token");
        }
        $this->tokenStream->next();
        return $token;
    }

    /**
     * Expects a token of a given type.
     * @param int|null $tokenType
     * @return Token
     * @throws Fusion\Exception
     */
    protected function expect(int $tokenType = null): Token
    {
        $token = $this->peek();
        if ($token->getType() === $tokenType && $token->getType() !== Token::EOF) {
            return $this->consume();
        }

        $atLine = '';

        $lastParseAction = '';
        $backtrace = debug_backtrace(2, 2);
        $lastFunctionName = end($backtrace)['function'] ?? '';
        if (strpos($lastFunctionName, 'parse') === 0) {
            $lastParseAction = ' While parsing: ' . substr($lastFunctionName, 5);
        }

        throw new Fusion\Exception("unexpected token: '" . $token . "' expected: '" . Token::typeToString($tokenType) . "'" . $lastParseAction . $atLine);
    }

    /**
     * Checks, if the token type matches the current, if so consume it and return true.
     * @param int $tokenType
     * @return bool|null
     * @throws Fusion\Exception
     */
    protected function lazyExpect(int $tokenType): ?bool
    {
        if ($this->peek()->getType() === $tokenType) {
            $this->consume();
            return true;
        }
        return false;
    }

    /**
     * consumes tokens of specified types as long as any of them is present and return the concat string value.
     * @param array $tokenTypes
     * @return string|null
     * @throws Fusion\Exception
     */
    protected function lazyExpectTokens(array $tokenTypes): ?string
    {
        $mergedValues = '';
        while (in_array($this->peek()->getType(), $tokenTypes)) {
            $mergedValues .= $this->consume()->getValue();
        }
        if ($mergedValues === '') {
            return null;
        }
        return $mergedValues;
    }
}
