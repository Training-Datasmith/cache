<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Fixtures;

use Psr\Cache\CacheItemInterface;

/**
 * Cache item that did not come from MemoryCachePool.
 */
final class ForeignCacheItem implements CacheItemInterface
{
    /** @var string */
    private $key;

    /** @var mixed */
    private $value;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return false;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(int|\DateInterval|null $time): static
    {
        return $this;
    }
}
