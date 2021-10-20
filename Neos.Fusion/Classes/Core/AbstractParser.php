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
     * @return array|null
     */
    protected function peek(int $offset = 0): ?array
    {
        return $this->tokenStream->getTokenAt($this->tokenStream->getPointer() + $offset);
    }

    /**
     * get next token but ignore passed tokens
     * @param array $ignoreTokens
     * @return array|null
     */
    protected function peekIgnore(array $ignoreTokens): ?array
    {
        return $this->tokenStream->getNextNotIgnoredToken($this->tokenStream->getPointer(), $ignoreTokens);
    }

    /**
     * consume the current token and move forward
     * @return array
     * @throws Fusion\Exception
     */
    protected function consume(): array
    {
        $token = $this->peek();
        if ($token['type'] === 'EOF') {
            throw new Fusion\Exception("end of input cannot consume token");
        }
        $this->tokenStream->next();
        return $token;
    }

    /**
     * Expects a token of a given type.
     * @param string|null $tokenType
     * @return array
     * @throws Fusion\Exception
     */
    protected function expect(string $tokenType = null): array
    {
        $token = $this->peek();
        if ($token['type'] === $tokenType && $token['type'] !== 'EOF') {
            return $this->consume();
        }
        throw new Fusion\Exception("unexpected token: '" . json_encode($token) . "' expected: '" . $tokenType . "'");
    }

    /**
     * Checks, if the token type matches the current, if so consume it and return true.
     * @param $tokenType
     * @return bool|null
     * @throws Fusion\Exception
     */
    protected function lazyExpect($tokenType): ?bool
    {
        if ($this->peek()['type'] === $tokenType) {
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
        while (in_array($this->peek()['type'], $tokenTypes)) {
            $mergedValues .= $this->consume()['value'];
        }
        if ($mergedValues === '') {
            return null;
        }
        return $mergedValues;
    }
}
