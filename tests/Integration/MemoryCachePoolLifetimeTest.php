<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Expiration rules for the in-memory reference pool in tests/Fixtures.
 * This is not coverage of src/. See tests/Contract for the package surface.
 */
class MemoryCachePoolLifetimeTest extends TestCase
{
    use CreatesPools;

    public function testFutureExpirationIsAHitUntilTheInstantIsReached(): void
    {
        $clock = $this->clock();
        $pool = $this->pool($clock);
        $item = $pool->getItem('key');
        $item->set('value')->expiresAfter(10);
        $this->assertTrue($pool->save($item));

        $clock->advance(9);
        $fetched = $pool->getItem('key');
        $this->assertTrue($fetched->isHit());
        $this->assertSame('value', $fetched->get());
        $this->assertTrue($pool->hasItem('key'));

        $clock->advance(1);
        $this->assertTrue($fetched->isHit(), 'An already fetched item must not flip between isHit() and get().');
        $this->assertSame('value', $fetched->get());

        $expired = $pool->getItem('key');
        $this->assertFalse($expired->isHit());
        $this->assertNull($expired->get());
        $this->assertFalse($pool->hasItem('key'));
    }

    public function testExpiresAtAcceptsMutableAndImmutableInstants(): void
    {
        $clock = $this->clock();
        $pool = $this->pool($clock);

        $mutable = $pool->getItem('mutable');
        $mutable->set('m');
        $this->assertSame($mutable, $mutable->expiresAt(new \DateTime('2026-01-15T13:00:00+00:00')));
        $this->assertTrue($pool->save($mutable));

        $immutable = $pool->getItem('immutable');
        $immutable->set('i')->expiresAt(new \DateTimeImmutable('2026-01-15T13:00:00+00:00'));
        $this->assertTrue($pool->save($immutable));

        $this->assertTrue($pool->getItem('mutable')->isHit());
        $this->assertTrue($pool->getItem('immutable')->isHit());

        $clock->set(new \DateTimeImmutable('2026-01-15T13:00:00+00:00'));
        $this->assertFalse($pool->hasItem('mutable'));
        $this->assertFalse($pool->hasItem('immutable'));
        $this->assertNull($pool->getItem('immutable')->get());
    }

    public function testExpiresAtCopiesTheInstantInsteadOfKeepingTheCallerClock(): void
    {
        $clock = $this->clock();
        $pool = $this->pool($clock);
        $item = $pool->getItem('key');
        $item->set('value');
        $mutable = new \DateTime('2026-01-15T13:00:00+00:00');
        $item->expiresAt($mutable);
        $mutable->modify('-2 hours');

        $this->assertTrue($pool->save($item));
        $this->assertTrue($pool->getItem('key')->isHit());
    }

    public function testExpiresAtComparesAbsoluteInstantsAcrossTimeZones(): void
    {
        $clock = $this->clock(new \DateTimeImmutable('2026-01-15T12:00:00+00:00'));
        $pool = $this->pool($clock);
        $item = $pool->getItem('key');
        $item->set('value');
        $item->expiresAt(new \DateTime('2026-01-15T08:00:00', new \DateTimeZone('America/New_York')));
        $this->assertTrue($pool->save($item));
        $this->assertTrue($pool->hasItem('key'));

        $clock->set(new \DateTimeImmutable('2026-01-15T13:00:00+00:00'));
        $this->assertFalse($pool->hasItem('key'));
    }

    public function testZeroAndNegativeLifetimesAreAlreadyExpired(): void
    {
        $clock = $this->clock();
        $pool = $this->pool($clock);

        foreach ([0, -1, -30] as $ttl) {
            $item = $pool->getItem('int-' . $ttl);
            $item->set('value')->expiresAfter($ttl);
            $this->assertTrue($pool->save($item));
            $this->assertFalse($pool->hasItem('int-' . $ttl));
            $this->assertNull($pool->getItem('int-' . $ttl)->get());
        }

        $interval = new \DateInterval('PT0S');
        $item = $pool->getItem('zero-interval');
        $item->set('value')->expiresAfter($interval);
        $this->assertTrue($pool->save($item));
        $this->assertFalse($pool->hasItem('zero-interval'));

        $negative = new \DateInterval('PT30S');
        $negative->invert = 1;
        $item = $pool->getItem('negative-interval');
        $item->set('value')->expiresAfter($negative);
        $this->assertSame(1, $negative->invert);
        $this->assertTrue($pool->save($item));
        $this->assertFalse($pool->hasItem('negative-interval'));
    }

    public function testDateIntervalLifetimeUsesTheInjectedClock(): void
    {
        $clock = $this->clock();
        $pool = $this->pool($clock);
        $interval = new \DateInterval('PT30S');
        $item = $pool->getItem('key');
        $item->set('value')->expiresAfter($interval);
        $this->assertSame('30', $interval->format('%s'));
        $this->assertTrue($pool->save($item));

        $clock->advance(29);
        $this->assertTrue($pool->hasItem('key'));
        $clock->advance(1);
        $this->assertFalse($pool->getItem('key')->isHit());
    }

    public function testSavingAnExpiredItemRemovesThePreviousValue(): void
    {
        $clock = $this->clock();
        $pool = $this->pool($clock);
        $item = $pool->getItem('key');
        $item->set('fresh')->expiresAt($clock->now()->modify('+1 hour'));
        $this->assertTrue($pool->save($item));

        $item = $pool->getItem('key');
        $item->set('stale')->expiresAt($clock->now()->modify('-1 second'));
        $this->assertTrue($pool->save($item));

        $this->assertFalse($pool->hasItem('key'));
        $this->assertFalse($pool->getItem('key')->isHit());
        $this->assertNull($pool->getItem('key')->get());
    }

    public function testNullExpirationLastsWithoutADefaultTtl(): void
    {
        $clock = $this->clock();
        $pool = $this->pool($clock);
        $item = $pool->getItem('key');
        $item->set('forever')->expiresAfter(1)->expiresAt(null);
        $this->assertTrue($pool->save($item));

        $clock->advance(86400 * 365);
        $this->assertTrue($pool->getItem('key')->isHit());
        $this->assertSame('forever', $pool->getItem('key')->get());

        $replaced = $pool->getItem('key');
        $replaced->set('still')->expiresAfter(null);
        $this->assertTrue($pool->save($replaced));
        $clock->advance(86400);
        $this->assertSame('still', $pool->getItem('key')->get());
    }

    public function testLastExpirationCallWins(): void
    {
        $clock = $this->clock();
        $pool = $this->pool($clock);

        $pastThenShort = $pool->getItem('recovered');
        $pastThenShort->set('value')->expiresAt($clock->now()->modify('-1 hour'))->expiresAfter(30);
        $this->assertTrue($pool->save($pastThenShort));
        $this->assertTrue($pool->hasItem('recovered'));

        $shortThenPast = $pool->getItem('dropped');
        $shortThenPast->set('value')->expiresAfter(30)->expiresAt($clock->now()->modify('-1 second'));
        $this->assertTrue($pool->save($shortThenPast));
        $this->assertFalse($pool->hasItem('dropped'));
    }
}
