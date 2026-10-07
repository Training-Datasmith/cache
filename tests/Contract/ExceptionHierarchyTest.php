<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;

class ExceptionHierarchyTest extends TestCase
{
    use SignatureAssertions;

    public function testCacheExceptionExtendsThrowableAndDeclaresNoMethods(): void
    {
        $this->assertTrue(is_subclass_of(CacheException::class, \Throwable::class));
        $this->assertFalse(is_subclass_of(CacheException::class, InvalidArgumentException::class));
        $this->assertFalse(is_subclass_of(CacheException::class, \Exception::class));
        $this->assertSame(
            $this->inheritedInterfaceClosure(\Throwable::class),
            $this->sortedInterfaces(CacheException::class)
        );
        $this->assertMethodNames(CacheException::class, []);
    }

    public function testInvalidArgumentExceptionExtendsCacheException(): void
    {
        $this->assertTrue(is_subclass_of(InvalidArgumentException::class, CacheException::class));
        $this->assertTrue(is_subclass_of(InvalidArgumentException::class, \Throwable::class));
        $this->assertFalse(is_subclass_of(InvalidArgumentException::class, \InvalidArgumentException::class));

        $this->assertSame(
            $this->inheritedInterfaceClosure(CacheException::class),
            $this->sortedInterfaces(InvalidArgumentException::class)
        );
        $this->assertMethodNames(InvalidArgumentException::class, []);
    }

    /**
     * @param class-string $interface
     * @return list<string>
     */
    private function sortedInterfaces(string $interface): array
    {
        $names = (new \ReflectionClass($interface))->getInterfaceNames();
        sort($names);

        return $names;
    }

    /**
     * @param class-string $interface
     * @return list<string>
     */
    private function inheritedInterfaceClosure(string $interface): array
    {
        $names = (new \ReflectionClass($interface))->getInterfaceNames();
        $names[] = $interface;
        sort($names);

        return $names;
    }
}
