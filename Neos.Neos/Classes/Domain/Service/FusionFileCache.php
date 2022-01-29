<?php

namespace Neos\Neos\Domain\Service;

use Neos\Cache\Frontend\VariableFrontend;
use Neos\Fusion\Core\CachedParserCache;

class FusionFileCache implements CachedParserCache
{

    public function getFusionFileCache(): VariableFrontend
    {
        // TODO: Implement getFusionFileCache() method.
    }

    public function setObjectTree(array $objectTree): void
    {
        // TODO: Implement setObjectTree() method.
    }

    public function mergeObjectTree(array $objectTree): void
    {
        // TODO: Implement mergeObjectTree() method.
    }

    public function getObjectTree(): array
    {
        // TODO: Implement getObjectTree() method.
    }
}
