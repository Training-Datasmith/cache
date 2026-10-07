<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;

/**
 * Key rules for the in-memory reference pool in tests/Fixtures.
 * This is not coverage of src/. See tests/Contract for the package surface.
 */
class MemoryCachePoolKeyTest extends TestCase
{
    use CreatesPools;

    /**
     * @return list<array{0: string}>
     */
    public static function invalidKeys(): array
    {
        return [
            [''],
            ['{'],
            ['}'],
            ['('],
            [')'],
            ['/'],
            ['\\'],
            ['@'],
            [':'],
            ['{str'],
            ['rand{'],
            ['rand{str'],
            ['rand}str'],
            ['rand(str'],
            ['rand)str'],
            ['rand/str'],
            ['rand\\str'],
            ['rand@str'],
            ['rand:str'],
        ];
    }

    /**
     * @return list<array{0: mixed}>
     */
    public static function invalidKeyTypes(): array
    {
        return [
            [true],
            [false],
            [null],
            [2],
            [2.5],
            [new \stdClass()],
            [['array']],
        ];
    }

    public function testRequiredKeyCharactersRoundTripUnmodified(): void
    {
        $pool = $this->pool();
        $keys = [
            '.',
            '..',
            '_',
            '0',
            '00',
            '0.0',
            'A',
            'z',
            'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_.',
        ];

        $this->assertSame(64, strlen($keys[8]));

        foreach ($keys as $key) {
            $item = $pool->getItem($key);
            $this->assertFalse($item->isHit());
            $this->assertSame($key, $item->getKey());
            $item->set('value:' . $key);
            $this->assertTrue($pool->save($item));
        }

        foreach ($keys as $key) {
            $item = $pool->getItem($key);
            $this->assertTrue($item->isHit(), 'Key was not stored: ' . $key);
            $this->assertSame($key, $item->getKey());
            $this->assertSame('value:' . $key, $item->get());
            $this->assertTrue($pool->hasItem($key));
        }

        $pairs = $this->pairs($pool->getItems(['0', '00', '0.0', '123']));
        $this->assertSame(
            [
                ['0', 'string', '0', true],
                ['00', 'string', '00', true],
                ['0.0', 'string', '0.0', true],
                ['123', 'string', '123', false],
            ],
            array_map(static function (array $pair): array {
                return [$pair[0], gettype($pair[0]), $pair[1]->getKey(), $pair[1]->isHit()];
            }, $pairs)
        );
    }

    public function testKeysAreCaseSensitive(): void
    {
        $pool = $this->pool();
        $lower = $pool->getItem('key');
        $lower->set('lower');
        $upper = $pool->getItem('Key');
        $upper->set('upper');
        $this->assertTrue($pool->save($lower));
        $this->assertTrue($pool->save($upper));

        $this->assertSame('lower', $pool->getItem('key')->get());
        $this->assertSame('upper', $pool->getItem('Key')->get());
    }

    /**
     * @dataProvider invalidKeys
     */
    public function testGetItemRejectsIllegalKeys(string $key): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->pool()->getItem($key);
    }

    /**
     * @dataProvider invalidKeys
     */
    public function testHasItemRejectsIllegalKeys(string $key): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->pool()->hasItem($key);
    }

    /**
     * @dataProvider invalidKeys
     */
    public function testDeleteItemRejectsIllegalKeys(string $key): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->pool()->deleteItem($key);
    }

    /**
     * @dataProvider invalidKeys
     */
    public function testGetItemsRejectsIllegalKeysBeforeIterating(string $key): void
    {
        $pool = $this->pool();
        $item = $pool->getItem('key1');
        $item->set('kept');
        $this->assertTrue($pool->save($item));

        try {
            $pool->getItems(['key1', $key, 'key2']);
            $this->fail('Expected an invalid cache key to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertInstanceOf(CacheException::class, $exception);
        }

        $this->assertTrue($pool->hasItem('key1'));
        $this->assertSame('kept', $pool->getItem('key1')->get());
    }

    /**
     * @dataProvider invalidKeys
     */
    public function testDeleteItemsValidatesEveryKeyBeforeMutation(string $key): void
    {
        $pool = $this->pool();
        $item = $pool->getItem('key1');
        $item->set('kept');
        $this->assertTrue($pool->save($item));

        try {
            $pool->deleteItems(['key1', $key, 'key2']);
            $this->fail('Expected an invalid cache key to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertInstanceOf(CacheException::class, $exception);
        }

        $this->assertTrue($pool->hasItem('key1'));
        $this->assertSame('kept', $pool->getItem('key1')->get());
    }

    /**
     * @dataProvider invalidKeyTypes
     * @param mixed $key
     */
    public function testScalarKeyMethodsRejectNonStrings($key): void
    {
        $pool = $this->pool();

        try {
            $pool->getItem($key);
            $this->fail('getItem() accepted a non-string key.');
        } catch (\TypeError $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }

        try {
            $pool->hasItem($key);
            $this->fail('hasItem() accepted a non-string key.');
        } catch (\TypeError $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }

        try {
            $pool->deleteItem($key);
            $this->fail('deleteItem() accepted a non-string key.');
        } catch (\TypeError $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }
    }

    /**
     * @dataProvider invalidKeyTypes
     * @param mixed $key
     */
    public function testListKeyMethodsRejectNonStrings($key): void
    {
        $pool = $this->pool();

        try {
            $pool->getItems(['key1', $key, 'key2']);
            $this->fail('getItems() accepted a non-string key.');
        } catch (\TypeError $exception) {
            $this->assertStringContainsString('string', $exception->getMessage());
        }

        try {
            $pool->deleteItems(['key1', $key, 'key2']);
            $this->fail('deleteItems() accepted a non-string key.');
        } catch (\TypeError $exception) {
            $this->assertStringContainsString('string', $exception->getMessage());
        }
    }

    public function testGetItemsUsesValuesFromAnAssociativeList(): void
    {
        $pool = $this->pool();
        $pairs = $this->pairs($pool->getItems(['ignored' => 'real', 'also-ignored' => 'other']));

        $this->assertSame('real', $pairs[0][0]);
        $this->assertSame('real', $pairs[0][1]->getKey());
        $this->assertSame('other', $pairs[1][0]);
        $this->assertSame('other', $pairs[1][1]->getKey());
    }

    public function testDeletingNumericStringKeyDoesNotDeleteALookalike(): void
    {
        $pool = $this->pool();
        foreach (['0', '00'] as $key) {
            $item = $pool->getItem($key);
            $item->set($key);
            $this->assertTrue($pool->save($item));
        }

        $this->assertTrue($pool->deleteItem('0'));
        $this->assertFalse($pool->hasItem('0'));
        $this->assertSame('00', $pool->getItem('00')->get());
    }
}
