<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\Tests\Fixtures\MemoryBackend;
use Psr\Cache\Tests\Fixtures\MemoryCachePool;
use Psr\Cache\Tests\Fixtures\MutableClock;

/**
 * Builds the in-memory reference pool exercised by tests/Integration.
 * Those tests are not coverage of src/.
 */
trait CreatesPools
{
    protected function clock(?\DateTimeImmutable $now = null): MutableClock
    {
        return new MutableClock($now);
    }

    protected function pool(?MutableClock $clock = null, ?MemoryBackend $backend = null): MemoryCachePool
    {
        return new MemoryCachePool($backend ?? new MemoryBackend(), $clock ?? new MutableClock());
    }

    /**
     * Collect iterable pairs without using the cache key as a PHP array key.
     * Numeric strings such as "0" would otherwise be cast to integers.
     *
     * @return list<array{0: string, 1: CacheItemInterface}>
     */
    protected function pairs(iterable $items): array
    {
        if (!$this instanceof TestCase) {
            throw new \LogicException('CreatesPools must be used by a test case.');
        }

        $pairs = [];
        foreach ($items as $key => $item) {
            $this->assertIsString($key, 'getItems() keys must stay strings.');
            $this->assertInstanceOf(CacheItemInterface::class, $item);
            $pairs[] = [$key, $item];
        }

        return $pairs;
    }
}
