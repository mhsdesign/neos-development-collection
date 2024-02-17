<?php

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

declare(strict_types=1);

namespace Neos\ContentRepository\Core\SharedModel\Workspace;

/**
 * @api
 */
final class DetachedWorkspaceName implements \JsonSerializable
{
    public const PREFIX = 'DETACHED_';

    private function __construct(
        public readonly ContentStreamId $contentStreamId
    ) {
    }

    public static function tryFromString(string $value): ?self
    {
        if (!str_starts_with(self::PREFIX, $value)) {
            return null;
        }
        return self::fromString($value);
    }

    public static function fromString(string $value): self
    {
        if (!str_starts_with(self::PREFIX, $value)) {
            throw new \InvalidArgumentException(sprintf('Value %s is not a DetatchedWorkspaceName.', $value), 1708205491);
        }
        return new self(
            ContentStreamId::fromString(substr($value, 0, strlen(self::PREFIX)))
        );
    }

    public static function fromContentStreamId(ContentStreamId $contentStreamId): self
    {
        return new self($contentStreamId);
    }

    public function jsonSerialize(): string
    {
        return self::PREFIX . $this->contentStreamId->value;
    }

    public function equals(self $other): bool
    {
        return $this->contentStreamId->equals($other->contentStreamId);
    }
}
