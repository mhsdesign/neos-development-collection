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
class Parser
{
    /**
     * The Fusion object tree, created by this parser.
     * @var array
     */
    protected $objectTree = [];


    protected array $tokenStack = [];

    /**
     * For nested blocks to determine the prefix
     * @var array
     */
    protected $currentObjectPathStack = [];

    /**
     * @Flow\Inject
     * @var Lexer
     */
    protected $lexer;


    /**
     * Namespaces used for resolution of Fusion object names. These namespaces
     * are a mapping from a user defined key (alias) to a package key (the namespace).
     * By convention, the namespace should be a package key, but other strings would
     * be possible, too. Note that, in order to resolve an object type, a prototype
     * with that namespace and name must be defined elsewhere.
     *
     * These namespaces are _not_ used for resolution of processor class names.
     * @var array
     */
    protected $objectTypeNamespaces = [
        'default' => 'Neos.Fusion'
    ];

    /**
     * Parses the given Fusion source code and returns an object tree
     * as the result.
     *
     * @param string $sourceCode The Fusion source code to parse
     * @param string $contextPathAndFilename An optional path and filename to use as a prefix for inclusion of further Fusion files
     * @param array $objectTreeUntilNow Used internally for keeping track of the built object tree
     * @param boolean $buildPrototypeHierarchy Merge prototype configurations or not. Will be false for includes to only do that once at the end.
     * @return array A Fusion object tree, generated from the source code
     * @throws Fusion\Exception
     * @api
     */
    public function parse($sourceCode)
    {
        if (is_string($sourceCode) === false) {
            throw new Fusion\Exception('Cannot parse Fusion - $sourceCode must be of type string!', 1180203775);
        }

        $this->lexer->init($sourceCode);
        $this->Program();

        FusionAst::buildPrototypeHierarchy($this->objectTree);

        return $this->objectTree;
    }

    /**
     * Generate a token via the lexer. It caches the result which will be returned in the future until the token is consumed.
     * If the Lexer set the type to 'NO_TOKEN_FOUND' peek() will ask the lexer again. (Usefull when the Lexer State is changed)
     */
    public function peek(array $virtualTokens = null): array
    {
        // the token stack holds all lexed tokens and those combined to virtual tokens, the first element reset()
        // will hold the latest. The first el will be cleaned with consume();
        if (empty($this->tokenStack)) {
            $this->tokenStack[] = $this->lexer->getNextToken();
        }

        $currentToken = reset($this->tokenStack);

        if ($virtualTokens === null ) {
            return $currentToken;
        }

        foreach ($virtualTokens as $virtualToken) {

            foreach ($virtualToken as $virtualTokenType => $tokenTypesToCombine) {

                if (in_array($currentToken['type'], $tokenTypesToCombine)) {

                    $combinedTokenValue = $currentToken['value'];

                    $lexedToken = $this->lexer->getNextToken();
                    while (in_array($lexedToken['type'], $tokenTypesToCombine)) {
                        $combinedTokenValue .= $lexedToken['value'];
                        $lexedToken = $this->lexer->getNextToken();
                    }

                    // we cobined it remove the current token:
                    array_shift($this->tokenStack);


                    // add token to the token stack in second position than the combined if the getNextToken was not combined.

                    // ignore ws
                    // null => will be "" in array
                    if ($virtualTokenType === 0) {
                        $this->tokenStack[] = $lexedToken;
                        return $this->peek($virtualTokens);
                    }


                    $this->tokenStack[] = [
                        'type' => $virtualTokenType,
                        'value' => $combinedTokenValue
                    ];

                    // the lexed Token was not combined to a $virtualTokenType and thus need to be preserved
                    $this->tokenStack[] = $lexedToken;

                    return reset($this->tokenStack);

                }
                // only first els
                break;
            }

        }
        return $currentToken;
    }

    /**
     * Expects a token of a given type.
     */
    protected function consume(string $tokenType = null, array $virtualTokens = null): array
    {
        $token = $this->peek($virtualTokens);

        if ($tokenType === null)
            $tokenType = $token['type'];

        if ($token['type'] === 'EOF')
            throw new \Error("end of input expected" . $tokenType);

        if ($token['type'] !== $tokenType)
            throw new \Exception("unexpected token: '" . json_encode($token) . "' expected: '" . $tokenType . "'");

        array_shift($this->tokenStack);
        return $token;
    }


