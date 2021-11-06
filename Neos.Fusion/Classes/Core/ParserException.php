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

/**
 * An exception thrown by Fusion processors or generally in the Fusion context.
 *
 */
class ParserException extends \Exception
{

    public const UNEXPECTED_TOKEN_WITH_MESSAGE = -1;
    public const ONLY_MESSAGE = 0;

    public const PARSING_PATH_OR_OPERATOR = 1;
    public const PARSING_PATH_SEGMENT = 2;
    public const PARSING_VALUE_ASSIGNMENT = 3;
    public const PARSING_DSL_EXPRESSION = 4;
    public const PARSING_END_OF_STATEMENT = 5;
    public const PARSING_STATEMENT = 6;

    private $fileName;
    private $lineNumber;
    private $columnNumber;
    private $currentLine;
    private $parsingAction;
    private $isEof;
    private $optMessage;
    /**
     * @var string
     */
    private $firstPartOfLine;
    /**
     * @var string
     */
    private $lastPartOfLine;
    /**
     * @var string
     */
    private $nextChar;
    /**
     * @var string
     */
    private $lastChar;


    /**
     * feature: allow accept or expect to accept string like "." and "-" for better error messages.
     */

    /**
     * got will be a fake token till the next line end
     * on error no token is allowed to be in cache
     */
    public function __construct($fileName, $code, $cursor, $isEof, $parsingAction, $optMessage, $optMessageCode)
    {
        $this->fileName = $fileName ?? '<input>';

        $this->parsingAction = $parsingAction;
        $this->isEof = $isEof;
        $this->optMessage = $optMessage;

        $this->initializeCurrentCodeInformation($code, $cursor);

        parent::__construct($this->__toString(), $optMessageCode ?? 0);
    }

    protected function messageParsingPathOrOperator(): string
    {
        if (preg_match('/.*namespace\s*$/', $this->firstPartOfLine) === 1) {
            return 'Did you meant to add a namespace declaration? (namespace: Alias=Vendor)';
        }
        if (preg_match('/.*include\s*$/', $this->firstPartOfLine) === 1) {
            return 'Did you meant to include a Fusion file? (include: "./FileName.fusion")';
        }

        if ($this->lastChar === ' ') {
            // it's an operator since there was space
            if ($this->nextChar === '.') {
                return "Nested paths, seperated by '.' cannot contain spaces.";
            }
            return "Unknown operator starting with '$this->nextChar'. (Or you have unwanted spaces in you object path)";
        }
        if ($this->nextChar === '(') {
            return "A normal path segment cannot contain '('. Did you meant to declare a prototype: 'prototype()'?";
        }
        return "Unknown operator or path segment at '$this->nextChar'. Paths can contain only alphanumeric and ':-' - otherwise quote them.";
    }

    protected function messageParsingPathSegment(): string
    {
        if ($this->nextChar === '"' || $this->nextChar === '\'') {
            return "A quoted object path starting with $this->nextChar was not closed";
        }
        return "Unexpected '$this->nextChar'. Expected an object path like alphanumeric[:-], prototype(...), quoted paths, or meta path starting with @";
    }

    protected function messageParsingValueAssignment(): string
    {
        switch ($this->nextChar) {
            case '':
                return 'No value specified in assignment.';
            case '"':
                return 'Unclosed quoted string.';
            case '\'':
                return 'Unclosed char sequence.';
            case '`':
                return 'Template literals without DSL identifier are not supported.';
            case '$':
                if ($this->lastPartOfLine[1] ?? '' === '{') {
                    return 'Unclosed eel expression';
                }
                return 'Did you meant to start an eel expression "${...}"?';
        }
        return "Unexpected character in assignment starting with '$this->nextChar'";
    }

    protected function messageParsingStatement(): string
    {
        switch ($this->nextChar) {
            case '/':
                if ($this->lastPartOfLine[1] ?? '' === '*') {
                    return 'Unclosed comment.';
                }
                return 'Unexpected single /';
            case '"':
            case '\'':
                return 'Unclosed quoted path.';
            case '{':
                return 'Unexpected block start out of context. Check the number of your curly braces.';
            case '}':
                return 'Unexpected block end out of context. Check the number of your curly braces.';
        }
        return "Unexpected character in statement: '$this->nextChar'. A valid object path is alphanumeric[:-], prototype(...), quoted, or a meta path starting with @";
    }

