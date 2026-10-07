<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Fixtures;

/**
 * Storage for the reference implementation in tests/Fixtures.
 *
 * This is test support, not part of the package under test. Deferred queues
 * stay on the pool rather than this shared backend.
 */
final class MemoryBackend
{
    /**
     * @var array<string, array{value: mixed, expiration: \DateTimeImmutable|null}>
     */
    private array $items = [];

    /**
     * @return array{value: mixed, expiration: \DateTimeImmutable|null}|null
     */
    public function get(string $key): ?array
    {
        return $this->items[$key] ?? null;
    }

    public function put(string $key, mixed $value, ?\DateTimeImmutable $expiration): void
    {
        $this->items[$key] = [
            'value' => $value,
            'expiration' => $expiration,
        ];
    }

    public function remove(string $key): void
    {
        unset($this->items[$key]);
    }

    public function clear(): void
    {
        $this->items = [];
    }
}
