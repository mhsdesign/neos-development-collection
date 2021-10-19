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
class Parser extends AbstractParser
{
    const WHITESPACE = ['SPACE', 'NEWLINE'];
    const COMMENTS = ['//COMMENT', '#COMMENT', '/*COMMENT*/'];

    /**
     * The Fusion object tree, created by this parser.
     * @var array
     */
    protected $objectTree = [];

    /**
     * For nested blocks to determine the prefix
     * @var array
     */
    protected $currentObjectPathStack = [];

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

    protected TokenStream $tokenStream;


    /**
     * Parses the given Fusion source code and returns an object tree
     * as the result.
     *
     * @param string $sourceCode The Fusion source code to parse
     * @param string $contextPathAndFilename An optional path and filename to use as a prefix for inclusion of further Fusion files
     * @param string $contextPathAndFilename An optional path and filename to use as a prefix for inclusion of further Fusion files
     * @param array $objectTreeUntilNow Used internally for keeping track of the built object tree
     * @param boolean $buildPrototypeHierarchy Merge prototype configurations or not. Will be false for includes to only do that once at the end.
     * @return array A Fusion object tree, generated from the source code
     * @throws Fusion\Exception
     * @api
     */
    public function parse($sourceCode, $contextPathAndFilename = null, array $objectTreeUntilNow = [], $buildPrototypeHierarchy = true)
    {
        if (is_string($sourceCode) === false) {
            throw new Fusion\Exception('Cannot parse Fusion - $sourceCode must be of type string!', 1180203775);
        }
        $this->objectTree = $objectTreeUntilNow;
        $this->contextPathAndFilename = $contextPathAndFilename;
        $this->tokenStream = $this->lexer->tokenize($sourceCode);

        $this->parseFusion();

        if ($buildPrototypeHierarchy) {
            FusionAst::buildPrototypeHierarchy($this->objectTree);
        }
        return $this->objectTree;
    }

    protected function parseOptBigSeparation(): void
    {
        while (in_array($this->peek()['type'], [...self::WHITESPACE, ...self::COMMENTS])) {
            $this->consume();
        }
    }


    protected function parseOptSmallSeparation(): void
    {
        while (in_array($this->peek()['type'], ['SPACE', ...self::COMMENTS])) {
            $this->consume();
        }
    }

    /**
     * Main entry point.
     *
     * Program
     *  : StatementList
     *  ;
     *
     */
    protected function parseFusion(): void
    {
        $this->parseStatementList();
    }

