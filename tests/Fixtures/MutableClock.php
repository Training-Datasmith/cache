<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Fixtures;

/**
 * Clock whose "present" can be moved without sleeping.
 */
final class MutableClock
{
    private \DateTimeImmutable $now;

    public function __construct(?\DateTimeImmutable $now = null)
    {
        $this->now = $now ?? new \DateTimeImmutable('2026-01-15T12:00:00+00:00');
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function set(\DateTimeImmutable $now): void
    {
        $this->now = $now;
    }

    public function advance(int $seconds): void
    {
        $this->now = $this->now->modify(sprintf('%+d seconds', $seconds));
    }
}
