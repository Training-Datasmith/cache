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
        $this->assertDocContains(
            CacheItemInterface::class,
            'get',
            'identical to the value originally stored by set()'
        );
        $this->assertDocContains(
            CacheItemInterface::class,
            'get',
            'If isHit() returns false, this method MUST return null'
        );
    }

    public function testIsHitSignature(): void
    {
        $this->assertInterfaceMethod(CacheItemInterface::class, 'isHit', [], 'bool');
        $this->assertDocContains(
            CacheItemInterface::class,
            'isHit',
            'MUST NOT have a race condition between calling isHit() and calling get()'
        );
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
        $this->assertDocContains(
            CacheItemInterface::class,
            'expiresAt',
            'If null is passed explicitly, a default value MAY be used'
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
        $this->assertDocContains(
            CacheItemInterface::class,
            'expiresAfter',
            'An integer parameter is understood to be the time in seconds until expiration'
        );
    }
}
