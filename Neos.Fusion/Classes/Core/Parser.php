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

/**
 * The Fusion Parser
 *
 * @api
 */
class Parser extends AbstractParser implements ParserInterface
{
    /**
     * Reserved parse tree keys for internal usage.
     *
     * @var array
     */
    public static $reservedParseTreeKeys = ['__meta', '__prototypes', '__stopInheritanceChain', '__prototypeObjectName', '__prototypeChain', '__value', '__objectType', '__eelExpression'];


    /**
     * @Flow\Inject
     * @var DslFactory
     */
    protected $dslFactory;

    /**
     * The Fusion object tree builder, used by this parser.
     * @var AstBuilder
     */
    protected $astBuilder;

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
     * TODO: These namespaces are _not_ used for resolution of processor class names
     * TODO: ? but this works: a.@process.stuff = Value
     * @var array
     */
    protected $objectTypeNamespaces = [
        'default' => 'Neos.Fusion'
    ];

    /**
     * @var string|null
     */
    protected $contextPathAndFilename;


    /**
     * Parses the given Fusion source code and returns an object tree
     * as the result.
     *
     * @param string $sourceCode The Fusion source code to parse
     * @param string $contextPathAndFilename An optional path and filename to use as a prefix for inclusion of further Fusion files
     * @param array|AstBuilder|null $objectTreeUntilNow Used internally for keeping track of the built object tree
     * @param boolean $buildPrototypeHierarchy Merge prototype configurations or not. Will be false for includes to only do that once at the end.
     * @return array A Fusion object tree, generated from the source code
     * @throws Fusion\Exception
     * @api
     */
    public function parse($sourceCode, $contextPathAndFilename = null, $objectTreeUntilNow = null, bool $buildPrototypeHierarchy = true): array
    {
        // TODO remove
//        return (new ParserOld())->parse($sourceCode, $contextPathAndFilename, $objectTreeUntilNow ?? [], $buildPrototypeHierarchy);

        if (is_string($sourceCode) === false) {
            // why not type string $sourceCode in argument ?
            throw new Fusion\Exception('Cannot parse Fusion - $sourceCode must be of type string!', 1180203775);
        }

        if ($objectTreeUntilNow === null) {
            $this->astBuilder = new AstBuilder();
        } elseif (is_array($objectTreeUntilNow)) {
            $this->astBuilder = new AstBuilder();
            $this->astBuilder->setObjectTree($objectTreeUntilNow);
        } elseif ($objectTreeUntilNow instanceof AstBuilder) {
            $this->astBuilder = $objectTreeUntilNow;
        } else {
            throw new Fusion\Exception('Cannot parse Fusion - $objectTreeUntilNow must be of type array or AstBuilder or null');
        }

        // TODO use dependency Injection, but this test doesnt like it Neos.Fusion/Tests/Unit/Core/ParserTest.php
        $this->lexer = new Lexer();

        $this->contextPathAndFilename = $contextPathAndFilename;
        $this->lexer->initialize($sourceCode);

        $this->parseFusion();

        if ($buildPrototypeHierarchy) {
            $this->astBuilder->buildPrototypeHierarchy();
        }
        return $this->astBuilder->getObjectTree();
    }

    /**
     * Fusion
     *  = StatementList
     */
    protected function parseFusion(): void
    {
        try {
            $this->parseStatementList();
        } catch (Fusion\Exception $e) {
            $this->throwError($e->getMessage(), $e->getStatusCode());
        }
    }

    /**
     * StatementList
     *  = __ ( Statement __ )*
     *
     * @param int|null $stopLookahead When this tokenType is encountered the loop will be stopped
     */
    protected function parseStatementList(int $stopLookahead = null): void
    {
        $this->parseBigGap();

         while ($this->accept(Token::EOF) === false
             && ($stopLookahead === null || $this->accept($stopLookahead) === false)) {
             $this->parseStatement();
             $this->parseBigGap();
         }
    }

