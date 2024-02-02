<?php

declare(strict_types=1);

namespace Neos\ContentRepository\Core\SharedModel\Node;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\Factory\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Workspace\ContentStreamId;

/**
 * The content-repository-id content-stream-id dimension-space-point and the node-aggregate-id
 * Are used in combination to distinctly identify a single node.
 *
 * By using getting the content graph for the content-repository-id,
 * one can build a subgraph with the perspective to find this node:
 *
 *      $subgraph = $contentgraph->getSubgraph(
 *          $nodeIdentity->contentStreamId,
 *          $nodeIdentity->dimensionSpacePoint,
 *          // show all disabled nodes
 *          VisibilityConstraints::withoutRestrictions()
 *      );
 *      $node = $subgraph->findNodeById($nodeIdentity->nodeAggregateId);
 *
 * @api
 */
final readonly class NodeIdentity implements \JsonSerializable
{
    public function __construct(
        public ContentRepositoryId $contentRepositoryId,
        public ContentStreamId $contentStreamId,
        public DimensionSpacePoint $dimensionSpacePoint,
        public NodeAggregateId $nodeAggregateId,
    ) {
    }

    /**
     * @param array<string, mixed> $array
     */
    public static function fromArray(array $array): self
    {
        return new self(
            ContentRepositoryId::fromString($array['contentRepositoryId']),
            ContentStreamId::fromString($array['contentStreamId']),
            DimensionSpacePoint::fromArray($array['dimensionSpacePoint']),
            NodeAggregateId::fromString($array['nodeAggregateId'])
        );
    }

    public function jsonSerialize(): mixed
    {
        return [
            'contentRepositoryId' => $this->contentRepositoryId,
            'contentStreamId' => $this->contentStreamId,
            'dimensionSpacePoint' => $this->dimensionSpacePoint,
            'nodeAggregateId' => $this->nodeAggregateId
        ];
    }
}
