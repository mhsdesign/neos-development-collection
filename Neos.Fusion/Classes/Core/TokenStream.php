<?php

namespace Neos\Fusion\Core;

class TokenStream implements \Iterator, \Countable
{
    private array $tokens;

    /** @var int */
    private int $pointer;

    public function __construct(array $tokens)
    {
        $this->pointer = 0;
        $this->tokens = $tokens;
    }

    public function getTokenAt(int $index): ?array
    {
        return $this->tokens[$index] ?? null;
    }

    public function getNextNotIgnoredToken($offset, $excludeTokenTypes): ?array
    {
        for ($i = $offset; $i < count($this->tokens); $i++) {
            if (in_array($this->tokens[$i]['type'], $excludeTokenTypes) === false) {
                return $this->tokens[$i];
            }
        }
        return null;
    }

    public function getPointer(): int
    {
        return $this->pointer;
    }

    public function current(): array
    {
        return $this->tokens[$this->pointer];
    }

    public function next(): void
    {
        $this->pointer++;
    }

    public function key(): int
    {
        return $this->pointer;
    }

    public function valid(): bool
    {
        return isset($this->tokens[$this->pointer]);
    }

    public function rewind(): void
    {
        $this->pointer = 0;
    }

    public function count(): int
    {
        return count($this->tokens);
    }
}