    /**
     * Statement
     *  = SEMICOLON / NamespaceDeclaration / IncludeStatement / PrototypeDeclaration / UnsetStatement / ObjectDefinition
     */
    protected function parseStatement(): void
    {
        switch (true) {
            case $this->accept(Token::SEMICOLON):
                $this->consume();
                return;

            case $this->accept(Token::NAMESPACE):
                $this->parseNamespaceDeclaration();
                return;

            case $this->accept(Token::INCLUDE):
                $this->parseIncludeStatement();
                return;

            case $this->accept(Token::PROTOTYPE):
                $this->parsePrototypeDeclaration();
                return;

            case $this->accept(Token::UNSET_KEYWORD):
                $this->parseUnsetStatement();
                return;

            case $this->accept(Token::PROTOTYPE_START):
            case $this->accept(Token::OBJECT_PATH_PART):
            case $this->accept(Token::META_PATH_START):
            case $this->accept(Token::STRING):
            case $this->accept(Token::CHAR):
                $this->parseObjectDefinition();
                return;
        }

        $this->throwSyntaxError(ParserException::PARSING_STATEMENT, 1635708717);
    }

    /**
     * PrototypeDeclaration
     *  = PROTOTYPE _ FusionObjectName _ ( EXTENDS _ FusionObjectName _ )? ( __ BlockStatement? / EndOfStatement )
     */
    protected function parsePrototypeDeclaration(): void
    {
        $this->expect(Token::PROTOTYPE);
        $this->parseSmallGap();

        $currentPathPrefix = $this->getCurrentObjectPathPrefix();

        $currentPath = $currentPathPrefix;
        array_push($currentPath, '__prototypes', $this->parseFusionObjectName());

        $this->parseSmallGap();
        switch (true) {
            /**
             * prototype: a extends b
             */
            case $this->accept(Token::EXTENDS):
                $this->consume();
                $this->parseSmallGap();

                $extendObjectPath = $currentPathPrefix;
                array_push($extendObjectPath, '__prototypes', $this->parseFusionObjectName());
                $this->astBuilder->inheritPrototypeInObjectTree($currentPath, $extendObjectPath);
                $this->parseSmallGap();

            /**
             * prototype: a (extends b)? {
             */
            case $this->accept(Token::LBRACE):
                $this->parseBlockStatement($currentPath);
                return;

            /**
             * prototype: a (extends b)?
             * {
             */
            case $this->accept(Token::NEWLINE):
                $this->parseBigGap();
                if ($this->accept(Token::LBRACE)) {
                    $this->parseBlockStatement($currentPath);
                    return;
                }
                return;

            /**
             * prototype: a (extends b)?
             *
             */
            default:
                $this->parseEndOfStatement();
        }
    }


    /**
     *
     * true if block found and parsed
     * false if no block found
     * null if no block found but newlines 'destroyed'
     *
     */
    protected function parseIsBlockStatement(): int
    {
        switch (true) {
            /**
             * direct block start
             *
             * a = Value {
             * }
             */
            case $this->accept(Token::LBRACE):
                return self::PEEK_FOUND_BLOCK;

            /**
             * 'hanging' block start:
             *
             * a = Value
             * {
             * }
             *
             */
            case $this->accept(Token::NEWLINE):
                $this->parseBigGap();
                if ($this->accept(Token::LBRACE)) {
                    return self::PEEK_FOUND_BLOCK;
                }
                return self::PEEK_FOUND_END_OF_STATEMENT;

        }
        return self::PEEK_FOUND_NO_BLOCK;
    }

    /**
     * UnsetStatement
     *  = Token::UNSET_KEYWORD _ AbsoluteObjectPath EndOfStatement
     */
    protected function parseUnsetStatement(): void
    {
        $this->expect(Token::UNSET_KEYWORD);
        $currentPath = $this->parseObjectPathAssignment();
        $this->astBuilder->removeValueInObjectTree($currentPath);
        $this->parseEndOfStatement();
    }


    protected const PEEK_FOUND_NO_BLOCK = 0;
    protected const PEEK_FOUND_BLOCK = 1;
    protected const PEEK_FOUND_END_OF_STATEMENT = 2;


    /**
     * ObjectDefinition
     *  = ObjectPath ( BlockStatement? / ObjectOperator BlockStatement? )
     */