    /**
     * Checks, if the token type matches the current, if so consume it and return true.
     */
    protected function lazyConsume($tokenType, $virtualTokens = null): ?bool
    {
        $token = $this->peek($virtualTokens);

        if ($token['type'] === $tokenType) {
            $this->consume();
            return true;
        }
        return false;
    }

    protected function getCurrentObjectPathPrefix(): array
    {
        $lastElementOfStack = end($this->currentObjectPathStack);
        return ($lastElementOfStack !== false) ? $lastElementOfStack : [];
    }


    protected const IGNORE_WHITESPACE = [
        0 => ['SPACE', 'NEWLINE']
    ];
    protected const IGNORE_SPACE = [
        0 => ['SPACE']
    ];
    protected const IGNORE_NEWLINE = [
        0 => ['NEWLINE']
    ];
    protected const VTOKEN_PATHIDENTIFIER = [
        'V:PATHIDENTIFIER' => ['INTEGER', ':', 'a-zA-Z0-9_\-', 'ALPHANUMERIC', 'TRUE', 'FALSE', 'NULL']
    ];

    protected const VTOKEN_OBJECTNAMEPART = [
        'V:OBJECTNAMEPART' => ['ALPHANUMERIC', '.']
    ];


    /**
     * Main entry point.
     *
     * Program
     *  : StatementList
     *  ;
     *
     */
    protected function Program() {
        $this->StatementList();
    }

    /**
     * StatementList
     *  : Statement
     *  | StatementList Statement
     *  ;
     *
     */
    protected function StatementList($stopLookahead = null)
    {
        // TODO: $stopLookahead provide not ws option
        while ($this->peek()['type'] !== 'EOF' && $this->peek([self::IGNORE_WHITESPACE])['type'] !== $stopLookahead) {
            $this->Statement();
        }
    }

    /**
     * Statement
     *  : EmptyStatement
     *  | ClassDeclaration
     *  | DeleteStatement
     *  | ObjectDefinition
     *  ;
     *
     */
    protected function Statement(): void
    {
        switch ($this->peek([self::IGNORE_WHITESPACE, self::VTOKEN_PATHIDENTIFIER])['type']) {
            case 'NEWLINE':
            case ';':
                $this->consume();
                return;

            case 'EOF':
                return;

            case 'PROTOTYPE':
               $this->PrototypeDeclaration();
                return;

            case 'DELETE':
               $this->DeleteStatement();
                return;

            case 'V:PATHIDENTIFIER':
            case '@':
            case 'PROTOTYPE_START':
            case 'STRING':
            case 'CHAR':
                $this->ObjectDefinition();
                return;
            default:
                throw new \Error('unexpected token in statement: ' . json_encode($this->peek()));
        }
    }

    /**
     * PrototypeDeclaration
     *  : PROTOTYPE FusionObjectName LazyBlockStatement
     *  | PROTOTYPE FusionObjectName EXTENDS FusionObjectName LazyBlockStatement
     *  ;
     *
     */
    protected function PrototypeDeclaration()
    {
        $this->consume('PROTOTYPE');

        $currentPathPrefix = $this->getCurrentObjectPathPrefix();

        $currentPath = [...$currentPathPrefix, '__prototypes', $this->FusionObjectName()];

        if ($this->peek([self::IGNORE_SPACE])['type'] === 'EXTENDS') {
            $this->consume();

            $extendObjectPath = [...$currentPathPrefix, '__prototypes', $this->FusionObjectName()];

            if ($this->pathsAreBothPrototype($currentPath, $extendObjectPath)) {
                $this->inheritPrototype($currentPath, $extendObjectPath);
            } else {
                throw new \Error("one of the paths is not a prototype.");
            }
        }

        if ($this->peek([self::IGNORE_SPACE])['type'] === '{') {
            $this->BlockStatement($currentPath);
        } else {
            $this->EndOfStatement();
        }
    }

    /**
     * DeleteStatement
     *  : DELETE AbsoluteObjectPath
     *  ;
     *
     */
    protected function DeleteStatement()
    {
        $this->consume('DELETE');
        $currentPath = $this->AbsoluteObjectPath();
        $this->valueUnAssignment($currentPath);
    }

