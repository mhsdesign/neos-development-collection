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
    protected array $lookahead;

    /**
     * @Flow\Inject
     * @var Lexer
     */
    protected $lexer;

    protected TokenStream $tokenStream;



// make peek recursive otput for:  few454fewf f
// other tokens pushed into line need to be used secods time of peek

//Flow Variable Dump
//array(2)
//integer 0 => array(2)
//string "type" (4) => string "V:LOL" (5)
//string "value" (5) => string "few454fewf::::::tokenStack" (26)
//integer 1 => array(2)
//string "type" (4) => string "SPACE" (5)
//string "value" (5) => string " " (1)
//
//Flow Variable Dump
//array(3)
//integer 0 => array(2)
//string "type" (4) => string "SPACE" (5)
//string "value" (5) => string " " (1)
//integer 1 => array(2)
//string "type" (4) => string "Moin" (4)
//string "value" (5) => string "few454fewf::::::tokenStack::::::tokenStack" (42)
//integer 2 => array(2)
//string "type" (4) => string "LETTER" (6)
//string "value" (5) => string "f" (1)
//
//Array
//(
//[type] => SPACE
//[value] =>
//)

    protected function mergeNextToken(array $virtualTokens): void
    {
        // the token stack holds all lexed tokens and those combined to virtual tokens, the first element reset()
        // will hold the latest. The first el will be cleaned with consume();

        $mergeStatus = $this->tokenStream->mergeToNextToken($virtualTokens);

        if ($mergeStatus === true) {
            $this->lookahead = $this->tokenStream->current();
        } elseif ($mergeStatus === null) {
            $this->mergeNextToken($virtualTokens);
        }

        $this->lookahead = $this->tokenStream->current();

    }

    protected function peekIgnore(array $ignoreTokens)
    {

        return $this->tokenStream->getNextNotIgnoredToken($this->tokenStream->getPointer(), $ignoreTokens);


//        $isdigit('/^-$/', '?', '/^DIGIT$/', ['/^\\./', '/^DIGIT$/'], '?');
//        $isObject('/^(DIGIT|LETTER|\\.)/', '+', [':', '/^(DIGIT|LETTER|\\.)/', '/^\\+/'], '?');
        // 56346546.545.

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
            throw new \Error("end of input expected" . $tokenType);
        }

        $this->tokenStream->next();
        $this->lookahead = $this->tokenStream->current();
        return $token;
    }

    /**
     * Expects a token of a given type.
     */
    protected function expect(string $tokenType = null): array
    {
        if ($this->peek()['type'] === $tokenType) {
            return $this->consume();
        }

        throw new \Exception("unexpected token: '" . json_encode($this->peek()) . "' expected: '" . $tokenType . "'");
    }


    /**
     * Checks, if the token type matches the current, if so consume it and return true.
     */
    protected function lazyExpect($tokenType, $virtualTokens = null): ?bool
    {
        if ($virtualTokens !== null) {
            $this->mergeNextToken($virtualTokens);
        }

        $token = $this->peek();

        if ($token['type'] === $tokenType) {
            $this->consume();
            return true;
        }
        return false;
    }
}