    /**
     * StatementList
     *  : Statement
     *  | StatementList Statement
     *  ;
     *
     */
    protected function parseStatementList(callable $stopLookaheadCallback = null): void
    {
        if ($stopLookaheadCallback === null) {
            while ($this->peek()['type'] !== 'EOF') {
                $this->parseStatement();
            }
        } else {
            while ($this->peek()['type'] !== 'EOF' && $stopLookaheadCallback()) {
                $this->parseStatement();
            }
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
    protected function parseStatement(): void
    {
        $this->parseOptBigSeparation();
        switch ($this->peek()['type']) {
            case 'NEWLINE':
            case ';':
                $this->consume();
                return;

            case 'EOF':
                return;

            case 'NAMESPACE':
                $this->parseNamespaceDeclaration();
                return;

            case 'INCLUDE':
                $this->parseIncludeStatement();
                return;

            case 'PROTOTYPE':
               $this->parsePrototypeDeclaration();
                return;

            case 'DELETE':
               $this->parseDeleteStatement();
                return;

            case 'DIGIT':
            case 'LETTER':
            case 'TRUE':
            case 'FALSE':
            case 'NULL':
            case '_':
            case '-':
            case ':':
            case '@':
            case 'PROTOTYPE_START':
            case 'STRING':
            case 'CHAR':
                $this->parseObjectDefinition();
                return;

            case '{':
                throw new \Exception('unexpected block start in statement: ' . json_encode($this->peek()));

            case '}':
                throw new \Exception('unexpected block end while not nested in statement: ' . json_encode($this->peek()));

            default:
                throw new \Exception('unexpected token in statement: ' . json_encode($this->peek()));
        }
    }

    /**
     * PrototypeDeclaration
     *  : PROTOTYPE FusionObjectName LazyBlockStatement
     *  | PROTOTYPE FusionObjectName EXTENDS FusionObjectName LazyBlockStatement
     *  ;
     *
     */
    protected function parsePrototypeDeclaration()
    {
        $this->expect('PROTOTYPE');

        $currentPathPrefix = $this->getCurrentObjectPathPrefix();

        $currentPath = [...$currentPathPrefix, '__prototypes', $this->parseFusionObjectName()];

        if ($this->peek()['type'] === 'SPACE' && $this->peek(1)['type'] === 'EXTENDS') {
            $this->consume();
            $this->consume();

            $extendObjectPath = [...$currentPathPrefix, '__prototypes', $this->parseFusionObjectName()];

            if ($this->pathsAreBothPrototype($currentPath, $extendObjectPath)) {
                $this->inheritPrototypeInObjectTree($currentPath, $extendObjectPath);
            } else {
                throw new \Exception("one of the paths is not a prototype.");
            }
        }

        if ($this->isStartOfBlockStatement()) {
            $this->parseBlockStatement($currentPath);
        } else {
            $this->parseEndOfStatement();
        }
    }

    protected function isStartOfBlockStatement(): bool
    {
        // using here self::IGNORE_WHITESPACE will discard any newlines, which are needed in EndOfStatement
        return $this->peekIgnore(self::WHITESPACE)['type'] === '{';
    }

    /**
     * DeleteStatement
     *  : DELETE AbsoluteObjectPath
     *  ;
     *
     */
    protected function parseDeleteStatement()
    {
        $this->expect('DELETE');
        $currentPath = $this->parseObjectPathAssignment();
        $this->removeValueInObjectTree($currentPath);
    }

    /**
     * ObjectDefinition
     *  : ObjectPath BlockStatement?
     *  | ObjectPath ObjectOperator EndOfStatement
     *  | ObjectPath ObjectOperator BlockStatement?
     *  ;
     *
     */
    protected function parseObjectDefinition(): void
    {
        $path = $this->parseObjectPath($this->getCurrentObjectPathPrefix());

        if ($this->isStartOfBlockStatement()) {
            $this->parseBlockStatement($path);
            return;
        }

        $this->parseOptSmallSeparation();
        $this->parseObjectOperation($path);

        if ($this->isStartOfBlockStatement()) {
            $this->parseBlockStatement($path);
        } else {
            $this->parseEndOfStatement();
        }
    }

    /**
     * FusionObjectNamePart
     *  : LETTER
     *  | DIGIT
     *  | .
     *  | TRUE
     *  | FALSE
     *  | NULL
     *  ;
     */
    protected function parseFusionObjectNamePart(): string
    {
        $value = $this->consumeValueWhileInArray(['LETTER', 'DIGIT', '.', 'TRUE', 'FALSE', 'NULL']);
        if ($value === null) {
            throw new \Exception('Expected FusionObjectNamePart but got' . json_encode($this->peek()));
        }
        return $value;
    }



    protected function parseFilePattern()
    {
        $value = $this->consumeValueWhileInArray(['DIGIT', ':', '*', '-', '_', '/', '.', 'LETTER', 'TRUE', 'FALSE', 'NULL', '/*COMMENT*/', '#//COMMENT']);
        if ($value === null) {
            throw new \Exception('Expected FilePattern but got' . json_encode($this->peek()));
        }
        return $value;
    }

    /**
     * NamespaceDeclaration
     *  : NAMESPACE V:OBJECTNAMEPART = V:OBJECTNAMEPART
     *  ;
     *
     * Parses a namespace declaration and stores the result in the namespace registry.
     *
     */
    protected function parseNamespaceDeclaration(): void
    {

        try {
            $this->expect('NAMESPACE');

            $this->parseOptSmallSeparation();
            $namespaceAlias = $this->parseFusionObjectNamePart();

            $this->parseOptSmallSeparation();
            $this->expect('=');

            $this->parseOptSmallSeparation();
            $namespacePackageKey = $this->parseFusionObjectNamePart();

        } catch (\Exception $e) {
            throw new Fusion\Exception('Invalid namespace declaration "' . $namespaceDeclaration . '"' . $this->renderCurrentFileAndLineInformation(), 1180547190);
        }

        $this->setObjectTypeNamespace($namespaceAlias, $namespacePackageKey);

        $this->parseEndOfStatement();
    }

    /**
     * IncludeStatement
     *  :
     */
    protected function parseIncludeStatement()
    {
        $this->expect('INCLUDE');

        $this->parseOptSmallSeparation();

        switch ($this->peek()['type']) {
            case 'STRING':
            case 'CHAR':
                $filePattern = $this->parseLiteral();
                break;
            default:
                $filePattern = $this->parseFilePattern();
        }

        $this->parseEndOfStatement();
        \Neos\Flow\var_dump($filePattern);

//        $parser = new Parser();

//        if (strpos($filePattern, 'resource://') !== 0) {
//            // Resolve relative paths
//            if ($this->contextPathAndFilename !== null) {
//                $filePattern = dirname($this->contextPathAndFilename) . '/' . $filePattern;
//            } else {
//                throw new Fusion\Exception('Relative file inclusions are only possible if a context path and filename has been passed as second argument to parse()' . $this->renderCurrentFileAndLineInformation(), 1329806940);
//            }
//        }
//
//        // (?<path>[^*]*)\*\*/\*(.*?\.(?<extension>.*))?
//        // Match recursive wildcard globbing "**/*"
//        if (preg_match('#([^*]*)\*\*/\*#', $filePattern, $matches) === 1) {
//            $basePath = $matches['1'];
//            if (!is_dir($basePath)) {
//                throw new Fusion\Exception(sprintf('The path %s does not point to a directory.', $basePath) . $this->renderCurrentFileAndLineInformation(), 1415033179);
//            }
//            $recursiveDirectoryIterator = new \RecursiveDirectoryIterator($basePath);
//            $iterator = new \RecursiveIteratorIterator($recursiveDirectoryIterator);
//            // Match simple wildcard globbing "*"
//        } elseif (preg_match('#([^*]*)\*#', $filePattern, $matches) === 1) {
//            $basePath = $matches['1'];
//            if (!is_dir($basePath)) {
//                throw new Fusion\Exception(sprintf('The path %s does not point to a directory.', $basePath) . $this->renderCurrentFileAndLineInformation(), 1415033180);
//            }
//            $iterator = new \DirectoryIterator($basePath);
//        }
//        // If iterator is set it means we're doing globbing
//        if (isset($iterator)) {
//            foreach ($iterator as $fileInfo) {
//                $pathAndFilename = $fileInfo->getPathname();
//                if ($fileInfo->getExtension() === 'fusion') {
//                    // Check if not trying to recursively include the current file via globbing
//                    if (stat($pathAndFilename) !== stat($this->contextPathAndFilename)) {
//                        if (!is_readable($pathAndFilename)) {
//                            throw new Fusion\Exception(sprintf('Could not include Fusion file "%s"', $pathAndFilename) . $this->renderCurrentFileAndLineInformation(), 1347977018);
//                        }
//                        $this->objectTree = $parser->parse(file_get_contents($pathAndFilename), $pathAndFilename, $this->objectTree, false);
//                    }
//                }
//            }
//        } else {
//            if (!is_readable($filePattern)) {
//                throw new Fusion\Exception(sprintf('Could not include Fusion file "%s"', $filePattern) . $this->renderCurrentFileAndLineInformation(), 1347977017);
//            }
//            $this->objectTree = $parser->parse(file_get_contents($filePattern), $filePattern, $this->objectTree, false);
//        }
//


    }

    /**
     * EndOfStatement
     *  : EOF
     *  | ;
     *  | NEWLINE
     *  ;
     *
     */
    protected function parseEndOfStatement(): void
    {
        $this->parseOptSmallSeparation();
        switch ($this->peek()['type']){
            case 'EOF':
                return;
            // just as experiment
            case ';':
            case 'NEWLINE':
                $this->consume();
                return;
        }
        throw new \Exception('Expected EndOfStatement but got: ' . json_encode($this->peek()));
    }

    /**
     * BlockStatement:
     *  : { StatementList }
     *  ;
     *
     */
    protected function parseBlockStatement(array $path)
    {
        $this->parseOptBigSeparation();
        $this->expect('{');
        array_push($this->currentObjectPathStack, $path);

        $isNotEndOfBlockStatement = fn():bool => $this->peekIgnore(self::WHITESPACE)['type'] !== '}';
        if ($isNotEndOfBlockStatement()) {
            $this->parseStatementList($isNotEndOfBlockStatement);
        }

        array_pop($this->currentObjectPathStack);
        $this->parseOptBigSeparation();
        $this->expect('}');
    }

    /**
     * AbsoluteObjectPath
     *  : . ObjectPath
     *  | ObjectPath
     *  ;
     *
     */
    protected function parseObjectPathAssignment(array $relativePath = null)
    {
        $objectPathPrefix = [];
        if ($this->lazyExpect('.')) {
            $objectPathPrefix = $relativePath ?? $this->getCurrentObjectPathPrefix();
        }
        return $this->parseObjectPath($objectPathPrefix);
    }

    /**
     * ObjectPath
     *  : PathSegment
     *  | ObjectPath . PathSegment
     *  ;
     *
     */
    protected function parseObjectPath(array $objectPathPrefix = []): array
    {
        $objectPath = $objectPathPrefix;
        do {
            array_push($objectPath, ...$this->parsePathSegment());
        } while ($this->lazyExpect('.'));
        return $objectPath;
    }



    /**
     * PathIdentifier
     *  : LETTER
     *  | DIGIT
     *  | TRUE
     *  | FALSE
     *  | NULL
     *  | :
     *  | -
     *  | _
     *  ;
     */
    protected function parsePathIdentifier(): string
    {
        $value = $this->consumeValueWhileInArray(['DIGIT', ':', '-', '_', 'LETTER', 'TRUE', 'FALSE', 'NULL']);
        if ($value === null) {
            throw new \Exception('PathIdentifier but got' . json_encode($this->peek()));
        }
        return $value;
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
    protected function parsePathSegment(): array
    {
        $token = $this->peek();
        FusionAst::keyIsReservedParseTreeKey($token['value']);

        switch ($token['type']) {
            case 'DIGIT':
            case 'LETTER':
            case 'TRUE':
            case 'FALSE':
            case 'NULL':
            case '_':
            case '-':
            case ':':
                return [$this->parsePathIdentifier()];

            case '@':
                $this->consume();
                return ['__meta', $this->parsePathIdentifier()];

            case 'STRING':
            case 'CHAR':
                /* strip quotes and unescape ... */
                $value = $this->consume()['value'];
                $value = substr($value, 1 , -1);

                if ($value === '') {
                    throw new \Exception("a quoted path must not be empty");
                }

                return [$value];

            case 'PROTOTYPE_START':
                $this->expect('PROTOTYPE_START');
                $name = $this->parseFusionObjectName();
                $this->expect(')');
                return ['__prototypes', $name];

            default:
                throw new \Exception("This Path segment makes no sense: " . $this->peek()['type']);
        }
    }

    /**
     * FusionObjectName
     *  : V:OBJECTNAMEPART
     *  | V:OBJECTNAMEPART : V:OBJECTNAMEPART
     *  ;
     *
     */
    protected function parseFusionObjectName()
    {
        $objectPart = $this->parseFusionObjectNamePart();

        if ($this->lazyExpect(':')) {
            $namespace = $this->objectTypeNamespaces[$objectPart] ?? $objectPart;
            $unqualifiedType = $this->parseFusionObjectNamePart();
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
    protected function parseObjectOperation($currentPath): void
    {
        switch ($this->peek()['type']) {
            case '=':
                $this->consume();
                $this->parseOptSmallSeparation();
                $value = $this->parseValueAssignment();
                $this->setValueInObjectTree($currentPath, $value);
                return;

            case '>':
                $this->consume();
                $this->removeValueInObjectTree($currentPath);
                return;

            case '<':
            case 'EXTENDS':
                $operator = $this->consume()['type'];

                $this->parseOptSmallSeparation();
                $sourcePath = $this->parseObjectPathAssignment(FusionAst::getParentPath($currentPath));

                if ($this->pathsAreBothPrototype($currentPath, $sourcePath)) {
                    $this->inheritPrototypeInObjectTree($currentPath, $sourcePath);
                    return;
                }

                if ($operator === 'EXTENDS') {
                    throw new \Exception("EXTENDS doesnt support he copy operation");
                }

                $this->copyValueInObjectTree($currentPath, $sourcePath);
                return;

            default:
                throw new \Exception("no operation matched token: " . json_encode($this->peek()));
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
    protected function parseValueAssignment()
    {
        switch ($this->peek()['type']) {
            case 'EEL_EXPRESSION':
                return $this->parseEelExpression();

            case 'UNCLOSED_EEL_EXPRESSION':
                // implement as catch if token is ${ or as error visitor?
                // TODO: line info
//                $lol = substr($this->lexer->string, $this->lexer->cursor - 2, 20);
                throw new \Exception('an eel expression starting with: ');

            case 'DSL_EXPRESSION':
                return $this->consume()['value'];

            // decimal ?
            // digit start
            case '-':
            case 'STRING':
            case 'CHAR':
                return $this->parseLiteral();

            case 'LETTER':
                return $this->parseFusionObject();

            case 'FALSE':
            case 'NULL':
            case 'TRUE':
                // it could be a fusion object with the name TRUE:Fusion
                // check if the next token is anything that would lead to that its an object:
                switch ($this->peek(1)['type']){
                    case '.':
                    case ':':
                        return $this->parseFusionObject();
                }
                return $this->parseLiteral();

            case 'DIGIT':
                // we need to chek if its a fusionobject starting with a digit or a real number
                switch ($this->peek(1)['type']){
                    case 'LETTER':
                        return $this->parseFusionObject();
                    case '.':
                        if ($this->peek(2)['type'] === 'LETTER') {
                            return $this->parseFusionObject();
                        }
                }
                return $this->parseLiteral();

            default:
                break;

        }

        throw new \Exception("we dont have this expresssion: ". json_encode($this->peek()));
    }

    /**
     * EelExpression
     *  : EEL_EXPRESSION
     *  ;
     *
     */
    protected function parseEelExpression(): array
    {
        $eelExpression = $this->expect('EEL_EXPRESSION')['value'];
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
    protected function parseFusionObject(): array
    {
        return [
            '__objectType' => $this->parseFusionObjectName(), '__value' => null, '__eelExpression' => null
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
    protected function parseLiteral()
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
            case 'DIGIT':
            case '-':
                return $this->parseNumber();
            case 'STRING':
                $string = $this->consume()['value'];
                $string = substr($string, 1, -1);
                return stripcslashes($string);
            case 'CHAR':
                $char = $this->consume()['value'];
                $char = substr($char, 1, -1);
                // what about a = 'fwef\"wef'
                $char = str_replace([
                    '\\\'',     // a = 'few\'fef'
                    '\\\\',     // a = 'few\\fef'
                ], [
                    '\'',       // a = 'few'fef'
                    '\\'        // a = 'few\fef'
                ], $char);

                return $char;
            default:
                throw new \Exception('we dont support thjis Literal: ' . json_encode($this->peek()));
        }
    }

    /**
     * Number
     *  : -? DIGIT
     *  | -? DIGIT . DIGIT
     *  ;
     *
     */
    protected function parseNumber()
    {
        if ($this->lazyExpect('-')) {
            $int = '-';
        } else {
            $int = '';
        }
        $int .= $this->expect('DIGIT')['value'];
        if ($this->lazyExpect('.')) {
            $decimal = $this->expect('DIGIT')['value'];
            $float = $int . '.' . $decimal;
            return floatval($float);
        } else {
            return intval($int);
        }
    }

    protected function getCurrentObjectPathPrefix(): array
    {
        $lastElementOfStack = end($this->currentObjectPathStack);
        return ($lastElementOfStack !== false) ? $lastElementOfStack : [];
    }

    /**
     * Sets the given alias to the specified namespace.
     *
     * The namespaces defined through this setter or through a "namespace" declaration
     * in one of the Fusions are used to resolve a fully qualified Fusion
     * object name while parsing Fusion code.
     *
     * The alias is the handle by which the namespace can be referred to.
     * The namespace is, by convention, a package key which must correspond to a
     * namespace used in the prototype definitions for Fusion object types.
     *
     * The special alias "default" is used as a fallback for resolution of unqualified
     * Fusion object types.
     *
     * @param string $alias An alias for the given namespace, for example "neos"
     * @param string $namespace The namespace, for example "Neos.Neos"
     * @return void
     * @throws Fusion\Exception
     * @api
     */
    public function setObjectTypeNamespace($alias, $namespace)
    {
        if (is_string($alias) === false) {
            throw new Fusion\Exception('The alias of a namespace must be valid string!' . $this->renderCurrentFileAndLineInformation(), 1180600696);
        }
        if (is_string($namespace) === false) {
            throw new Fusion\Exception('The namespace must be of type string!' . $this->renderCurrentFileAndLineInformation(), 1180600697);
        }
        $this->objectTypeNamespaces[$alias] = $namespace;
    }


    protected function removeValueInObjectTree($targetObjectPath)
    {
        $this->setValueInObjectTree($targetObjectPath, null);
        $this->setValueInObjectTree($targetObjectPath, ['__stopInheritanceChain' => true]);
    }

    protected function copyValueInObjectTree($targetObjectPath, $sourceObjectPath)
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

    public function inheritPrototypeInObjectTree($targetPrototypeObjectPath, $sourcePrototypeObjectPath)
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