    /**
     * ObjectDefinition
     *  : ObjectPath BlockStatement?
     *  | ObjectPath ObjectOperator EndOfStatement
     *  | ObjectPath ObjectOperator BlockStatement?
     *  ;
     *
     */
    protected function ObjectDefinition(): void
    {
        $path = $this->ObjectPath($this->getCurrentObjectPathPrefix());

        if ($this->peek([self::IGNORE_SPACE])['type'] === '{') {
            $this->BlockStatement($path);
            return;
        }

        $this->ObjectOperator($path);

        // using here self::IGNORE_WHITESPACE will discard any newlines, which are needed in EndOfStatement
        if ($this->peek([self::IGNORE_SPACE])['type'] === '{') {
            $this->BlockStatement($path);
        } else {
            $this->EndOfStatement();
        }
    }

    /**
     * EndOfStatement
     *  : EOF
     *  | ;
     *  | NEWLINE
     *  ;
     *
     */
    protected function EndOfStatement(): void
    {
        switch ($this->peek([self::IGNORE_SPACE])['type']){
            case 'EOF':
                return;
            // just for experimentation
            case ';':
            case 'NEWLINE':
                $this->consume();
                return;
        }
        throw new \Error('Expected EndOfStatement but got: ' . json_encode($this->peek()));
    }

    /**
     * BlockStatement:
     *  : { StatementList }
     *  ;
     *
     */
    protected function BlockStatement(array $path)
    {
        $this->consume('{');
        array_push($this->currentObjectPathStack, $path);

        if ($this->peek()['type'] !== '}') {
            $this->StatementList('}');
        }

        array_pop($this->currentObjectPathStack);
        $this->consume('}');
    }

    /**
     * AbsoluteObjectPath
     *  : . ObjectPath
     *  | ObjectPath
     *  ;
     *
     */
    protected function AbsoluteObjectPath()
    {
        $objectPathPrefix = [];
        if ($this->lazyConsume('.')) {
            $objectPathPrefix = $this->getCurrentObjectPathPrefix();
        }
        return $this->ObjectPath($objectPathPrefix);
    }

    /**
     * ObjectPath
     *  : PathSegment
     *  | ObjectPath . PathSegment
     *  ;
     *
     */
    protected function ObjectPath(array $objectPathPrefix = []): array
    {
        $objectPath = $objectPathPrefix;

        do {
            array_push($objectPath, ...$this->PathSegment());
        } while ($this->lazyConsume('.'));

        return $objectPath;
    }

    /**
     * PathSegment
     *  : V:PATHIDENTIFIER
     *  | @ V:PATHIDENTIFIER
     *  | STRING
     *  | CHAR
     *  | PROTOTYPE_START FusionObjectName )
     *  ;
     *
     */
    protected function PathSegment(): array
    {
        $token = $this->peek([self::VTOKEN_PATHIDENTIFIER]);

        FusionAst::keyIsReservedParseTreeKey($token['value']);

        switch ($token['type']) {
            case 'V:PATHIDENTIFIER':
                return [$this->consume()['value']];

            case '@':
                $this->consume();
                return ['__meta', $this->consume('V:PATHIDENTIFIER', [self::VTOKEN_PATHIDENTIFIER])['value']];

            case 'STRING':
            case 'CHAR':
                /* strip quotes and unescape ... */
                return [$this->consume()['value']];

            case 'PROTOTYPE_START':
                $this->consume('PROTOTYPE_START');
                $name = $this->FusionObjectName();
                $this->consume(')');
                return ['__prototypes', $name];

            default:
                throw new Exception("This Path segment makes no sense: " . $this->peek()['type']);
        }
    }

    /**
     * FusionObjectName
     *  : V:OBJECTNAMEPART
     *  | V:OBJECTNAMEPART : V:OBJECTNAMEPART
     *  ;
     *
     */
    protected function FusionObjectName()
    {
        $objectPart = $this->consume('V:OBJECTNAMEPART', [self::VTOKEN_OBJECTNAMEPART])['value'];

        if ($this->lazyConsume(':')) {
            $namespace = $this->objectTypeNamespaces[$objectPart] ?? $objectPart;
            $unqualifiedType = $this->consume('V:OBJECTNAMEPART', [self::VTOKEN_OBJECTNAMEPART])['value'];
        } else {
            $namespace = $this->objectTypeNamespaces['default'];
            $unqualifiedType = $objectPart;
        }

        return $namespace . ':' . $unqualifiedType;
    }

