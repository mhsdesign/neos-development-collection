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
use Neos\Fusion\Annotations\LexerMode;
use Neos\Fusion\Exception;

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

    protected $current_lookahead = false;

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
     * a stack of lexing state ids, to tell the lexer how to lex
     */
    protected $lexingStates = [];

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
    public function peek(): array
    {
        if ($this->current_lookahead === false || $this->current_lookahead['type'] === 'NO_TOKEN_FOUND') {
            $this->current_lookahead = $this->lexer->getNextToken();

            if ($this->current_lookahead['type'] !== 'NO_TOKEN_FOUND') {
                echo $this->current_lookahead['type'];
                echo ":";
                echo json_encode($this->current_lookahead['value']);
                echo "->";
            }
        }
        return $this->current_lookahead;
    }

    /**
     * Expects a token of a given type.
     */
    protected function consume(string $tokenType = null): array
    {
        $token = $this->peek();

        if ($tokenType === null)
            $tokenType = $token['type'];

        if ($token['type'] === 'EOF')
            throw new \Error("end of input expected" . $tokenType);

        if ($token['type'] !== $tokenType)
            throw new \Exception("unexpected token: '" . json_encode($token) . "' expected: '" . $tokenType . "'");

        $this->current_lookahead = false;
        return $token;
    }


    /**
     * Checks, if the token type matches the current, if so consume it and return true.
     */
    protected function lazyConsume($tokenType): bool
    {
        if ($this->peek()['type'] === $tokenType) {
            $this->consume();
            return true;
        }
        return false;
    }

    /**
     * removes the current token and rewinds the cursor of the Lexer.
     */
    protected function resetToken()
    {
        if ($this->current_lookahead !== false) {
            $this->lexer->setCursorToLastTokenStart();
            $this->current_lookahead = false;
        }
        // nothing to do.
    }

    /**
     * sets next Lexer State. Called via AOP before on methods annotated with LexerMode.
     * @param int $stateId
     */
    public function pushState(int $stateId)
    {
        // if there was a peek() and thus a token generated which was not consumed this token need to be deleted if switched to a new mode.
//        if ($this->current_lookahead !== false && $stateId !== end($this->lexingStates)) {
            $this->resetToken();
//        }
        $this->lexer->setLexerStateForNextLexing($stateId);
        $this->lexingStates[] = $stateId;
    }

    /**
     * unsets current Lexer State. Called via AOP after return of a method annotated with LexerMode.
     */
    public function popState()
    {
        $currentStateId = array_pop($this->lexingStates);
        $lastStateId = end($this->lexingStates);
        // if there was a peek() and thus a token generated which was not consumed this token need to be deleted if switched to a new mode.
//        if ($this->current_lookahead !== false && $currentStateId !== $lastStateId) {
            $this->resetToken();
//        }
        $this->lexer->setLexerStateForNextLexing($lastStateId);
    }

    protected function getCurrentObjectPathPrefix(): array
    {
        $lastElementOfStack = end($this->currentObjectPathStack);
        return ($lastElementOfStack !== false) ? $lastElementOfStack : [];
    }


    /**
     * Main entry point.
     *
     * Program
     *  : StatementList
     *  ;
     *
     * @LexerMode(Lexer::StateDefault)
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
        while ($this->peek()['type'] !== 'EOF' && $this->peek()['type'] !== $stopLookahead) {
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
     * @LexerMode(Lexer::StateDefault)
     */
    protected function Statement(): void
    {

//        $this->resetToken();
        \Neos\Flow\var_dump($this->current_lookahead);

//        $this->resetToken();

//        $this->pushState(0);

        $a = $this->peek()['type'];

//        \Neos\Flow\var_dump(end($this->lexingStates));
////        $this->popState();
//        \Neos\Flow\var_dump($a);

        switch ($a) {
            case 'NEWLINE':
            case ';':
                $this->consume();
                return;

            case 'PROTOTYPE':
               $this->PrototypeDeclaration();
                return;

            case 'DELETE':
               $this->DeleteStatement();
                return;

            case 'NO_TOKEN_FOUND':
            default:
                $this->ObjectDefinition();
        }
    }

    /**
     * PrototypeDeclaration
     *  : PROTOTYPE FusionObjectName LazyBlockStatement
     *  | PROTOTYPE FusionObjectName EXTENDS FusionObjectName LazyBlockStatement
     *  ;
     *
     * @LexerMode(Lexer::StateDefault)
     */
    protected function PrototypeDeclaration()
    {
        $currentPathPrefix = $this->getCurrentObjectPathPrefix();

        $this->consume('PROTOTYPE');

        $currentPath = [...$currentPathPrefix, '__prototypes', $this->FusionObjectName()];

        // additional checks.. ???
        if ($this->peek()['type'] === 'EXTENDS') {
            $this->consume();

            $extendObjectPath = [...$currentPathPrefix, '__prototypes', $this->FusionObjectName()];

            $this->prototypeInheritance($currentPath, $extendObjectPath);
        }

        $this->LazyBlockStatement($currentPath);
    }


    /**
     * OptBlockStatement
     *  : BlockStatement
     *  | null
     *  ;
     *
     * @LexerMode(Lexer::StateObjectPath)
     */
    protected function LazyBlockStatement($path): bool
    {
        if ($this->peek()['type'] === '{') {
            $this->BlockStatement($path);
            return true;
        }
        return false;
    }


    /**
     * DeleteStatement
     *  : DELETE ObjectPath
     *  ;
     *
     * @LexerMode(Lexer::StateDefault)
     */
    protected function DeleteStatement()
    {
        $this->consume('DELETE');
        $currentPath = $this->AbsoluteObjectPath();
        $this->valueUnAssignment($currentPath);
    }

    /**
     * ObjectDefinition
     *  : ObjectPath LazyBlockStatement
     *  | ObjectPath ObjectOperator EndOfStatement
     *  | ObjectPath ObjectOperator LazyBlockStatement
     *  ;
     *
     */
    protected function ObjectDefinition(): void
    {
        $path = $this->ObjectPath($this->getCurrentObjectPathPrefix());

        if ($this->LazyBlockStatement($path)) {
            return;
        }

        $this->ObjectOperator($path);

        if ($this->LazyBlockStatement($path) === false) {
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
     * @LexerMode(Lexer::StateDefault)
     */
    protected function EndOfStatement(): void
    {
        switch ($this->peek()['type']){
            case 'EOF':
                return;
            case ';':
                $this->consume();
                return;
        }
        $this->consume('NEWLINE');
    }

    /**
     * BlockStatement:
     *  : { StatementList }
     *  ;
     *
     * @LexerMode(Lexer::StateObjectPath)
     */
    protected function BlockStatement(array $path)
    {
        $this->consume('{');
        array_push($this->currentObjectPathStack, $path);

        if ($this->peek()['type'] !== '}') {
            // TODO: does stopLookahead even work, since the Lexer::StateDefault doesnt has this token...
            $this->StatementList('}');
        }

        $this->consume('}');
        array_pop($this->currentObjectPathStack);
    }

    /**
     * AbsoluteObjectPath
     *  : . ObjectPath
     *  | ObjectPath
     *  ;
     *
     * @LexerMode(Lexer::StateObjectPath)
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
     * @LexerMode(Lexer::StateObjectPath)
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
     *  : IDENTIFIER
     *  | METAPROPERTY
     *  | STRING
     *  | PROTOTYPE_START FusionObjectName )
     *  ;
     *
     * @LexerMode(Lexer::StateObjectPath)
     */
    protected function PathSegment(): array
    {
        FusionAst::keyIsReservedParseTreeKey($this->peek()['value']);

        switch ($this->peek()['type']) {
            case 'METAPROPERTY':
                $keyName = $this->consume()['value'];
                return ['__meta', $keyName];

            case 'IDENTIFIER':
            case 'STRING':
            case 'CHAR':
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
     *  : OBJECT_NAME
     *  ;
     *
     * @LexerMode(Lexer::StateFusionObject)
     */
    protected function FusionObjectName()
    {
        $objectType = $this->consume('OBJECT_NAME')['value'];
        $objectTypeParts = explode(':', $objectType);

        if (count($objectTypeParts) === 1) {
            $fullyQualifiedObjectType = $this->objectTypeNamespaces['default'] . ':' . $objectTypeParts[0];
        } elseif (isset($this->objectTypeNamespaces[$objectTypeParts[0]])) {
            $fullyQualifiedObjectType = $this->objectTypeNamespaces[$objectTypeParts[0]] . ':' . $objectTypeParts[1];
        } else {
            $fullyQualifiedObjectType = $objectType;
        }
        return $fullyQualifiedObjectType;
    }

    /**
     * ObjectOperator
     *  : = Expression
     *  | >
     *  | < AbsoluteObjectPath
     *  | EXTENDS AbsoluteObjectPath
     *  ;
     *
     * @LexerMode(Lexer::StateObjectPath)
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

                $sourceObjectPath = $this->AbsoluteObjectPath();

                if ($this->pathsCanPrototypeInherit($currentPath, $sourceObjectPath)) {
                    $this->prototypeInheritance($currentPath, $sourceObjectPath);
                    return;
                }

                if ($operator === 'EXTENDS') {
                    throw new Exception("EXTENDS doesnt support he copy operation");
                }

                $this->copyValue($currentPath, $sourceObjectPath);
                return;

            default:
                throw new Exception("no operation matched token: " . json_encode($this->peek()));
        }
    }


    /**
     * Expression
     *  : EEL_EXPRESSION
     *  | UNCLOSED_EEL_EXPRESSION
     *  | DSL_EXPRESSION
     *  | OBJECT_NAME
     *  | Literal
     *  ;
     *
     * @LexerMode(Lexer::StateValueAssigment)
     */
    protected function Expression()
    {
        switch ($this->peek()['type']) {
            case 'EEL_EXPRESSION':
                return $this->EelExpression();

            case 'UNCLOSED_EEL_EXPRESSION':
                // TODO: line info
                $lol = substr($this->lexer->string, $this->lexer->cursor - 2, 20);
                throw new Error('an eel expression starting with: ' . $lol);

            case 'DSL_EXPRESSION':
                return $this->consume()['value'];

            case 'OBJECT_NAME':
                return $this->FusionObject();

            default:
                return $this->Literal();
        }
    }

    /**
     * EelExpression
     *  : EEL_EXPRESSION
     *  ;
     *
     * @LexerMode(Lexer::StateValueAssigment)
     */
    protected function EelExpression(): array
    {
        $eelExpression = $this->consume('EEL_EXPRESSION')['value'];
        return [
            '__eelExpression' => $eelExpression, '__value' => null, '__objectType' => null
        ];
    }

    /**
     * FusionObject
     *  : FusionObjectName
     *  ;
     *
     * @LexerMode(Lexer::StateFusionObject)
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
     *  | NUMBER
     *  | STRING
     *  | CHAR
     *  ;
     *
     * @LexerMode(Lexer::StateValueAssigment)
     */
    protected function Literal()
    {
        switch ($this->peek()['type']) {
            case 'FALSE':
            case 'NULL':
            case 'TRUE':
                return $this->consume()['value'];
            case 'NUMBER':
                return $this->consume()['value'];
            case 'STRING':
                $string = $this->consume()['value'];
                return stripcslashes($string);
            case 'CHAR':
                $char = $this->consume()['value'];
                return str_replace("\\'", "'", $char);
            default:
                throw new \Error('we dont support thjis Literal: ' . json_encode($this->peek()));
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

    protected function pathsCanPrototypeInherit(array $targetObjectPath, array $sourceObjectPath): bool
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

    public function prototypeInheritance($targetPrototypeObjectPath, $sourcePrototypeObjectPath)
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
}
