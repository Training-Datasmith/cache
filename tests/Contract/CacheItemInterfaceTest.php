<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;

class CacheItemInterfaceTest extends TestCase
{
    use SignatureAssertions;

    public function testDeclaresExactlyThePsr6ItemMethods(): void
    {
        $this->assertSame([], (new \ReflectionClass(CacheItemInterface::class))->getInterfaceNames());
        $this->assertMethodNames(CacheItemInterface::class, [
            'getKey',
            'get',
            'isHit',
            'set',
            'expiresAt',
            'expiresAfter',
        ]);
    }

    public function testGetKeySignature(): void
    {
        $this->assertInterfaceMethod(CacheItemInterface::class, 'getKey', [], 'string');
    }

    public function testGetSignature(): void
    {
        $this->assertInterfaceMethod(CacheItemInterface::class, 'get', [], 'mixed');
    }

    public function testIsHitSignature(): void
    {
        $this->assertInterfaceMethod(CacheItemInterface::class, 'isHit', [], 'bool');
    }

    public function testSetSignature(): void
    {
        $this->assertInterfaceMethod(
            CacheItemInterface::class,
            'set',
            [['value', 'mixed']],
            'static'
        );
    }

    public function testExpiresAtSignature(): void
    {
        $this->assertInterfaceMethod(
            CacheItemInterface::class,
            'expiresAt',
            [['expiration', '?DateTimeInterface']],
            'static'
        );
    }

    public function testExpiresAfterSignature(): void
    {
        $this->assertInterfaceMethod(
            CacheItemInterface::class,
            'expiresAfter',
            [['time', 'int|DateInterval|null']],
            'static'
        );
    }
}
