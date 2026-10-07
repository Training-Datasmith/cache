<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\Tests\Fixtures\SampleValue;

/**
 * Value rules for the in-memory reference pool in tests/Fixtures.
 * This is not coverage of src/. See tests/Contract for the package surface.
 */
class MemoryCachePoolValueTest extends TestCase
{
    use CreatesPools;

    public function testBasicUsage(): void
    {
        $pool = $this->pool();

        $first = $pool->getItem('key');
        $first->set('4711');
        $this->assertTrue($pool->save($first));

        $second = $pool->getItem('key2');
        $second->set('4712');
        $this->assertTrue($pool->save($second));

        $this->assertTrue($pool->getItem('key')->isHit());
        $this->assertSame('4711', $pool->getItem('key')->get());
        $this->assertTrue($pool->getItem('key2')->isHit());
        $this->assertSame('4712', $pool->getItem('key2')->get());

        $this->assertTrue($pool->deleteItem('key'));
        $this->assertFalse($pool->getItem('key')->isHit());
        $this->assertNull($pool->getItem('key')->get());
        $this->assertTrue($pool->getItem('key2')->isHit());

        $this->assertTrue($pool->clear());
        $this->assertFalse($pool->getItem('key')->isHit());
        $this->assertFalse($pool->getItem('key2')->isHit());
        $this->assertFalse($pool->hasItem('key2'));
    }

    public function testMissReturnsAnItemWhoseValueIsNull(): void
    {
        $pool = $this->pool();
        $item = $pool->getItem('missing');

        $this->assertInstanceOf(CacheItemInterface::class, $item);
        $this->assertNotNull($item);
        $this->assertSame('missing', $item->getKey());
        $this->assertFalse($item->isHit());
        $this->assertNull($item->get());
        $this->assertFalse($pool->hasItem('missing'));
    }

    public function testMissStaysAbsentUntilSaved(): void
    {
        $pool = $this->pool();
        $item = $pool->getItem('key');
        $this->assertFalse($item->isHit());
        $this->assertNull($item->get());

        $item->set('staged');
        $this->assertFalse($item->isHit());
        $this->assertNull($item->get());
        $this->assertFalse($pool->hasItem('key'));
        $fresh = $pool->getItem('key');
        $this->assertFalse($fresh->isHit());
        $this->assertNull($fresh->get());

        $this->assertTrue($pool->save($item));
        $fetched = $pool->getItem('key');
        $this->assertTrue($fetched->isHit());
        $this->assertSame('staged', $fetched->get());
    }

    public function testHitAndGetStayConsistentInEitherOrder(): void
    {
        $pool = $this->pool();
        $item = $pool->getItem('key');
        $item->set('value');
        $this->assertTrue($pool->save($item));

        $hitFirst = $pool->getItem('key');
        $this->assertTrue($hitFirst->isHit());
        $this->assertSame('value', $hitFirst->get());

        $valueFirst = $pool->getItem('key');
        $this->assertSame('value', $valueFirst->get());
        $this->assertTrue($valueFirst->isHit());
    }

    public function testUnsavedMutationDoesNotChangeTheStoredValue(): void
    {
        $pool = $this->pool();
        $item = $pool->getItem('key');
        $item->set('stored');
        $this->assertTrue($pool->save($item));

        $handle = $pool->getItem('key');
        $handle->set('local-only');

        $this->assertSame('stored', $pool->getItem('key')->get());
        $this->assertSame('local-only', $handle->get());
    }

    public function testKeyStaysImmutableAcrossMutators(): void
    {
        $pool = $this->pool();
        $item = $pool->getItem('stable');
        $this->assertSame(
            $item,
            $item->set('v')->expiresAfter(5)->expiresAt(null)
        );
        $this->assertSame('stable', $item->getKey());
    }

    /**
     * @return iterable<string, array{0: mixed}>
     */
    public static function roundTripValues(): iterable
    {
        yield 'string' => ['5'];
        yield 'integer' => [5];
        yield 'zero' => [0];
        yield 'negative' => [-42];
        yield 'int max' => [PHP_INT_MAX];
        yield 'int min' => [PHP_INT_MIN];
        yield 'float' => [1.23456789];
        yield 'float zero' => [0.0];
        yield 'true' => [true];
        yield 'false' => [false];
        yield 'null' => [null];
        yield 'empty string' => [''];
        yield 'empty array' => [[]];
        yield 'list' => [[1, '2', false, null]];
        yield 'assoc' => [['a' => 'foo', 2 => 'bar']];
        yield 'nested' => [[['x' => [true, null, 'z', 1]]]];
        yield 'unicode' => ['café — キャッシュ'];
        yield 'binary' => [self::binaryPayload()];
    }

