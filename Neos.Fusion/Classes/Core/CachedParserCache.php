<?php

namespace Neos\Fusion\Core;

use Neos\Cache\Frontend\VariableFrontend;

interface CachedParserCache
{
    public function getFusionFileCache(): VariableFrontend;
    public function setObjectTree(array $objectTree): void;
    public function mergeObjectTree(array $objectTree): void;
    public function getObjectTree(): array;
}
