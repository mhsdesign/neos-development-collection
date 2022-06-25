<?php
declare(strict_types=1);
namespace Neos\Neos\FrontendRouting\DimensionResolution;

use Neos\ContentRepositoryRegistry\ValueObject\ContentRepositoryIdentifier;

/**
 * See {@see DimensionResolverInterface} for documentation.
 *
 * @api
 */
interface DimensionResolverFactoryInterface
{
    public function create(ContentRepositoryIdentifier $contentRepositoryIdentifier, array $dimensionResolverOptions): DimensionResolverInterface;
}
