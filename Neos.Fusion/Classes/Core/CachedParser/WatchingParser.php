<?php

namespace Neos\Fusion\Core\CachedParser;

use Neos\Fusion\Core\Parser;

class WatchingParser extends Parser
{
    public $watchedFileIncludes = [];

    // could also be solved by giving the parser a closure $fusionFileHandler
    protected function includeAndParseFilesByPattern(string $filePattern): void
    {
        $this->watchedFileIncludes[] = $filePattern;
    }
}
