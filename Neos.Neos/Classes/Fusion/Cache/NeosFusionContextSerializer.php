<?php

declare(strict_types=1);

namespace Neos\Neos\Fusion\Cache;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\Factory\ContentRepositoryId;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Fusion\Core\Cache\FusionContextSerializer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Serializer for Fusion's [at]cache.context values
 *
 * Implements special handing for serializing {@see Node} objects in fusions cache context:
 *
 * ```
 * [at]cache {
 *   mode = 'uncached'
 *   context {
 *     1 = 'node'
 *   }
 * }
 * ```
 *
 * The property mapper cannot be relied upon to serialize nodes, as this is willingly not implemented.
 *
 * Serializing falls back to Fusion's standard {@see FusionContextSerializer} which uses Flow's property mapper.
 *
 * @internal
 */
final class NeosFusionContextSerializer implements NormalizerInterface, DenormalizerInterface
{
    public function __construct(
        private readonly FusionContextSerializer $fusionContextSerializer,
        private readonly ContentRepositoryRegistry $contentRepositoryRegistry
    ) {
    }

    /**
     * @param array<int|string,mixed> $context
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = [])
    {
        if ($type === Node::class) {
            return $this->deserializeNode($data);
        }
        return $this->fusionContextSerializer->denormalize($data, $type, $format, $context);
    }

    /**
     * @param array<int|string,mixed> $context
     * @return array<int|string,mixed>
     */
    public function normalize(mixed $object, string $format = null, array $context = [])
    {
        if ($object instanceof Node) {
            return $this->serializeNode($object);
        }
        return $this->fusionContextSerializer->normalize($object, $format, $context);
    }

    /**
     * @param array<int|string,mixed> $serializedNode
     */
    private function deserializeNode(array $serializedNode): Node
    {
        $contentRepositoryId = ContentRepositoryId::fromString($serializedNode['contentRepositoryId']);

        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);

        $workspace = $contentRepository->getWorkspaceFinder()->findOneByName(WorkspaceName::fromString($serializedNode['workspaceName']));
        if (!$workspace) {
            throw new \RuntimeException(sprintf('Could not find workspace while trying to convert node from array %s.', json_encode($serializedNode)), 1699782153);
        }

        $subgraph = $contentRepository->getContentGraph()->getSubgraph(
            $workspace->currentContentStreamId,
            DimensionSpacePoint::fromArray($serializedNode['dimensionSpacePoint']),
            $workspace->isPublicWorkspace()
                ? VisibilityConstraints::frontend()
                : VisibilityConstraints::withoutRestrictions()
        );

        $node = $subgraph->findNodeById(NodeAggregateId::fromString($serializedNode['nodeAggregateId']));
        if (!$node) {
            throw new \RuntimeException(sprintf('Could not find serialized node %s.', json_encode($serializedNode)), 1699782153);
        }
        return $node;
    }

    /**
     * @return array<int|string,mixed>
     */
    private function serializeNode(Node $source): array
    {
        $contentRepository = $this->contentRepositoryRegistry->get(
            $source->subgraphIdentity->contentRepositoryId
        );

        $workspace = $contentRepository->getWorkspaceFinder()->findOneByCurrentContentStreamId($source->subgraphIdentity->contentStreamId);

        if (!$workspace) {
            throw new \RuntimeException(sprintf('Could not fetch workspace for node (%s) in content stream (%s).', $source->nodeAggregateId->value, $source->subgraphIdentity->contentStreamId->value), 1699780153);
        }

        return [
            'contentRepositoryId' => $source->subgraphIdentity->contentRepositoryId->value,
            'workspaceName' => $workspace->workspaceName->value,
            'dimensionSpacePoint' => $source->subgraphIdentity->dimensionSpacePoint->jsonSerialize(),
            'nodeAggregateId' => $source->nodeAggregateId->value
        ];
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null)
    {
        return true;
    }

    public function supportsNormalization(mixed $data, string $format = null)
    {
        return true;
    }
}
