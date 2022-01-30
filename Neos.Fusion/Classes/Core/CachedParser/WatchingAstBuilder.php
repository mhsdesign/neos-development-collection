<?php

namespace Neos\Fusion\Core\CachedParser;

use Neos\Fusion\Core\AstBuilder;

class WatchingAstBuilder extends AstBuilder
{
    public $watchedValueUnsets = [];
    public function removeValueInObjectTree(array $targetObjectPath): void
    {
        $this->watchedValueUnsets[] = $targetObjectPath;
        parent::removeValueInObjectTree($targetObjectPath);
    }

    public function copyValueInObjectTree(array $targetObjectPath, array $sourceObjectPath): void
    {
        throw new \BadMethodCallException("The copy operation is not supported in the CachedParser.", 1643475497);
    }
}
