<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Fixtures;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * In-memory PSR-6 pool used only by tests/Integration.
 *
 * This is a reference implementation, not part of the package under test.
 * Coverage of src/ lives in tests/Contract.
 *
 * Keys must be non-empty and must not contain the reserved characters {}()/\@:.
 * A null expiration does not expire. Deferred saves are copied and stay visible
 * on this pool before commit(). commit() also runs from __destruct().
 * Stored values are serialized so later changes to the caller's variables do
 * not change the cached value.
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

    /**
     * Snapshots taken by saveDeferred(), keyed by cache key.
     *
     * @var array<string, array{value: mixed, expiration: \DateTimeImmutable|null}>
     */
    private $deferred = [];

    public function __construct(?MemoryBackend $backend = null, ?MutableClock $clock = null)
    {
        $this->id = bin2hex(random_bytes(16));
        $this->backend = $backend ?? new MemoryBackend();
        $this->clock = $clock ?? new MutableClock();
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
        $this->deferred = [];
        $this->backend->clear();

        return true;
    }

    public function deleteItem(string $key): bool
    {
        $this->assertLegalKey($key);

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
            $this->deleteItem($key);
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        if (!$this->accepts($item)) {
            return false;
        }

        $key = $item->getKey();
        unset($this->deferred[$key]);

        $expiration = $item->expiration();
        if ($this->isExpired($expiration)) {
            $this->backend->remove($key);

            return true;
        }

        $this->backend->put($key, $this->isolateValue($item->currentValue()), $expiration);

        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        if (!$this->accepts($item)) {
            return false;
        }

        $this->deferred[$item->getKey()] = [
            'value' => $this->isolateValue($item->currentValue()),
            'expiration' => $item->expiration(),
        ];

        return true;
    }

    public function commit(): bool
    {
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
            return new MemoryCacheItem($this->id, $key, $this->clock, false, null, null);
        }

        return new MemoryCacheItem(
            $this->id,
            $key,
            $this->clock,
            true,
            $this->isolateValue($record['value']),
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

    /**
     * @param mixed $value
     * @return mixed
     */
    private function isolateValue($value)
    {
        return unserialize(serialize($value));
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
            throw new SimpleInvalidArgumentException(sprintf('Cache key must be a string, %s given.', get_debug_type($key)));
        }

        if ($key === '' || strpbrk($key, self::RESERVED_CHARACTERS) !== false) {
            throw new SimpleInvalidArgumentException(sprintf('Cache key "%s" is not a legal value.', $key));
        }
    }
}
