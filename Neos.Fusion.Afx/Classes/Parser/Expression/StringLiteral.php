<?php
declare(strict_types=1);

namespace Neos\Fusion\Afx\Parser\Expression;

/*
 * This file is part of the Neos.Fusion.Afx package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Fusion\Afx\Parser\AfxParserException;
use Neos\Fusion\Afx\Parser\Lexer;

/**
 * Class StringLiteral
 * @package Neos\Fusion\Afx\Parser\Expression
 */
class StringLiteral
{
    /**
     * @param Lexer $lexer
     * @return string
     * @throws AfxParserException
     */
    public static function parse(Lexer $lexer, bool $keepQuotesAndEscapes = false): string
    {
        if ($lexer->isSingleQuote() === false
            && $lexer->isDoubleQuote() === false) {
            throw new AfxParserException("Unquoted String literal", 1557860514);
        }

        $openingQuoteSign = $lexer->consume();
        $contents = $keepQuotesAndEscapes ? $openingQuoteSign : '';

        while (true) {
            switch (true) {
                case $lexer->isEnd():
                    throw new AfxParserException("Unfinished string literal '$contents'", 1557860504);

                case $lexer->peek() === $openingQuoteSign:
                    $lexer->consume();
                    return $keepQuotesAndEscapes ? $contents . $openingQuoteSign : $contents;

                case $lexer->isBackSlash():
                    $lexer->consume();
                    if ($keepQuotesAndEscapes) {
                        $contents .= '\\';
                    }
                    $contents .= $lexer->consume();
                    break;

                default:
                    $contents .= $lexer->consume();
            }
        }
    }
}
