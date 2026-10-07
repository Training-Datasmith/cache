<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Psr\Cache\Tests\Fixtures\MemoryBackend;

/**
 * Deferred-save rules for the in-memory reference pool in tests/Fixtures.
 * This is not coverage of src/. See tests/Contract for the package surface.
 */
class MemoryCachePoolDeferredTest extends TestCase
{
    use CreatesPools;

    public function testDeferredItemsAreVisibleBeforeCommitAndPersistedAfter(): void
    {
        $backend = new MemoryBackend();
        $clock = $this->clock();
        $pool = $this->pool($clock, $backend);
        $reader = $this->pool($clock, $backend);

        $first = $pool->getItem('key');
        $first->set('4711');
        $this->assertTrue($pool->saveDeferred($first));

        $second = $pool->getItem('key2');
        $second->set('4712');
        $this->assertTrue($pool->saveDeferred($second));

        $this->assertTrue($pool->hasItem('key'));
        $this->assertTrue($pool->getItem('key')->isHit());
        $this->assertSame('4711', $pool->getItem('key')->get());
        $this->assertTrue($pool->getItem('key2')->isHit());

        $this->assertTrue($pool->commit());
        $this->assertTrue($reader->hasItem('key'));
        $this->assertSame('4711', $reader->getItem('key')->get());
        $this->assertSame('4712', $pool->getItem('key2')->get());
        $this->assertTrue($pool->commit());
    }

    public function testMutatingALaterHandleDoesNotChangeTheDeferredSnapshot(): void
    {
        $pool = $this->pool();
        $item = $pool->getItem('key');
        $item->set('value');
        $this->assertTrue($pool->saveDeferred($item));

        $item = $pool->getItem('key');
        $item->set('new value');

        $this->assertSame('value', $pool->getItem('key')->get());
        $this->assertTrue($pool->commit());
        $this->assertSame('value', $pool->getItem('key')->get());
    }

    public function testLaterDeferredSaveOverwritesTheSnapshot(): void
    {
        $pool = $this->pool();
        $item = $pool->getItem('key');
        $item->set('value');
        $this->assertTrue($pool->saveDeferred($item));

        $item = $pool->getItem('key');
        $item->set('new value');
        $this->assertTrue($pool->saveDeferred($item));

        $this->assertSame('new value', $pool->getItem('key')->get());
        $this->assertTrue($pool->commit());
        $this->assertSame('new value', $pool->getItem('key')->get());
    }

    public function testImmediateSaveReplacesADeferredValue(): void
    {
        $pool = $this->pool();
        $deferred = $pool->getItem('key');
        $deferred->set('deferred');
        $this->assertTrue($pool->saveDeferred($deferred));

        $immediate = $pool->getItem('key');
        $immediate->set('immediate');
        $this->assertTrue($pool->save($immediate));
        $immediate->set('after-save');
        $this->assertTrue($pool->commit());

        $this->assertSame('immediate', $pool->getItem('key')->get());
    }

    public function testExpiredDeferredItemHidesAnyPreviousValue(): void
    {
        $clock = $this->clock();
        $pool = $this->pool($clock);
        $existing = $pool->getItem('key');
        $existing->set('old');
        $this->assertTrue($pool->save($existing));

        $replacement = $pool->getItem('key');
        $replacement->set('new')->expiresAt($clock->now()->modify('-1 second'));
        $this->assertTrue($pool->saveDeferred($replacement));

        $this->assertFalse($pool->hasItem('key'));
        $this->assertFalse($pool->getItem('key')->isHit());
        $this->assertNull($pool->getItem('key')->get());

        $this->assertTrue($pool->commit());
        $this->assertFalse($pool->hasItem('key'));
        $this->assertNull($pool->getItem('key')->get());
    }

    public function testDeferredLifetimeUsesTheClock(): void
    {
        $clock = $this->clock();
        $pool = $this->pool($clock);
        $item = $pool->getItem('key');
        $item->set('value')->expiresAfter(2);
        $this->assertTrue($pool->saveDeferred($item));

        $clock->advance(1);
        $this->assertTrue($pool->hasItem('key'));
        $clock->advance(1);
        $this->assertFalse($pool->hasItem('key'));
        $this->assertTrue($pool->commit());
        $this->assertFalse($pool->getItem('key')->isHit());
    }

    public function testDeletedDeferredItemDoesNotReappearOnCommit(): void
    {
        $backend = new MemoryBackend();
        $pool = $this->pool(null, $backend);
        $reader = $this->pool(null, $backend);
        $existing = $pool->getItem('key');
        $existing->set('old');
        $this->assertTrue($pool->save($existing));

        $item = $pool->getItem('key');
        $item->set('4711');
        $this->assertTrue($pool->saveDeferred($item));
        $this->assertTrue($pool->getItem('key')->isHit());
        $this->assertSame('4711', $pool->getItem('key')->get());

        $this->assertTrue($pool->deleteItem('key'));
        $this->assertFalse($reader->hasItem('key'));
        $this->assertFalse($pool->hasItem('key'));
        $this->assertFalse($pool->getItem('key')->isHit());

        $this->assertTrue($pool->commit());
        $this->assertFalse($pool->hasItem('key'));
        $this->assertFalse($reader->hasItem('key'));
    }

    public function testClearDropsDeferredItems(): void
    {
        $pool = $this->pool();
        $item = $pool->getItem('key');
        $item->set('value');
        $this->assertTrue($pool->saveDeferred($item));

        $this->assertTrue($pool->clear());
        $this->assertTrue($pool->commit());
        $this->assertFalse($pool->getItem('key')->isHit());
        $this->assertFalse($pool->hasItem('key'));
    }

    public function testDestructorPersistsDeferredItems(): void
    {
        $backend = new MemoryBackend();
        $clock = $this->clock();
        $pool = $this->pool($clock, $backend);
        $first = $pool->getItem('key');
        $first->set('4711');
        $second = $pool->getItem('other');
        $second->set('4712');
        $this->assertTrue($pool->saveDeferred($first));
        $this->assertTrue($pool->saveDeferred($second));

        unset($first, $second, $pool);

        $again = $this->pool($clock, $backend);
        $this->assertTrue($again->getItem('key')->isHit());
        $this->assertSame('4711', $again->getItem('key')->get());
        $this->assertSame('4712', $again->getItem('other')->get());
    }

    public function testGetItemsSeesDeferredValues(): void
    {
        $pool = $this->pool();
        $hit = $pool->getItem('hit');
        $hit->set('ready');
        $this->assertTrue($pool->saveDeferred($hit));

        $items = [];
        foreach ($this->pairs($pool->getItems(['hit', 'miss'])) as [$key, $item]) {
            $items[$key] = $item;
        }

        $this->assertTrue($items['hit']->isHit());
        $this->assertSame('ready', $items['hit']->get());
        $this->assertFalse($items['miss']->isHit());
        $this->assertNull($items['miss']->get());
    }
}
