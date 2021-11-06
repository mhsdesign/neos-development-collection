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
     * @var Lexer
     */
    protected $lexer;

    /**
     * Consume the current token.
     * Can only consume if accept was called before.
     *
     * @return Token
     * @throws Fusion\Exception
     */
    protected function consume(): Token
    {
        return  $this->lexer->consumeLookahead();
    }

    /**
     * Accepts a token of a given type.
     * The Lexer will look up the regex for the token and try to match it on the current string.
     * First match wins.
     *
     * @param int $tokenType
     * @return bool
     * @throws \Exception
     */
    protected function accept(int $tokenType): bool
    {
        if ($this->lexer->getLookahead() === null) {
            $this->lexer->tryGenerateLookahead($tokenType);
        }
        if ($this->lexer->getLookahead() === null) {
            return false;
        }
        return $this->lexer->getLookahead()->getType() === $tokenType;
    }

    /**
     * Expects a token of a given type.
     * The Lexer will look up the regex for the token and try to match it on the current string.
     * First match wins.
     *
     * @param int $tokenType
     * @return Token
     * @throws Fusion\Exception
     */
    protected function expect(int $tokenType): Token
    {
        if ($this->lexer->getLookahead() === null) {
            $this->lexer->tryGenerateLookahead($tokenType);
        }

        if ($this->lexer->getLookahead() !== null && $this->lexer->getLookahead()->getType() === $tokenType) {
            return  $this->lexer->consumeLookahead();
        }

        throw new Fusion\Exception('Expected token: "' . Token::typeToString($tokenType) . '"', 1635708717);
    }

    /**
     * Checks, if the token type matches the current, if so consume it and return true.
     * @param int $tokenType
     * @return bool|null
     * @throws Fusion\Exception
     */
    protected function lazyExpect(int $tokenType): ?bool
    {
        if ($this->accept($tokenType)) {
            $this->consume();
            return true;
        }
        return false;
    }

    /**
     * OptionalBigGap
     *  = ( NEWLINE / OptionalSmallGap )*
     */
    protected function lazyBigGap(): void
    {
        while (true) {
            switch (true) {
                case $this->accept(Token::SPACE):
                case $this->accept(Token::NEWLINE):
                case $this->accept(Token::SLASH_COMMENT):
                case $this->accept(Token::HASH_COMMENT):
                case $this->accept(Token::MULTILINE_COMMENT):
                    $this->consume();
                    break;

                default:
                    return;
            }
        }
    }

    /**
     * OptionalSmallGap
     *  = ( SPACE / SLASH_COMMENT / HASH_COMMENT / MULTILINE_COMMENT )*
     */
    protected function lazySmallGap(): void
    {
        while (true) {
            switch (true) {
                case $this->accept(Token::SPACE):
                case $this->accept(Token::SLASH_COMMENT):
                case $this->accept(Token::HASH_COMMENT):
                case $this->accept(Token::MULTILINE_COMMENT):
                    $this->consume();
                    break;

                default:
                    return;
            }
        }
    }

    /**
     * Get the current file, cursor and the code the lexer is using.
     *
     * @param int $offset
     * @return array{string, string, int}
     */
    protected function getParsingContext(int $offset = 0): array
    {
        $cursor = $this->lexer->getCursor();
        $code = $this->lexer->getCode();

        if ($offset !== 0) {
            $cursor += $offset;
            if ($cursor < 0 || $cursor > strlen($code)) {
                throw new \LogicException("Offset of '$offset' cannot be applied, as its out of range.", 1635790851);
            }
        }

        return [$this->contextPathAndFilename, $code, $cursor];
    }
}
