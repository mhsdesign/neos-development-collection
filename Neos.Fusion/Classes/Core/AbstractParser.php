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
use Neos\Fusion\Exception;

//set_time_limit(2);

/**
 * The Fusion Parser
 *
 * @api
 */
// class Parser implements ParserInterface
abstract class AbstractParser
{
    /**
     * @Flow\Inject
     * @var Lexer
     */
    protected $lexer;

    protected TokenStream $tokenStream;

    protected function consumeValueWhileInArray(array $tokenTypes): ?string
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

    protected function peekIgnore(array $ignoreTokens)
    {
        return $this->tokenStream->getNextNotIgnoredToken($this->tokenStream->getPointer(), $ignoreTokens);
    }

    /**
     * Generate a token via the lexer. It caches the result which will be returned in the future until the token is consumed.
     * If the Lexer set the type to 'NO_TOKEN_FOUND' peek() will ask the lexer again. (Usefull when the Lexer State is changed)
     */
    public function peek(int $offset = 0): ?array
    {
        return $this->tokenStream->getTokenAt($this->tokenStream->getPointer() + $offset);
    }


    protected function consume(): array
    {
        $token = $this->peek();

        if ($token['type'] === 'EOF') {
            throw new \Error("end of input");
        }
        $this->tokenStream->next();
        return $token;
    }

    /**
     * Expects a token of a given type.
     */
    protected function expect(string $tokenType = null): array
    {
        $token = $this->peek();
        if ($token['type'] === $tokenType && $token['type'] !== 'EOF') {
            return $this->consume();
        }
        throw new \Exception("unexpected token: '" . json_encode($token) . "' expected: '" . $tokenType . "'");
    }

    /**
     * Checks, if the token type matches the current, if so consume it and return true.
     */
    protected function lazyExpect($tokenType): ?bool
    {
        $token = $this->peek();

        if ($token['type'] === $tokenType) {
            $this->consume();
            return true;
        }
        return false;
    }
}
