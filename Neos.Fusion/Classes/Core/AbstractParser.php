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
     */
    protected function accept(int $tokenType): bool
    {
        $token = $this->lexer->getCachedLookaheadOrTryToGenerateLookaheadForTokenAndGetLookahead($tokenType);
        if ($token === null) {
            return false;
        }
        return $token->getType() === $tokenType;
    }

    protected function acceptOneOf(...$tokenTypes): ?int
    {
        return $this->lexer->getLookaheadOrTryMatchOneOfTokens($tokenTypes);
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
        $token = $this->lexer->getCachedLookaheadOrTryToGenerateLookaheadForTokenAndGetLookahead($tokenType);
        if ($token === null || $token->getType() !== $tokenType) {
            throw new Fusion\Exception('Expected token: "' . Token::typeToString($tokenType) . '"', 1635708717);
        }
        return $this->lexer->consumeLookahead();
    }

    /**
     * Checks, if the token type matches the current, if so consume it and return true.
     * @param int $tokenType
     * @return bool|null
     */
    protected function lazyExpect(int $tokenType): ?bool
    {
        $token = $this->lexer->getCachedLookaheadOrTryToGenerateLookaheadForTokenAndGetLookahead($tokenType);
        if ($token === null || $token->getType() !== $tokenType) {
            return false;
        }
        $this->lexer->consumeLookahead();
        return true;
    }

    /**
     * OptionalBigGap
     *  = ( NEWLINE / OptionalSmallGap )*
     */
    protected function lazyBigGap(): void
    {
        $this->lexer->consumeGreedyOneOrMultipleOfTokens([
            Token::SPACE,
            Token::NEWLINE,
            Token::SLASH_COMMENT,
            Token::HASH_COMMENT,
            Token::MULTILINE_COMMENT
        ]);
    }

    /**
     * OptionalSmallGap
     *  = ( SPACE / SLASH_COMMENT / HASH_COMMENT / MULTILINE_COMMENT )*
     */
    protected function lazySmallGap(): void
    {
        $this->lexer->consumeGreedyOneOrMultipleOfTokens([
            Token::SPACE,
            Token::SLASH_COMMENT,
            Token::HASH_COMMENT,
            Token::MULTILINE_COMMENT
        ]);
    }

    /**
     * Get the current file, cursor and the code the lexer is using.
     *
     * @param int $offset
     * @return array{?string, string, int}
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