    protected function parseObjectDefinition(): void
    {
        $currentPath = $this->parseObjectPath($this->getCurrentObjectPathPrefix());

        $this->parseSmallGap();

        switch ($this->parseIsBlockStatement()) {
            case self::PEEK_FOUND_BLOCK:
                $this->parseBlockStatement($currentPath);
                return;

            /**
             * path // without anything but space, comments and a newline
             */
            case self::PEEK_FOUND_END_OF_STATEMENT:
                // TODO the internal pointer is already further...
                // TODO join will not undo __prototype or __meta
                $stringPath = join('.', $currentPath);
                $this->throwError("Object path '$stringPath' has no operator or is not a block.", 1635708717);
        }

        switch (true) {
            /**
             * path operation
             */
            case $this->accept(Token::ASSIGNMENT):
            case $this->accept(Token::UNSET):
            case $this->accept(Token::COPY):
            case $this->accept(Token::EXTENDS):
                $this->parseObjectOperation($currentPath);
                // must remove comments here too
                /**
                 * a = "" // fwef
                 * a = "" # fwe
                 * a = "" /* fwef *\/
                 */
                break;

            /**
             * if
             * path !( operation / { /
             * { )
             *
             */
            default:
                $this->throwSyntaxError(ParserException::PARSING_PATH_OR_OPERATOR, 1635708717);
        }

        $this->parseSmallGap();

        switch ($this->parseIsBlockStatement()) {
            case self::PEEK_FOUND_BLOCK:
                $this->parseBlockStatement($currentPath);
                return;

            case self::PEEK_FOUND_END_OF_STATEMENT:
                return;

            default:
                $this->parseEndOfStatement();
        }
    }

    /**
     * Parses a namespace declaration and stores the result in the namespace registry.
     *
     * NamespaceDeclaration
     *  = NAMESPACE ( " NamespaceAssignment " / ' NamespaceAssignment ' / NamespaceAssignment )
     */
    protected function parseNamespaceDeclaration(): void
    {
        try {
            $this->expect(Token::NAMESPACE);
            $this->parseSmallGap();

            switch (true) {
                case $this->accept(Token::DOUBLE_QUOTE):
                case $this->accept(Token::SINGLE_QUOTE):
                    $quoteType = $this->consume()->getType();
                    $this->parseSmallGap();
                    $this->parseNamespaceAssignment();
                    $this->parseSmallGap();
                    $this->expect($quoteType);
                    break;

                default:
                    $this->parseNamespaceAssignment();
                    break;
            }
        } catch (\Exception $e) {
            $this->throwSyntaxError(ParserException::UNEXPECTED_TOKEN_WITH_MESSAGE, 1180547190, 'Invalid namespace declaration: ' . $e->getMessage());
        }
        $this->parseEndOfStatement();
    }

    /**
     * NamespaceAssignment
     *  = FusionObjectNamePart = FusionObjectNamePart
     */
    protected function parseNamespaceAssignment(): void
    {
        $namespaceAlias = $this->expect(Token::OBJECT_TYPE_PART)->getValue();
        $this->parseSmallGap();
        $this->expect(Token::ASSIGNMENT);
        $this->parseSmallGap();
        $namespacePackageKey = $this->expect(Token::OBJECT_TYPE_PART)->getValue();

        $this->setObjectTypeNamespace($namespaceAlias, $namespacePackageKey);
    }


    /**
     * IncludeStatement
     *  = INCLUDE ( STRING / CHAR / FilePattern )
     */
    protected function parseIncludeStatement(): void
    {
        // TODO: test includes
        $this->expect(Token::INCLUDE);
        $this->parseSmallGap();

        switch (true) {
            case $this->accept(Token::STRING):
            case $this->accept(Token::CHAR):
                $filePattern = $this->parseLiteral();
                break;
            case $this->accept(Token::FILE_PATTERN):
                $filePattern = $this->consume()->getValue();
                break;
            default:
                $this->throwSyntaxError(ParserException::UNEXPECTED_TOKEN_WITH_MESSAGE, 1635708717, 'Expected file pattern in quotes or [a-zA-Z0-9.*:/_-]');
        }

        try {
            $this->includeAndParseFilesByPattern($filePattern);
        } catch (Fusion\Exception $e) {
            $this->throwError($e->getMessage(), 1635708717);
        }

        $this->parseEndOfStatement();
    }


