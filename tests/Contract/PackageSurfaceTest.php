<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

class PackageSurfaceTest extends TestCase
{
    public function testSrcContainsOnlyThePsr6Types(): void
    {
        $files = glob(dirname(__DIR__, 2) . '/src/*.php');
        $this->assertIsArray($files);

        $names = array_map('basename', $files);
        sort($names);

        $this->assertSame([
            'CacheException.php',
            'CacheItemInterface.php',
            'CacheItemPoolInterface.php',
            'InvalidArgumentException.php',
        ], $names);
    }

    public function testPublicTypesLiveInThePsrCacheNamespace(): void
    {
        $types = [
            CacheItemInterface::class => 'CacheItemInterface.php',
            CacheItemPoolInterface::class => 'CacheItemPoolInterface.php',
            CacheException::class => 'CacheException.php',
            InvalidArgumentException::class => 'InvalidArgumentException.php',
        ];

        foreach ($types as $type => $filename) {
            $reflection = new \ReflectionClass($type);
            $this->assertTrue($reflection->isInterface(), $type . ' must stay an interface.');
            $this->assertSame('Psr\\Cache', $reflection->getNamespaceName());
            $this->assertStringEndsWith('/src/' . $filename, str_replace('\\', '/', (string) $reflection->getFileName()));
            $this->assertSame([], $reflection->getConstants());
            $this->assertSame([], $reflection->getProperties());
        }
    }
}
