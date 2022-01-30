<?php
namespace Neos\Fusion\Core\CachedParser;

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
use Neos\Flow\Cache\CacheManager;

/**
 * Listener to clear Fusion File caches if files have changed
 *
 * It's used in the Package bootstrap as an early instance, so no full dependency injection is available.
 *
 * @Flow\Proxy(false)
 */
class FileMonitorListener
{
    /**
     * @var CacheManager
     */
    protected $flowCacheManager;

    /**
     * @param CacheManager $flowCacheManager
     */
    public function __construct(CacheManager $flowCacheManager)
    {
        $this->flowCacheManager = $flowCacheManager;
    }

    /**
     * @param $fileMonitorIdentifier
     * @param array $changedFiles
     * @return void
     */
    public function flushFusionFilesObjectTreeCacheOnFileChanges($fileMonitorIdentifier, array $changedFiles): void
    {
        if ($fileMonitorIdentifier !== 'Fusion_Files') {
            return;
        }

        // can only be hardcoded since no DI
        $fusionFileCache = $this->flowCacheManager->getCache('Neos_Fusion_FilesObjectTree');

        foreach ($changedFiles as $changedFile => $status) {
            $cacheIdentifier = CachedParser::getCacheIdentifierForPossibleUnresolvedResourcePath($changedFile);

            if ($fusionFileCache->has($cacheIdentifier)) {
                $fusionFileCache->remove($cacheIdentifier);
            }
        }
    }
}
