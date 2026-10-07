<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Fixtures;

use Psr\Cache\CacheItemInterface;

/**
 * Cache item for the reference implementation in tests/Fixtures.
 *
 * This is test support, not part of the package under test.
 */
final class MemoryCacheItem implements CacheItemInterface
{
    /** @var string */
    private $poolId;

    /** @var string */
    private $key;

    /** @var MutableClock */
    private $clock;

    /** @var bool */
    private $hit;

    /** @var mixed */
    private $value;

    /** @var \DateTimeImmutable|null */
    private $expiration;

    /**
     * @param mixed $value
     */
    public function __construct(
        string $poolId,
        string $key,
        MutableClock $clock,
        bool $hit,
        $value,
        ?\DateTimeImmutable $expiration
    ) {
        $this->poolId = $poolId;
        $this->key = $key;
        $this->clock = $clock;
        $this->hit = $hit;
        $this->value = $value;
        $this->expiration = $expiration;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        if (!$this->hit) {
            return null;
        }

        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->hit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        if ($expiration === null) {
            $this->expiration = null;

            return $this;
        }

        $immutable = \DateTimeImmutable::createFromInterface($expiration);
        $this->expiration = $immutable;

        return $this;
    }

    public function expiresAfter(int|\DateInterval|null $time): static
    {
        if ($time === null) {
            $this->expiration = null;

            return $this;
        }

        $now = $this->clock->now();
        if ($time instanceof \DateInterval) {
            $this->expiration = $now->add($time);

            return $this;
        }

        if ($time === 0) {
            $this->expiration = $now;

            return $this;
        }

        $this->expiration = $now->modify(sprintf('%+d seconds', $time));

        return $this;
    }

    public function belongsTo(string $poolId): bool
    {
        return $this->poolId === $poolId;
    }

    /**
     * @return mixed
     */
    public function currentValue()
    {
        return $this->value;
    }

    public function expiration(): ?\DateTimeImmutable
    {
        return $this->expiration;
    }
}
