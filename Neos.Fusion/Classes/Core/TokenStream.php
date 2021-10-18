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

    public function mergeToNextToken(array $virtualTokens): ?bool
    {

        foreach ($virtualTokens as $virtualToken) {

            foreach ($virtualToken as $virtualTokenType => $tokenTypesToCombine) {
                if (in_array($this->current()['type'], $tokenTypesToCombine)) {

                    $combinedTokenValue = $this->current()['value'];

//                    \Neos\Flow\var_dump($this->tokens);

                    $tokensBeforeMerge = array_slice($this->tokens, 0, $this->pointer);


//                    $nextTokenPointer = $this->pointer;

//                    do {
//                        $nextTokenPointer++;
//                        $nextToken = $this->tokens[$nextTokenPointer];
//                        $combinedTokenValue .= $nextToken['value'];
//
//                    } while (in_array($nextToken['type'], $tokenTypesToCombine));

//
                    $nextTokenPointer = $this->pointer+1;
                    $nextToken = $this->tokens[$nextTokenPointer];

                    while (in_array($nextToken['type'], $tokenTypesToCombine)) {
                        $nextTokenPointer +=1;
                        $combinedTokenValue .= $nextToken['value'];
                        $nextToken = $this->tokens[$nextTokenPointer];
                    }

                    $tokensAfterMerge = array_slice($this->tokens, $nextTokenPointer);

                    if ($virtualTokenType === 0) {
                        $this->tokens = [...$tokensBeforeMerge, ...$tokensAfterMerge];
                        return null;
                    }

                    // TODO: also add here line number etc...
                    $mergedToken = [
                        'type' => $virtualTokenType,
                        'value' => $combinedTokenValue
                    ];

                    $this->tokens = [...$tokensBeforeMerge, $mergedToken, ...$tokensAfterMerge];
                    return true;
                }
                // only first els
                break;
            }
        }
        return false;
    }
}