    public function getMessageByAction(): string
    {
        switch ($this->parsingAction) {
            case self::ONLY_MESSAGE:
                return $this->optMessage;
            case self::PARSING_PATH_OR_OPERATOR:
                return $this->messageParsingPathOrOperator();
            case self::PARSING_PATH_SEGMENT:
                return $this->messageParsingPathSegment();
             case self::PARSING_VALUE_ASSIGNMENT:
                return $this->messageParsingValueAssignment();
            case self::PARSING_DSL_EXPRESSION:
                return "A dsl expression starting with '$this->lastPartOfLine' was not closed.";
            case self::PARSING_END_OF_STATEMENT:
                // TODO: Unclosed comment.
                return "Expected the end of a statement but found '$this->lastPartOfLine'.";
            case self::UNEXPECTED_TOKEN_WITH_MESSAGE:
                return "Unexpected '$this->nextChar' - " . $this->optMessage;
            case self::PARSING_STATEMENT:
                return $this->messageParsingStatement();
        }
        return "Unexpected '$this->nextChar'";
    }

    protected function initializeCurrentCodeInformation(string $code, int $cursor): void
    {
        $length = strlen($code);
        $newLinesFound = 0;
        $newLineToCursor = '';
        $cursorToNewLine = '';

        for ($i = 0; $i < $length; $i++) {
            $char = $code[$i];

            if ($i >= $cursor) {
                // what about cursor *is* \n ?
                if ($char === "\n") {
                    break;
                }
                $cursorToNewLine .= $char;

            } else {
                if ($char === "\n") {
                    ++$newLinesFound;
                    $newLineToCursor = '';
                } else {
                    $newLineToCursor .= $char;
                }
            }
        }

        $this->firstPartOfLine = $newLineToCursor;
        $this->lastPartOfLine = $cursorToNewLine;

        if ($this->isEof) {
            $this->nextChar = 'EOF';
        } else {
            if (function_exists('mb_substr')) {
                $this->nextChar = mb_substr($cursorToNewLine, 0, 1);
            } else {
                $this->nextChar = substr($cursorToNewLine, 0, 1) ?: '';
            }
        }

        if (function_exists('mb_substr')) {
            $this->lastChar = mb_substr($newLineToCursor, -1);
        } else {
            $this->lastChar = substr($newLineToCursor,  -1) ?: '';
        }

        $this->lineNumber = $newLinesFound + 1;
        if (function_exists('mb_strlen')) {
            $this->columnNumber = mb_strlen($newLineToCursor);
        } else {
            $this->columnNumber = strlen($newLineToCursor);
        }
        $this->currentLine = $newLineToCursor . $cursorToNewLine;
    }

    public function __toString()
    {

        $line = $this->renderErrorLine();
        $message = $this->getMessageByAction();

        return $line . "\n" . $message;
    }


    public function renderErrorLine()
    {
        if ($this->isEof === false) {
            $firstChar = $this->nextChar;
            $unexpected = $firstChar;
//            $unexpected = Ascii::printable($firstChar);
            $body = $this->currentLine;
        } else {
            $unexpected = $body = '<EOF>';
        }

        $lineNumber = $this->lineNumber;

        $spaceIndent = str_repeat('_', strlen((string)$lineNumber));

        // +1 to get the next char
        $columnNumber = $this->columnNumber + 1;
        $position = $this->fileName . ':' . $lineNumber . ':' . $columnNumber;

        $spaceToArrow = str_repeat('_', $this->columnNumber);

        $body = preg_replace('/\s(?=\s)/', '_', $body);
        $bodyLine = strlen($body) > 80 ? (substr($body, 0, 77) . "...") : $body;

        $arrowColumn = '';
        if ($this->parsingAction !== self::ONLY_MESSAGE) {
            $arrowColumn = "$spaceToArrow^— column $columnNumber";
        }

        return <<<MESSAGE
            $position
            {$spaceIndent} |
            {$lineNumber} | $bodyLine
            {$spaceIndent} | $arrowColumn
            MESSAGE;
    }
}
