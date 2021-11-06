<?php
namespace Neos\Fusion\Annotations;

/**
 * Marks a method as to be decorated.
 *
 * @Annotation
 * @Target({"METHOD"})
 */
final class LexerMode {

    /**
     * @var int
     */
    public $lexerStateId;

    /**
     * @param array $values
     */
    public function __construct(array $values) {
        if (isset($values['value']) === false && isset($values['lexerStateId']) === false) {
            throw new \InvalidArgumentException('A LexerMode annotation must specify a lexer state Id.', 1633869108);
        }
        $this->lexerStateId = $values['lexerStateId'] ?? $values['value'];
    }
}