    /**
     * @dataProvider roundTripValues
     * @param mixed $value
     */
    public function testStoredValueKeepsTypeAndContents($value): void
    {
        $pool = $this->pool();
        $item = $pool->getItem('key');
        $item->set($value);
        $this->assertTrue($pool->save($item));

        $fetched = $pool->getItem('key');
        $this->assertTrue($fetched->isHit());
        $this->assertTrue($pool->hasItem('key'));
        $this->assertSame($value, $fetched->get());
    }

    public function testNullIsAHitRatherThanAMiss(): void
    {
        $pool = $this->pool();
        $item = $pool->getItem('key');
        $item->set(null);
        $this->assertTrue($pool->save($item));

        $fetched = $pool->getItem('key');
        $this->assertTrue($pool->hasItem('key'));
        $this->assertTrue($fetched->isHit());
        $this->assertNull($fetched->get());
    }

    public function testIntegerReplacesAStringOfTheSameCharacters(): void
    {
        $pool = $this->pool();
        $item = $pool->getItem('key');
        $item->set('5');
        $this->assertTrue($pool->save($item));
        $this->assertSame('5', $pool->getItem('key')->get());

        $item = $pool->getItem('key');
        $item->set(5);
        $this->assertTrue($pool->save($item));
        $this->assertSame(5, $pool->getItem('key')->get());
    }

    public function testNonFiniteFloatsRoundTrip(): void
    {
        $pool = $this->pool();

        $infinite = $pool->getItem('inf');
        $infinite->set(\INF);
        $this->assertTrue($pool->save($infinite));
        $this->assertSame(\INF, $pool->getItem('inf')->get());
        $this->assertTrue(is_infinite($pool->getItem('inf')->get()));

        $negative = $pool->getItem('neginf');
        $negative->set(-\INF);
        $this->assertTrue($pool->save($negative));
        $this->assertSame(-\INF, $pool->getItem('neginf')->get());

        $nan = $pool->getItem('nan');
        $nan->set(\NAN);
        $this->assertTrue($pool->save($nan));
        $stored = $pool->getItem('nan')->get();
        $this->assertTrue(is_float($stored));
        $this->assertTrue(is_nan($stored));
        $this->assertTrue($pool->getItem('nan')->isHit());
    }

    public function testObjectsAndDateTimesRoundTripByEquality(): void
    {
        $pool = $this->pool();

        $object = new \stdClass();
        $object->a = 'foo';
        $sample = new SampleValue('widget', 3);
        $moment = new \DateTimeImmutable('2020-05-01T00:00:00+00:00');
        $nested = ['sample' => $sample, 'list' => [$object]];

        $item = $pool->getItem('object');
        $item->set($object);
        $this->assertTrue($pool->save($item));

        $item = $pool->getItem('sample');
        $item->set($sample);
        $this->assertTrue($pool->save($item));

        $item = $pool->getItem('moment');
        $item->set($moment);
        $this->assertTrue($pool->save($item));

        $item = $pool->getItem('nested');
        $item->set($nested);
        $this->assertTrue($pool->save($item));

        $this->assertEquals($object, $pool->getItem('object')->get());
        $this->assertInstanceOf(\stdClass::class, $pool->getItem('object')->get());

        $restored = $pool->getItem('sample')->get();
        $this->assertEquals(new SampleValue('widget', 3), $restored);
        $this->assertInstanceOf(SampleValue::class, $restored);

        $this->assertEquals($moment, $pool->getItem('moment')->get());
        $this->assertInstanceOf(\DateTimeImmutable::class, $pool->getItem('moment')->get());
        $this->assertEquals($nested, $pool->getItem('nested')->get());

        $object->a = 'changed';
        $sample->label = 'mutated';
        $sample->count = 99;

        $isolated = $pool->getItem('object')->get();
        $this->assertInstanceOf(\stdClass::class, $isolated);
        $this->assertNotSame($object, $isolated);
        $this->assertSame('foo', $isolated->a);

        $isolatedSample = $pool->getItem('sample')->get();
        $this->assertSame('widget', $isolatedSample->label);
        $this->assertSame(3, $isolatedSample->count);

        $isolatedNested = $pool->getItem('nested')->get();
        $this->assertSame('foo', $isolatedNested['list'][0]->a);
        $this->assertSame('widget', $isolatedNested['sample']->label);
        $this->assertSame(3, $isolatedNested['sample']->count);

        $isolated->a = 'changed after retrieval';
        $isolatedSample->label = 'changed after retrieval';
        $isolatedNested['list'][0]->a = 'changed after retrieval';

        $this->assertSame('foo', $pool->getItem('object')->get()->a);
        $this->assertSame('widget', $pool->getItem('sample')->get()->label);
        $this->assertSame('foo', $pool->getItem('nested')->get()['list'][0]->a);
    }