    /**
     * Parse an include files by pattern. Currently, we start a new parser object; but we could as well re-use
     * the given one.
     *
     * @param string $filePattern The include-pattern, for example " FooBar" or " resource://....". Can also include wildcard mask for Fusion globbing.
     * @throws Fusion\Exception
     */
    protected function includeAndParseFilesByPattern(string $filePattern): void
    {
        $parser = new Parser();

        // TODO: bug?: using a parser for one pattern means, that if you include all files: '**/*',
        // one could declare a namespace in a earlier parsed fusion file and use this in a later parsed file. This could be hard to follow.
        // is this wanted?

        $filesToInclude = FilePatternResolver::resolveFilesByPattern($filePattern, $this->contextPathAndFilename, '.fusion');
        foreach ($filesToInclude as $file) {
            if (is_readable($file) === false) {
                throw new Fusion\Exception(sprintf('Could not read file "%s" of pattern "%s"', $file, $filePattern), 1347977017);
            }
            // Check if not trying to recursively include the current file via globbing
            if (stat($this->contextPathAndFilename) !== stat($file)) {
                $parser->parse(file_get_contents($file), $file, $this->astBuilder, false);
            }
        }
    }

    /**
     * EndOfStatement
     *  = ( EOF / ; / NEWLINE )
     */
    protected function parseEndOfStatement(): void
    {
        $this->parseSmallGap();

        switch (true){
            case $this->accept(Token::EOF):
                return;
            // just as experiment ;
            case $this->accept(Token::SEMICOLON):
            case $this->accept(Token::NEWLINE):
                $this->consume();
                return;
        }
        $this->throwSyntaxError(ParserException::PARSING_END_OF_STATEMENT, 1635708717);
    }

    /**
     * BlockStatement:
     *  = { StatementList? }
     */
    protected function parseBlockStatement(array $path): void
    {
        $this->expect(Token::LBRACE);
        array_push($this->currentObjectPathStack, $path);

        $this->parseStatementList(Token::RBRACE);

        array_pop($this->currentObjectPathStack);

        try {
            $this->expect(Token::RBRACE);
        } catch (Fusion\Exception $e) {
            $this->throwSyntaxError(ParserException::UNEXPECTED_TOKEN_WITH_MESSAGE, 1635708717, 'Expected "}" as end of the started block.');
        }
    }

    /**
     * AbsoluteObjectPath
     *  = ( . )? ObjectPath
     *
     * @param array $relativePath If a dot is encountered a relative Path will be created. This determines the relation.
     */
    protected function parseObjectPathAssignment(array $relativePath = null): array
    {
        $objectPathPrefix = [];
        if ($this->lazyExpect(Token::DOT)) {
            $objectPathPrefix = $relativePath ?? $this->getCurrentObjectPathPrefix();
        }
        return $this->parseObjectPath($objectPathPrefix);
    }

    /**
     * ObjectPath
     *  = PathSegment ( . PathSegment )*
     *
     * @param array $objectPathPrefix The current base objectpath.
     */
    protected function parseObjectPath(array $objectPathPrefix = []): array
    {
        $objectPath = $objectPathPrefix;
        do {
            array_push($objectPath, ...$this->parsePathSegment());
        } while ($this->lazyExpect(Token::DOT));
        return $objectPath;
    }

    /**
     * PathSegment
     *  = ( PathIdentifier / @ PathIdentifier / STRING / CHAR / PROTOTYPE_START FusionObjectName ) )
     */
    protected function parsePathSegment(): array
    {
        switch (true) {
            case $this->accept(Token::PROTOTYPE_START):
                $this->expect(Token::PROTOTYPE_START);
                $name = $this->parseFusionObjectName();
                $this->expect(Token::RPAREN);
                return ['__prototypes', $name];

            case $this->accept(Token::OBJECT_PATH_PART):
                $value = $this->consume()->getValue();
                $this->astBuilder->throwIfKeyIsReservedParseTreeKey($value);
                return [$value];

            case $this->accept(Token::META_PATH_START):
                $this->consume();
                return ['__meta', $this->expect(Token::OBJECT_PATH_PART)->getValue()];

            case $this->accept(Token::STRING):
            case $this->accept(Token::CHAR):
                $value = $this->parseLiteral();
                if ($value === '') {
                    $this->throwError("A quoted path must not be empty", 1635708717);
                }
                return [$value];
        }
        $this->throwSyntaxError(ParserException::PARSING_PATH_SEGMENT, 1635708755);
    }

