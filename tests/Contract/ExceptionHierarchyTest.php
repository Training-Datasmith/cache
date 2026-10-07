<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Psr\Cache\Tests\Fixtures\SimpleInvalidArgumentException;

class ExceptionHierarchyTest extends TestCase
{
    use SignatureAssertions;

    public function testCacheExceptionExtendsThrowableAndDeclaresNoMethods(): void
    {
        $this->assertTrue(is_subclass_of(CacheException::class, \Throwable::class));
        $this->assertTrue(is_subclass_of(CacheException::class, \Stringable::class));
        $this->assertFalse(is_subclass_of(CacheException::class, InvalidArgumentException::class));
        $this->assertFalse(is_subclass_of(CacheException::class, \Exception::class));
        $this->assertSame(
            [\Stringable::class, \Throwable::class],
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
            [CacheException::class, \Stringable::class, \Throwable::class],
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

    public function testLibraryExceptionIsCatchableAsEitherPsrInterface(): void
    {
        $exception = new SimpleInvalidArgumentException('bad key');

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(CacheException::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
        $this->assertSame('bad key', $exception->getMessage());

        $caught = null;
        try {
            throw $exception;
        } catch (CacheException $caught) {
            $this->assertSame($exception, $caught);
        }
    }
}
