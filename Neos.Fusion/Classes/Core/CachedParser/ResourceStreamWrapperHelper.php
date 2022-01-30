<?php

namespace Neos\Fusion\Core\CachedParser;

use Neos\Flow\Package\FlowPackageInterface;
use Neos\Flow\Package\PackageManager;
use Neos\Flow\ResourceManagement\Streams\ResourceStreamWrapper;
use Neos\Utility\Files;
use Neos\Flow\Annotations as Flow;

/**
 * TODO Since the ResourceStreamWrapper has no option for this we build it our self
 * This is obviously the wrong location but its WIP ^^
 *
 */
class ResourceStreamWrapperHelper
{
    /**
     * @Flow\Inject(lazy = false)
     * @var PackageManager
     */
    protected $packageManager;

    public function getAbsolutePath(string $requestedPath): string
    {
        $requestPathParts = explode('://', $requestedPath, 2);
        if ($requestPathParts[0] !== ResourceStreamWrapper::getScheme()) {
            throw new \InvalidArgumentException('The ' . __CLASS__ . ' only supports the \'' . ResourceStreamWrapper::getScheme() . '\' scheme.', 1643540543);
        }

        if (isset($requestPathParts[1]) === false) {
            throw new \InvalidArgumentException('Incomplete $requestedPath', 1643540538);
        }

        $resourceUriWithoutScheme = $requestPathParts[1];

        if (strpos($resourceUriWithoutScheme, '/') === false && preg_match('/^[0-9a-f]{40}$/i', $resourceUriWithoutScheme) === 1) {
            throw new \InvalidArgumentException('Cant process resource', 1643540539);
        }

        list($packageName, $path) = explode('/', $resourceUriWithoutScheme, 2);

        try {
            $package = $this->packageManager->getPackage($packageName);
        } catch (\Neos\Flow\Package\Exception\UnknownPackageException $packageException) {
            throw new \Exception(sprintf('Invalid resource URI "%s": Package "%s" is not available.', $requestedPath, $packageName), 123, $packageException);
        }

        if ($package instanceof FlowPackageInterface === false) {
            return false;
        }

        return Files::concatenatePaths([$package->getResourcesPath(), $path]);
    }
}
