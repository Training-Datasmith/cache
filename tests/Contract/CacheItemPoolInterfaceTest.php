<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

class CacheItemPoolInterfaceTest extends TestCase
{
    use SignatureAssertions;

    public function testDeclaresExactlyThePsr6PoolMethods(): void
    {
        $this->assertSame([], (new \ReflectionClass(CacheItemPoolInterface::class))->getInterfaceNames());
        $this->assertMethodNames(CacheItemPoolInterface::class, [
            'getItem',
            'getItems',
            'hasItem',
            'clear',
            'deleteItem',
            'deleteItems',
            'save',
            'saveDeferred',
            'commit',
        ]);
    }

    public function testGetItemSignature(): void
    {
        $this->assertInterfaceMethod(
            CacheItemPoolInterface::class,
            'getItem',
            [['key', 'string']],
            CacheItemInterface::class
        );
        $this->assertDocContains(CacheItemPoolInterface::class, 'getItem', 'MUST NOT return null');
        $this->assertDocContains(CacheItemPoolInterface::class, 'getItem', 'InvalidArgumentException');
    }

    public function testGetItemsSignature(): void
    {
        $this->assertInterfaceMethod(
            CacheItemPoolInterface::class,
            'getItems',
            [['keys', 'array', true, []]],
            'iterable'
        );
        $this->assertDocContains(
            CacheItemPoolInterface::class,
            'getItems',
            'iterable<string, CacheItemInterface>'
        );
        $this->assertDocContains(
            CacheItemPoolInterface::class,
            'getItems',
            'if no keys are specified then an empty traversable MUST be returned'
        );
        $this->assertDocContains(CacheItemPoolInterface::class, 'getItems', 'InvalidArgumentException');
    }

    public function testHasItemSignature(): void
    {
        $this->assertInterfaceMethod(
            CacheItemPoolInterface::class,
            'hasItem',
            [['key', 'string']],
            'bool'
        );
        $this->assertDocContains(CacheItemPoolInterface::class, 'hasItem', 'InvalidArgumentException');
    }

    public function testClearSignature(): void
    {
        $this->assertInterfaceMethod(CacheItemPoolInterface::class, 'clear', [], 'bool');
    }

    public function testDeleteItemSignature(): void
    {
        $this->assertInterfaceMethod(
            CacheItemPoolInterface::class,
            'deleteItem',
            [['key', 'string']],
            'bool'
        );
        $this->assertDocContains(CacheItemPoolInterface::class, 'deleteItem', 'InvalidArgumentException');
    }

    public function testDeleteItemsSignature(): void
    {
        $this->assertInterfaceMethod(
            CacheItemPoolInterface::class,
            'deleteItems',
            [['keys', 'array']],
            'bool'
        );
        $this->assertDocContains(CacheItemPoolInterface::class, 'deleteItems', 'InvalidArgumentException');
    }

    public function testSaveSignature(): void
    {
        $this->assertInterfaceMethod(
            CacheItemPoolInterface::class,
            'save',
            [['item', CacheItemInterface::class]],
            'bool'
        );
    }

    public function testSaveDeferredSignature(): void
    {
        $this->assertInterfaceMethod(
            CacheItemPoolInterface::class,
            'saveDeferred',
            [['item', CacheItemInterface::class]],
            'bool'
        );
    }

    public function testCommitSignature(): void
    {
        $this->assertInterfaceMethod(CacheItemPoolInterface::class, 'commit', [], 'bool');
        $this->assertDocContains(
            CacheItemPoolInterface::class,
            'commit',
            'True if all not-yet-saved items were successfully saved or there were none'
        );
    }
}