    /**
     * ObjectOperator
     *  : = Expression
     *  | >
     *  | < AbsoluteObjectPath
     *  | EXTENDS AbsoluteObjectPath
     *  ;
     *
     */
    protected function ObjectOperator($currentPath): void
    {
        switch ($this->peek()['type']) {
            case '=':
                $this->consume();
                $value = $this->Expression();
                $this->setValueInObjectTree($currentPath, $value);
                return;

            case '>':
                $this->consume();
                $this->valueUnAssignment($currentPath);
                return;

            case '<':
            case 'EXTENDS':
                $operator = $this->consume()['type'];

                $this->peek([self::IGNORE_SPACE]);

                $sourcePath = $this->AbsoluteObjectPath();

                if ($this->pathsAreBothPrototype($currentPath, $sourcePath)) {
                    $this->inheritPrototype($currentPath, $sourcePath);
                    return;
                }

                if ($operator === 'EXTENDS') {
                    throw new Exception("EXTENDS doesnt support he copy operation");
                }

                $this->copyValue($currentPath, $sourcePath);
                return;

            default:
                throw new Exception("no operation matched token: " . json_encode($this->peek()));
        }
    }



    /**
     * Expression
     *  : EelExpression
     *  | UNCLOSED_EEL_EXPRESSION
     *  | DSL_EXPRESSION
     *  | FusionObject
     *  | Literal
     *  ;
     *
     */
    protected function Expression()
    {
        switch ($this->peek([self::IGNORE_SPACE, self::VTOKEN_OBJECTNAMEPART])['type']) {
            case 'EEL_EXPRESSION':
                return $this->EelExpression();

            case 'UNCLOSED_EEL_EXPRESSION':
                // TODO: line info
                $lol = substr($this->lexer->string, $this->lexer->cursor - 2, 20);
                throw new \Error('an eel expression starting with: ' . $lol);

            case 'DSL_EXPRESSION':
                return $this->consume()['value'];

            case 'V:OBJECTNAMEPART':
                return $this->FusionObject();

            // decimal ?
            case 'INTEGER':
            case 'FALSE':
            case 'NULL':
            case 'TRUE':
            case 'STRING':
            case 'CHAR':
                return $this->Literal();

            default:
                throw new \Error("we dont have this expresssion: ". json_encode($this->peek()));
        }
    }

    /**
     * EelExpression
     *  : EEL_EXPRESSION
     *  ;
     *
     */
    protected function EelExpression(): array
    {
        $eelExpression = $this->consume('EEL_EXPRESSION')['value'];
        $eelExpression = substr($eelExpression, 2, -1);
        $eelExpression = str_replace("\n", '', $eelExpression);
        return [
            '__eelExpression' => $eelExpression, '__value' => null, '__objectType' => null
        ];
    }

    /**
     * FusionObject
     *  : FusionObjectName
     *  ;
     *
     */
    protected function FusionObject(): array
    {
        return [
            '__objectType' => $this->FusionObjectName(), '__value' => null, '__eelExpression' => null
        ];
    }

    /**
     * Literal
     *  : FALSE
     *  | TRUE
     *  | NULL
     *  | Number
     *  | STRING
     *  | CHAR
     *  ;
     *
     */
    protected function Literal()
    {
        switch ($this->peek()['type']) {
            case 'FALSE':
                $this->consume();
                return false;
            case 'NULL':
                $this->consume();
                return null;
            case 'TRUE':
                $this->consume();
                return true;
            case 'INTEGER':
                return $this->Number();
            case 'STRING':
                $string = $this->consume()['value'];
                $string = substr($string, 1, -1);
                return stripcslashes($string);
            case 'CHAR':
                $char = $this->consume()['value'];
                $char = substr($char, 1, -1);
                return str_replace("\\'", "'", $char);
            default:
                throw new \Error('we dont support thjis Literal: ' . json_encode($this->peek()));
        }
    }

