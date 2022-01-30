<?php

namespace Neos\Fusion\Core\CachedParser;

use Neos\Fusion\Core\Parser\AstBuilder;

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
        $sourceObjectPath = join('.', $sourceObjectPath);
        $targetObjectPath = join('.', $targetObjectPath);

        throw new \BadMethodCallException("The copy operation '$sourceObjectPath < $targetObjectPath' is not supported in the CachedParser.", 1643475497);
    }
}