    public function testCallerArrayMutationsAfterSaveDoNotChangeTheStoredArray(): void
    {
        $pool = $this->pool();
        $value = ['a' => 1, 'b' => ['c' => 2]];
        $item = $pool->getItem('key');
        $item->set($value);
        $this->assertTrue($pool->save($item));

        $value['a'] = 99;
        $value['b']['c'] = 100;

        $this->assertSame(['a' => 1, 'b' => ['c' => 2]], $pool->getItem('key')->get());
    }

    public function testLongStringRoundTrips(): void
    {
        $pool = $this->pool();
        $value = str_repeat('á', 10000);
        $item = $pool->getItem('key');
        $item->set($value);
        $this->assertTrue($pool->save($item));

        $stored = $pool->getItem('key')->get();
        $this->assertSame($value, $stored);
    }

    public function testGetItemsReturnsAnItemForEveryRequestedKey(): void
    {
        $pool = $this->pool();
        foreach (['a' => 'A', 'b' => 'B', 'c' => 'C'] as $key => $value) {
            $item = $pool->getItem($key);
            $item->set($value);
            $this->assertTrue($pool->save($item));
        }

        $items = [];
        foreach ($this->pairs($pool->getItems(['c', 'missing', 'a'])) as [$key, $item]) {
            $items[$key] = $item;
        }

        $this->assertEqualsCanonicalizing(['c', 'missing', 'a'], array_keys($items));
        $this->assertTrue($items['c']->isHit());
        $this->assertSame('C', $items['c']->get());
        $this->assertFalse($items['missing']->isHit());
        $this->assertNull($items['missing']->get());
        $this->assertTrue($items['a']->isHit());
        $this->assertSame('A', $items['a']->get());
    }

    public function testGetItemsWithNoKeysIsEmpty(): void
    {
        $pool = $this->pool();
        $item = $pool->getItem('key');
        $item->set('v');
        $this->assertTrue($pool->save($item));

        $this->assertIsIterable($pool->getItems());
        $this->assertSame([], $this->pairs($pool->getItems()));
        $this->assertSame([], $this->pairs($pool->getItems([])));
    }

    public function testManyKeysRoundTrip(): void
    {
        $pool = $this->pool();
        $keys = [];
        for ($i = 0; $i < 50; ++$i) {
            $key = 'item_' . $i;
            $keys[] = $key;
            $item = $pool->getItem($key);
            $item->set($i);
            $this->assertTrue($pool->save($item));
        }

        $items = [];
        foreach ($this->pairs($pool->getItems($keys)) as [$key, $item]) {
            $items[$key] = $item;
        }

        $this->assertCount(50, $items);
        foreach ($keys as $index => $key) {
            $this->assertArrayHasKey($key, $items);
            $this->assertSame($index, $items[$key]->get());
        }

        $this->assertTrue($pool->deleteItems(['item_1', 'missing', 'item_3']));
        $this->assertFalse($pool->hasItem('item_1'));
        $this->assertFalse($pool->hasItem('item_3'));
        $this->assertSame(0, $pool->getItem('item_0')->get());
        $this->assertSame(2, $pool->getItem('item_2')->get());
    }

    public function testDeleteAndClearReportSuccessWhenTheKeyIsAlreadyGone(): void
    {
        $pool = $this->pool();
        $this->assertTrue($pool->deleteItem('missing'));
        $this->assertTrue($pool->deleteItems([]));
        $this->assertTrue($pool->deleteItems(['missing', 'also-missing']));
        $this->assertTrue($pool->clear());
        $this->assertFalse($pool->hasItem('missing'));
    }

    private static function binaryPayload(): string
    {
        $data = '';
        for ($i = 0; $i < 256; ++$i) {
            $data .= chr($i);
        }

        return $data;
    }
}
