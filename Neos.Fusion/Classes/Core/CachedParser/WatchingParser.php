<?php

namespace Neos\Fusion\Core\CachedParser;

class WatchingParser extends \Neos\Fusion\Core\Parser
{
    public $watchedFileIncludes = [];

    // could also be solved by giving the parser a closure $fusionFileHandler
    protected function includeAndParseFilesByPattern(string $filePattern): void
    {
        $this->watchedFileIncludes[] = $filePattern;
    }
}