    /**
     * FusionObjectName
     *  = FusionObjectNamePart ( : FusionObjectNamePart )?
     */
    protected function parseFusionObjectName(): string
    {
        $objectPart = $this->expect(Token::OBJECT_TYPE_PART)->getValue();

        if ($this->lazyExpect(Token::COLON)) {
            $namespace = $this->objectTypeNamespaces[$objectPart] ?? $objectPart;
            $unqualifiedType = $this->expect(Token::OBJECT_TYPE_PART)->getValue();
        } else {
            $namespace = $this->objectTypeNamespaces['default'];
            $unqualifiedType = $objectPart;
        }

        return $namespace . ':' . $unqualifiedType;
    }

    /**
     * ObjectOperator
     *  = ( Expression / > / < AbsoluteObjectPath / EXTENDS AbsoluteObjectPath )
     * @param array $currentPath The path which will be modified.
     */
    protected function parseObjectOperation(array $currentPath): void
    {
        switch (true) {
            case $this->accept(Token::ASSIGNMENT):
                $this->consume();
                $this->parseSmallGap();
                $value = $this->parseValueAssignment();
                $this->astBuilder->setValueInObjectTree($currentPath, $value);
                return;

            case $this->accept(Token::UNSET):
                $this->consume();
                $this->astBuilder->removeValueInObjectTree($currentPath);
                return;

            case $this->accept(Token::COPY):
            case $this->accept(Token::EXTENDS):
                $operator = $this->consume()->getType();

                $this->parseSmallGap();
                $sourcePath = $this->parseObjectPathAssignment($this->astBuilder->getParentPath($currentPath));

                // both are a prototype definition
                if ($this->astBuilder->countPrototypePaths($currentPath, $sourcePath) === 2) {
                    try {
                        $this->astBuilder->inheritPrototypeInObjectTree($currentPath, $sourcePath);
                    } catch (Fusion\Exception $e) {
                        $this->throwError($e->getMessage(), $e->getStatusCode());
                    }
                    return;
                } elseif ($this->astBuilder->countPrototypePaths($currentPath, $sourcePath) === 1) {
                    // Only one of "source" or "target" is a prototype. We do not support copying a
                    // non-prototype value to a prototype value or vice-versa.
                    // TODO: delay throw since there might be syntax errors causing this.
                    $this->throwError("Cannot inherit, when one of the sides is no prototype definition of the form prototype(Foo). It is only allowed to build inheritance chains with prototype objects.", 1358418015);
                }

                if ($operator === Token::EXTENDS) {
                    $this->throwError("The operator 'extends' doesnt support the copy path operation", 1635708717);
                }

                $this->astBuilder->copyValueInObjectTree($currentPath, $sourcePath);
                return;

            default:
                $this->throwSyntaxError(ParserException::UNEXPECTED_TOKEN_WITH_MESSAGE, 1635708717, "Expected path operator." );
        }
    }

    /**
     * Expression
     *  = ( EelExpression / DSL_EXPRESSION / FusionObject / Literal )
     */
    protected function parseValueAssignment()
    {
        switch (true) {
            // watch out for the order, its regex matching and first one wins.
            case $this->accept(Token::FALSE_VALUE):
            case $this->accept(Token::NULL_VALUE):
            case $this->accept(Token::TRUE_VALUE):
            case $this->accept(Token::INTEGER):
            case $this->accept(Token::FLOAT):
            case $this->accept(Token::STRING):
            case $this->accept(Token::CHAR):
                return $this->parseLiteral();

            case $this->accept(Token::DSL_EXPRESSION_START):
                return $this->parseDslExpression();

            // Needs to come later, since it's too greedy.
            case $this->accept(Token::OBJECT_TYPE_PART):
                return $this->parseFusionObject();

            case $this->accept(Token::EEL_EXPRESSION):
                return $this->parseEelExpression();
        }
        $this->throwSyntaxError(ParserException::PARSING_VALUE_ASSIGNMENT, 1635708717);
    }

