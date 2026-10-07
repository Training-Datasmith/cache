<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Fixtures;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Reference PSR-6 pool used to exercise the cache interfaces.
 *
 * Keys must be non-empty and must not contain the reserved characters {}()/\@:.
 * Any other character, including keys longer than 64 bytes, is accepted.
 * A null TTL uses $defaultTtl when one is configured, otherwise the item does not expire.
 * Deferred saves are snapshotted and are visible on this pool before commit().
 * commit() runs from __destruct() so uncommitted items are not dropped.
 */
final class MemoryCachePool implements CacheItemPoolInterface
{
    private const RESERVED_CHARACTERS = '{}()/\\@:';

    /** @var string */
    private $id;

    /** @var MemoryBackend */
    private $backend;

    /** @var MutableClock */
    private $clock;

    /** @var int|null */
    private $defaultTtl;

    /**
     * Snapshots taken by saveDeferred(), keyed by cache key.
     *
     * @var array<string, array{value: mixed, expiration: \DateTimeImmutable|null}>
     */
    private $deferred = [];

    public function __construct(?MemoryBackend $backend = null, ?MutableClock $clock = null, ?int $defaultTtl = null)
    {
        if ($defaultTtl !== null && $defaultTtl < 0) {
            throw new SimpleInvalidArgumentException('Default TTL must be null or a non-negative integer.');
        }

        $this->id = bin2hex(random_bytes(16));
        $this->backend = $backend ?? new MemoryBackend();
        $this->clock = $clock ?? new MutableClock();
        $this->defaultTtl = $defaultTtl;
    }

    public function getItem(string $key): CacheItemInterface
    {
        $this->assertLegalKey($key);

        if (isset($this->deferred[$key])) {
            return $this->hydrate($key, $this->deferred[$key]);
        }

        $record = $this->backend->get($key);
        if ($record === null) {
            return new MemoryCacheItem($this->id, $key, $this->clock, false, null, null);
        }

        return $this->hydrate($key, $record);
    }

    public function getItems(array $keys = []): iterable
    {
        foreach ($keys as $key) {
            $this->assertLegalKey($key);
        }

        if ($keys === []) {
            return [];
        }

        return $this->iterateItems($keys);
    }

    public function hasItem(string $key): bool
    {
        $this->assertLegalKey($key);

        if (isset($this->deferred[$key])) {
            return !$this->isExpired($this->deferred[$key]['expiration']);
        }

        $record = $this->backend->get($key);
        if ($record === null) {
            return false;
        }

        return !$this->isExpired($record['expiration']);
    }

    public function clear(): bool
    {
        if ($this->backend->failClear) {
            return false;
        }

        $this->deferred = [];
        $this->backend->clear();

        return true;
    }

    public function deleteItem(string $key): bool
    {
        $this->assertLegalKey($key);

        if ($this->backend->failDeletes) {
            return false;
        }

        unset($this->deferred[$key]);
        $this->backend->remove($key);

        return true;
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->assertLegalKey($key);
        }

        foreach ($keys as $key) {
            if (!$this->deleteItem($key)) {
                return false;
            }
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        if (!$this->accepts($item)) {
            return false;
        }

        if ($this->backend->failWrites) {
            return false;
        }

        $key = $item->getKey();
        unset($this->deferred[$key]);

        $expiration = $this->resolveExpiration($item);
        if ($this->isExpired($expiration)) {
            $this->backend->remove($key);

            return true;
        }

        $this->backend->put($key, $item->currentValue(), $expiration);

        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        if (!$this->accepts($item)) {
            return false;
        }

        $this->deferred[$item->getKey()] = [
            'value' => $item->currentValue(),
            'expiration' => $this->resolveExpiration($item),
        ];

        return true;
    }

    public function commit(): bool
    {
        if ($this->deferred !== [] && $this->backend->failWrites) {
            return false;
        }

        foreach ($this->deferred as $key => $record) {
            if ($this->isExpired($record['expiration'])) {
                $this->backend->remove($key);
                continue;
            }

            $this->backend->put($key, $record['value'], $record['expiration']);
        }

        $this->deferred = [];

        return true;
    }

    public function __destruct()
    {
        $this->commit();
    }

    /**
     * @param array{value: mixed, expiration: \DateTimeImmutable|null} $record
     */
    private function hydrate(string $key, array $record): MemoryCacheItem
    {
        if ($this->isExpired($record['expiration'])) {
            return new MemoryCacheItem($this->id, $key, $this->clock, false, null, $record['expiration']);
        }

        return new MemoryCacheItem(
            $this->id,
            $key,
            $this->clock,
            true,
            $record['value'],
            $record['expiration']
        );
    }

    /**
     * @return iterable<string, CacheItemInterface>
     */
    private function iterateItems(array $keys): iterable
    {
        foreach ($keys as $key) {
            yield $key => $this->getItem($key);
        }
    }

    private function accepts(CacheItemInterface $item): bool
    {
        return $item instanceof MemoryCacheItem && $item->belongsTo($this->id);
    }

    private function resolveExpiration(MemoryCacheItem $item): ?\DateTimeImmutable
    {
        $expiration = $item->expiration();
        if ($expiration !== null || $this->defaultTtl === null) {
            return $expiration;
        }

        if ($this->defaultTtl === 0) {
            return $this->clock->now();
        }

        return $this->clock->now()->modify(sprintf('+%d seconds', $this->defaultTtl));
    }

    private function isExpired(?\DateTimeImmutable $expiration): bool
    {
        if ($expiration === null) {
            return false;
        }

        return $expiration <= $this->clock->now();
    }

    /**
     * @param mixed $key
     */
    private function assertLegalKey($key): void
    {
        if (!is_string($key)) {
            throw new \TypeError(sprintf('Cache key must be a string, %s given.', get_debug_type($key)));
        }

        if ($key === '' || strpbrk($key, self::RESERVED_CHARACTERS) !== false) {
            throw new SimpleInvalidArgumentException(sprintf('Cache key "%s" is not a legal value.', $key));
        }
    }
}
