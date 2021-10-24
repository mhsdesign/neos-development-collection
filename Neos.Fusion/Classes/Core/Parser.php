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
    const WHITESPACE = [Token::SPACE, Token::NEWLINE];
    const COMMENTS = [Token::SLASH_COMMENT, Token::HASH_COMMENT, Token::MULTILINE_COMMENT];

    /**
     * @Flow\Inject
     * @var Lexer
     */
    protected Lexer $lexer;

    protected TokenStream $tokenStream;

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
     * TODO: These namespaces are _not_ used for resolution of processor class names? but this works: a.@process.stuff = Value
     * @var array
     */
    protected $objectTypeNamespaces = [
        'default' => 'Neos.Fusion'
    ];

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

    /**
     * Parses the given Fusion source code and returns an object tree
     * as the result.
     *
     * @param string $sourceCode The Fusion source code to parse
     * @param string $contextPathAndFilename An optional path and filename to use as a prefix for inclusion of further Fusion files
     * @param array|AstBuilder $objectTreeUntilNow Used internally for keeping track of the built object tree
     * @param boolean $buildPrototypeHierarchy Merge prototype configurations or not. Will be false for includes to only do that once at the end.
     * @return array A Fusion object tree, generated from the source code
     * @throws Fusion\Exception
     * @api
     */
    public function parse($sourceCode, $contextPathAndFilename = null, $objectTreeUntilNow = null, $buildPrototypeHierarchy = true): array
    {
        if (is_string($sourceCode) === false) {
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

        $this->contextPathAndFilename = $contextPathAndFilename;
        $this->tokenStream = $this->lexer->tokenize($sourceCode);

        $this->parseFusion();

        if ($buildPrototypeHierarchy) {
            $this->astBuilder->buildPrototypeHierarchy();
        }
        return $this->astBuilder->getObjectTree();
    }

    /**
     * BigGap
     *  : SPACE
     *  | NEWLINE
     *  | //COMMENT
     *  | #COMMENT
     *  | /*COMMENT*\/
     *  ;
     */
    protected function parseBigGap(): void
    {
        while (in_array($this->peek()->getType(), [...self::WHITESPACE, ...self::COMMENTS])) {
            $this->consume();
        }
    }

    /**
     * SmallGap
     *  : SPACE
     *  | //COMMENT
     *  | #COMMENT
     *  | /*COMMENT*\/
     *  ;
     */
    protected function parseSmallGap(): void
    {
        while (in_array($this->peek()->getType(), [Token::SPACE, ...self::COMMENTS])) {
            $this->consume();
        }
    }

    /**
     * Program
     *  : StatementList
     *  ;
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
            while ($this->peek()->getType() !== Token::EOF) {
                $this->parseStatement();
            }
        } else {
            while ($this->peek()->getType() !== Token::EOF && $stopLookaheadCallback()) {
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
        $this->parseBigGap();
        switch ($this->peek()->getType()) {
            case Token::NEWLINE:
            case Token::SEMICOLON:
                $this->consume();
                return;

            case Token::EOF:
                return;

            case Token::NAMESPACE:
                $this->parseNamespaceDeclaration();
                return;

            case Token::INCLUDE:
                $this->parseIncludeStatement();
                return;

            case Token::PROTOTYPE:
               $this->parsePrototypeDeclaration();
                return;

            case Token::DELETE:
               $this->parseDeleteStatement();
                return;

            case Token::DIGIT:
            case Token::LETTER:
            case Token::TRUE:
            case Token::FALSE:
            case Token::NULL:
            case Token::MINUS:
            case Token::UNDERSCORE:
            case Token::COLON:
            case Token::AT:
            case Token::PROTOTYPE_START:
            case Token::STRING:
            case Token::CHAR:
                $this->parseObjectDefinition();
                return;

            case Token::LBRACE:
                throw new \Exception('unexpected block start in statement: ' . $this->peek());

            case Token::RBRACE:
                throw new \Exception('unexpected block end while not nested in statement: ' . $this->peek());

            default:
                throw new \Exception('unexpected token in statement: ' . $this->peek());
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
        $this->expect(Token::PROTOTYPE);

        $currentPathPrefix = $this->getCurrentObjectPathPrefix();

        $currentPath = [...$currentPathPrefix, '__prototypes', $this->parseFusionObjectName()];

        if ($this->peek()->getType() === Token::SPACE && $this->peek(1)->getType() === Token::EXTENDS) {
            $this->consume();
            $this->consume();

            $extendObjectPath = [...$currentPathPrefix, '__prototypes', $this->parseFusionObjectName()];

            $this->astBuilder->inheritPrototypeInObjectTree($currentPath, $extendObjectPath);
        }

        if ($this->isStartOfBlockStatement()) {
            $this->parseBlockStatement($currentPath);
        } else {
            $this->parseEndOfStatement();
        }
    }

    protected function isStartOfBlockStatement(): bool
    {
        return $this->peekIgnore(self::WHITESPACE)->getType() === Token::LBRACE;
    }

    /**
     * DeleteStatement
     *  : DELETE AbsoluteObjectPath
     *  ;
     *
     */
    protected function parseDeleteStatement()
    {
        $this->expect(Token::DELETE);
        $currentPath = $this->parseObjectPathAssignment();
        $this->astBuilder->removeValueInObjectTree($currentPath);
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
        $this->parseSmallGap();
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
        $value = $this->lazyExpectTokens([Token::LETTER, Token::DIGIT, Token::DOT, Token::TRUE, Token::FALSE, Token::NULL]);
        if ($value === null) {
            throw new \Exception('Expected FusionObjectNamePart but got' . $this->peek());
        }
        return $value;
    }

    /**
     * FilePattern
     *  : LETTER
     *  | DIGIT
     *  | :
     *  | *
     *  | -
     *  | _
     *  | /
     *  | .
     *  | TRUE
     *  | FALSE
     *  | NULL
     *  | /*COMMENT*\/
     *  | #COMMENT
     *  ;
     */
    protected function parseFilePattern()
    {
        // TODO: include stuff/**/*.fusion -> will be lexed to 'LETTER /*COMMENT*/ * . LETTER' this seems off.
        // would also apply for a case with include #file.fusion, which will be a #COMMENT
        $value = $this->lazyExpectTokens([Token::DIGIT, Token::LETTER, Token::COLON, Token::STAR, Token::MINUS, Token::UNDERSCORE, Token::SLASH, Token::DOT, Token::TRUE, Token::FALSE, Token::NULL, Token::MULTILINE_COMMENT, Token::HASH_COMMENT]);
        if ($value === null) {
            throw new \Exception('Expected FilePattern but got' . $this->peek());
        }
        return $value;
    }

    /**
     * Parses a namespace declaration and stores the result in the namespace registry.
     *
     * NamespaceDeclaration
     *  : NAMESPACE FusionObjectNamePart = FusionObjectNamePart
     *  ;
     *
     */
    protected function parseNamespaceDeclaration(): void
    {

        try {
            $this->expect(Token::NAMESPACE);

            $this->parseSmallGap();
            $namespaceAlias = $this->parseFusionObjectNamePart();

            $this->parseSmallGap();
            $this->expect('=');

            $this->parseSmallGap();
            $namespacePackageKey = $this->parseFusionObjectNamePart();

        } catch (\Exception $e) {
            throw new Fusion\Exception('Invalid namespace declaration "' . $namespaceDeclaration . '"' . $this->renderCurrentFileAndLineInformation(), 1180547190);
        }

        $this->setObjectTypeNamespace($namespaceAlias, $namespacePackageKey);

        $this->parseEndOfStatement();
    }

    /**
     * IncludeStatement
     *  : INCLUDE STRING
     *  | INCLUDE CHAR
     *  | INCLUDE FilePattern
     *  ;
     */
    protected function parseIncludeStatement()
    {
        $this->expect('INCLUDE');

        $this->parseSmallGap();

        switch ($this->peek()->getType()) {
            case Token::STRING:
            case Token::CHAR:
                $filePattern = $this->parseLiteral();
                break;
            default:
                $filePattern = $this->parseFilePattern();
        }

        $this->parseEndOfStatement();

//        $parser = new Parser();

//        if (strpos($filePattern, 'resource://') !== 0) {
//            // Resolve relative paths
//            if ($this->contextPathAndFilename !== null) {
//                $filePattern = dirname($this->contextPathAndFilename) . Token::SLASH . $filePattern;
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
        $this->parseSmallGap();

        $accepted = [];
        $save = function ($tokenType) use (&$accepted) {
            $accepted[] = $tokenType;
            return $tokenType;
        };

        switch ($this->peek()->getType()){
            case $save(Token::EOF):
                return;
            // just as experiment
            case $save(Token::SEMICOLON):
            case $save(Token::NEWLINE):
                $this->consume();
                return;
        }
        throw new \Exception('Expected in ' . __FUNCTION__ .  ' one of ' . $accepted . ' but got: ' . $this->peek() . ' on line: $this->peek()->lineInfo()');
    }

    /**
     * BlockStatement:
     *  : { StatementList }
     *  ;
     *
     */
    protected function parseBlockStatement(array $path)
    {
        $this->parseBigGap();
        $this->expect(Token::LBRACE);
        array_push($this->currentObjectPathStack, $path);

        $isNotEndOfBlockStatement = fn():bool => $this->peekIgnore(self::WHITESPACE)->getType() !== Token::RBRACE;
        if ($isNotEndOfBlockStatement()) {
            $this->parseStatementList($isNotEndOfBlockStatement);
        }

        array_pop($this->currentObjectPathStack);
        $this->parseBigGap();

        $this->expect(Token::RBRACE);
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
        if ($this->lazyExpect(Token::DOT)) {
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
        } while ($this->lazyExpect(Token::DOT));
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
        $value = $this->lazyExpectTokens([Token::DIGIT, Token::COLON, Token::MINUS, Token::UNDERSCORE, Token::LETTER, Token::TRUE, Token::FALSE, Token::NULL]);
        if ($value === null) {
            throw new \Exception('PathIdentifier but got' . $this->peek());
        }
        return $value;
    }

    /**
     * PathSegment
     *  : PathIdentifier
     *  | @ PathIdentifier
     *  | STRING
     *  | CHAR
     *  | PROTOTYPE_START FusionObjectName )
     *  ;
     *
     */
    protected function parsePathSegment(): array
    {
        $token = $this->peek();
        $this->astBuilder->keyIsReservedParseTreeKey($token->getValue());

        switch ($token->getType()) {
            case Token::DIGIT:
            case Token::LETTER:
            case Token::TRUE:
            case Token::FALSE:
            case Token::NULL:
            case Token::UNDERSCORE:
            case Token::MINUS:
            case Token::COLON:
                return [$this->parsePathIdentifier()];
            case Token::AT:
                $this->consume();
                return ['__meta', $this->parsePathIdentifier()];
            case Token::STRING:
            case Token::CHAR:
                $value = $this->parseLiteral();
                if ($value === '') {
                    throw new \Exception("a quoted path must not be empty");
                }
                return [$value];

            case Token::PROTOTYPE_START:
                $this->expect(Token::PROTOTYPE_START);
                $name = $this->parseFusionObjectName();
                $this->expect(Token::RPAREN);
                return ['__prototypes', $name];

            default:
                throw new \Exception("This Path segment makes no sense: " . $this->peek()->getType());
        }
    }

    /**
     * FusionObjectName
     *  : FusionObjectNamePart
     *  | FusionObjectNamePart : FusionObjectNamePart
     *  ;
     *
     */
    protected function parseFusionObjectName()
    {
        $objectPart = $this->parseFusionObjectNamePart();

        if ($this->lazyExpect(Token::COLON)) {
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
        switch ($this->peek()->getType()) {
            case Token::ASSIGNMENT:
                $this->consume();
                $this->parseSmallGap();
                $value = $this->parseValueAssignment();
                $this->astBuilder->setValueInObjectTree($currentPath, $value);
                return;

            case Token::UNSET:
                $this->consume();
                $this->astBuilder->removeValueInObjectTree($currentPath);
                return;

            case Token::COPY:
            case Token::EXTENDS:
                $operator = $this->consume()->getType();

                $this->parseSmallGap();
                $sourcePath = $this->parseObjectPathAssignment($this->astBuilder->getParentPath($currentPath));

                if ($this->astBuilder->countPrototypePaths($currentPath, $sourcePath) === 2) {
                    // both are a prototype definition
                    $this->astBuilder->inheritPrototypeInObjectTree($currentPath, $sourcePath);
                    return;
                } elseif ($this->astBuilder->countPrototypePaths($currentPath, $sourcePath) === 1) {
                    // Only one of "source" or "target" is a prototype. We do not support copying a
                    // non-prototype value to a prototype value or vice-versa.
                    throw new Fusion\Exception('Tried to parse "' . join(Token::DOT, $targetObjectPath) . '" < "' . join(Token::DOT, $sourceObjectPath) . '", however one of the sides is no prototype definition of the form prototype(Foo). It is only allowed to build inheritance chains with prototype objects.' . $this->renderFileStuff(), 1358418015);
                }

                if ($operator === Token::EXTENDS) {
                    throw new \Exception("EXTENDS doesnt support he copy operation");
                }

                $this->astBuilder->copyValueInObjectTree($currentPath, $sourcePath);
                return;

            default:
                throw new \Exception("no operation matched token: " . $this->peek());
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
        switch ($this->peek()->getType()) {
            case Token::EEL_EXPRESSION:
                return $this->parseEelExpression();

//            case 'UNCLOSED_EEL_EXPRESSION':
//                // implement as catch if token is ${ or as error visitor?
//                // TODO: line info and contents
//                throw new \Exception('an eel expression starting with: ');

//            case 'DSL_EXPRESSION':
//                // TODO catch unclosed
//                return $this->consume()->getValue();

            // TODO decimal with dot starting .123?
            // digit start
            case Token::MINUS:
            case Token::STRING:
            case Token::CHAR:
                return $this->parseLiteral();

            case Token::LETTER:
                return $this->parseFusionObject();

            case Token::FALSE:
            case Token::NULL:
            case Token::TRUE:
                // it could be a fusion object with the name TRUE:Fusion
                // check if the next token is anything that would lead to that its an object:
                switch ($this->peek(1)->getType()){
                    case Token::DOT:
                    case Token::COLON:
                        return $this->parseFusionObject();
                }
                return $this->parseLiteral();

            case Token::DIGIT:
                // we need to chek if its a fusionobject starting with a digit or a real number
                switch ($this->peek(1)->getType()){
                    case Token::LETTER:
                        return $this->parseFusionObject();
                    case Token::DOT:
                        if ($this->peek(2)->getType() === Token::LETTER) {
                            return $this->parseFusionObject();
                        }
                }
                return $this->parseLiteral();

            default:
                break;

        }

        throw new \Exception("this is not a ValueAssignment: ". $this->peek());
    }

    /**
     * EelExpression
     *  : EEL_EXPRESSION
     *  ;
     *
     */
    protected function parseEelExpression(): array
    {
        $eelExpression = $this->expect(Token::EEL_EXPRESSION)->getValue();
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
        switch ($this->peek()->getType()) {
            case Token::FALSE:
                $this->consume();
                return false;
            case Token::NULL:
                $this->consume();
                return null;
            case Token::TRUE:
                $this->consume();
                return true;
            case Token::DIGIT:
            case Token::MINUS:
                return $this->parseNumber();
            case Token::STRING:
                $string = $this->consume()->getValue();
                return self::stringUnquote($string);
            case Token::CHAR:
                $char = $this->consume()->getValue();
                return self::charUnquote($char);
            default:
                throw new \Exception('we dont support this Literal: ' . $this->peek());
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
        $int = $this->lazyExpect(Token::MINUS) ? '-' : '';
        $int .= $this->expect(Token::DIGIT)->getValue();

        if ($this->lazyExpect(Token::DOT)) {
            $decimal = $this->expect(Token::DIGIT)->getValue();
            $float = $int . '.' . $decimal;
            return floatval($float);
        }
        return intval($int);
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
}