    protected function parseDslExpression()
    {
        $dslIdentifier = $this->expect(Token::DSL_EXPRESSION_START)->getValue();
        try {
            $dslCode = $this->expect(Token::DSL_EXPRESSION_CONTENT)->getValue();
        } catch (Fusion\Exception $e) {
            $this->throwSyntaxError(ParserException::PARSING_DSL_EXPRESSION, 1490714685);
        }
        $dslCode = substr($dslCode, 1, -1);
        return $this->invokeAndParseDsl($dslIdentifier, $dslCode);
    }

    /**
     * @param string $identifier
     * @param $code
     * @return mixed
     * @throws Exception
     * @throws Fusion\Exception
     */
    protected function invokeAndParseDsl(string $identifier, $code)
    {
        $dslObject = $this->dslFactory->create($identifier);
        try {
            $transpiledFusion = $dslObject->transpile($code);
        } catch (\Exception $e) {
            // TODO: AFX line preview is not helping... the real error happens somewhere in between.
            /*
               |
            32 | `
               |
            Error during AFX-parsing: <p> Opening-bracket for closing of tag "p" expected.
             */
            // convert all exceptions from dsl transpilation to fusion exception and add file and line info
            $this->throwError($e->getMessage(), 1180600696);
        }

        $parser = new Parser();
        // transfer current namespaces to new parser
        foreach ($this->objectTypeNamespaces as $key => $objectTypeNamespace) {
            $parser->setObjectTypeNamespace($key, $objectTypeNamespace);
        }
        $temporaryAst = $parser->parse('value = ' . $transpiledFusion);
        return $temporaryAst['value'];
    }


    /**
     * EelExpression
     *  = EEL_EXPRESSION
     */
    protected function parseEelExpression(): array
    {
        $eelExpression = $this->expect(Token::EEL_EXPRESSION)->getValue();
        $eelExpression = substr($eelExpression, 2, -1);
        // multiline trim
        /**
         * TODO
         * ${"
         * afwfe
         * fwefe
         * v
         * "}
         *
         * will become afwfefwefev
         */
        $eelExpression = str_replace("\n", '', $eelExpression);
        return [
            '__eelExpression' => $eelExpression, '__value' => null, '__objectType' => null
        ];
    }

    /**
     * FusionObject
     *  = FusionObjectName
     */
    protected function parseFusionObject(): array
    {
        return [
            '__objectType' => $this->parseFusionObjectName(), '__value' => null, '__eelExpression' => null
        ];
    }

    /**
     * Literal
     *  = ( FALSE / TRUE / NULL / Number / STRING / CHAR )
     *
     * @return mixed
     */
    protected function parseLiteral()
    {
        // TODO decimal with dot starting .123?
        switch (true) {
            case $this->accept(Token::TRUE_VALUE):
                $this->consume();
                return true;
            case $this->accept(Token::FALSE_VALUE):
                $this->consume();
                return false;
            case $this->accept(Token::NULL_VALUE):
                $this->consume();
                return null;

            case $this->accept(Token::STRING):
                $string = $this->consume()->getValue();
                return self::stringUnquote($string);
            case $this->accept(Token::CHAR):
                $char = $this->consume()->getValue();
                return self::charUnquote($char);

            case $this->accept(Token::INTEGER):
                return (int)$this->consume()->getValue();
            case $this->accept(Token::FLOAT):
                return (float)$this->consume()->getValue();

        }
        $this->throwSyntaxError(ParserException::UNEXPECTED_TOKEN_WITH_MESSAGE, 1635708717, 'Expected literal');
    }

    protected static function charUnquote($char): string
    {
        $char = substr($char, 1, -1);
        return stripslashes($char);
    }

    protected static function stringUnquote($string): string
    {
        $string = substr($string, 1, -1);
        return stripcslashes($string);
    }

    protected function getCurrentObjectPathPrefix(): array
    {
        $lastElementOfStack = end($this->currentObjectPathStack);
        return ($lastElementOfStack === false) ? [] : $lastElementOfStack;
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
    public function setObjectTypeNamespace($alias, $namespace): void
    {
        if (is_string($alias) === false) {
            throw new Fusion\Exception('The alias of a namespace must be valid string!', 1180600696);
        }
        if (is_string($namespace) === false) {
            throw new Fusion\Exception('The namespace must be of type string!', 1180600697);
        }
        $this->objectTypeNamespaces[$alias] = $namespace;
    }
}
