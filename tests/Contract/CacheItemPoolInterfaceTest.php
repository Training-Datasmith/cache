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
    }

    public function testGetItemsSignature(): void
    {
        $this->assertInterfaceMethod(
            CacheItemPoolInterface::class,
            'getItems',
            [['keys', 'array', true, []]],
            'iterable'
        );
        $this->assertDocTag(
            CacheItemPoolInterface::class,
            'getItems',
            'return',
            'iterable<string, CacheItemInterface>'
        );
        $this->assertDocTag(
            CacheItemPoolInterface::class,
            'getItems',
            'param',
            'string[] $keys'
        );
    }

    public function testKeyMethodsDocumentInvalidArgumentException(): void
    {
        foreach (['getItem', 'getItems', 'hasItem', 'deleteItem', 'deleteItems'] as $method) {
            $this->assertDocTag(
                CacheItemPoolInterface::class,
                $method,
                'throws',
                'InvalidArgumentException'
            );
        }
    }

    public function testHasItemSignature(): void
    {
        $this->assertInterfaceMethod(
            CacheItemPoolInterface::class,
            'hasItem',
            [['key', 'string']],
            'bool'
        );
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
    }

    public function testDeleteItemsSignature(): void
    {
        $this->assertInterfaceMethod(
            CacheItemPoolInterface::class,
            'deleteItems',
            [['keys', 'array']],
            'bool'
        );
        $this->assertDocTag(
            CacheItemPoolInterface::class,
            'deleteItems',
            'param',
            'string[] $keys'
        );
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
    }
}