    /**
     * Number
     *  : -? DIGIT
     *  | -? DIGIT . DIGIT
     *  ;
     *
     */
    protected function Number()
    {
        $int = $this->consume('INTEGER')['value'];
        if ($this->peek()['type'] === 'DECIMAL') {
            $decimal = $this->consume()['value'];
            $float = $int . $decimal;
            return floatval($float);
        } else {
            return intval($int);
        }
    }


    protected function valueUnAssignment($targetObjectPath)
    {
        $this->setValueInObjectTree($targetObjectPath, null);
        $this->setValueInObjectTree($targetObjectPath, ['__stopInheritanceChain' => true]);
    }

    protected function copyValue($targetObjectPath, $sourceObjectPath)
    {
        $originalValue = FusionAst::getValueFromObjectTree($sourceObjectPath, $this->objectTree);
        $value = is_object($originalValue) ? clone $originalValue : $originalValue;

        $this->setValueInObjectTree($targetObjectPath, $value);
    }

    /**
     * Assigns a value to a node or a property in the object tree, specified by the object path array.
     *
     * @param array $objectPathArray The object path, specifying the node / property to set
     * @param mixed $value The value to assign, is a non-array type or an array with __eelExpression etc.
     * @return array The modified object tree
     */
    protected function setValueInObjectTree(array $objectPathArray, $value): array
    {
        return FusionAst::setValueInObjectTree($objectPathArray, $value, $this->objectTree);
    }

    protected function pathsAreBothPrototype(array $targetObjectPath, array $sourceObjectPath): bool
    {
        $targetIsPrototypeDefinition = FusionAst::objectPathIsPrototype($targetObjectPath);
        $sourceIsPrototypeDefinition = FusionAst::objectPathIsPrototype($sourceObjectPath);

        if ($targetIsPrototypeDefinition && $sourceIsPrototypeDefinition) {
            // both are a prototype definition
            return true;
        } elseif ($targetIsPrototypeDefinition || $sourceIsPrototypeDefinition) {
            // Either "source" or "target" are no prototypes. We do not support copying a
            // non-prototype value to a prototype value or vice-versa.
            throw new Fusion\Exception('Tried to parse "' . join('.', $targetObjectPath) . '" < "' . join('.', $sourceObjectPath) . '", however one of the sides is no prototype definition of the form prototype(Foo). It is only allowed to build inheritance chains with prototype objects.' . $this->renderFileStuff(), 1358418015);
        }
        return false;
    }

    public function inheritPrototype($targetPrototypeObjectPath, $sourcePrototypeObjectPath)
    {
        if (count($targetPrototypeObjectPath) === 2 && count($sourcePrototypeObjectPath) === 2) {
            // the path has length 2: this means
            // it must be of the form "prototype(Foo) < prototype(Bar)"
            $targetPrototypeObjectPath[] = '__prototypeObjectName';
            $this->setValueInObjectTree($targetPrototypeObjectPath, end($sourcePrototypeObjectPath));
        } else {
            // Both are prototype definitions, but at least one is nested (f.e. foo.prototype(Bar))
            // Currently, it is not supported to override the prototypical inheritance in
            // parts of the Fusion rendering tree.
            // Although this might work conceptually, it makes reasoning about the prototypical
            // inheritance tree a lot more complex; that's why we forbid it right away.
            // TODO: join($targetPrototypeObjectPath, '.') will have ugly things
            throw new Fusion\Exception('Tried to parse "' . join($targetPrototypeObjectPath, '.') . '" < "' . join($sourcePrototypeObjectPath, '.') . '", however one of the sides is nested (e.g. foo.prototype(Bar)). Setting up prototype inheritance is only supported at the top level: prototype(Foo) < prototype(Bar)' . $this->renderCurrentFileAndLineInformation(), 1358418019);
        }
    }

    protected function whiteSpace(): void
    {
        while ($this->peek()['type'] === 'NEWLINE' || $this->peek()['type'] === 'SPACE') {
            $this->consume();
        }

    }

    protected function getNextTokenAndCombineValue(array $tokenTypesToCombine, string $value): string
    {
        $this->lexedToken = $this->lexer->getNextToken();
        while (in_array($this->lexedToken, $tokenTypesToCombine)) {
            $value .= $this->lexedToken['value'];
        }
        return $value;
    }
}
