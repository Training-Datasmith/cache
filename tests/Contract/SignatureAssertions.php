<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Contract;

use PHPUnit\Framework\TestCase;

/**
 * Shared reflection checks for the PSR-6 interfaces.
 */
trait SignatureAssertions
{
    /**
     * @param class-string $interface
     * @param list<array{0: string, 1: string, 2?: bool, 3?: mixed}> $parameters
     */
    private function assertInterfaceMethod(string $interface, string $name, array $parameters, string $returnType): void
    {
        if (!$this instanceof TestCase) {
            throw new \LogicException('SignatureAssertions must be used by a test case.');
        }

        $class = new \ReflectionClass($interface);
        $this->assertTrue($class->hasMethod($name), $interface . '::' . $name . ' is missing.');

        $method = $class->getMethod($name);
        $this->assertSame($interface, $method->getDeclaringClass()->getName());
        $this->assertTrue($method->isPublic());
        $this->assertFalse($method->isStatic());

        $return = $method->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $return);
        $this->assertSame($returnType, $return->getName());
        if ($returnType === 'mixed') {
            $this->assertTrue($return->allowsNull(), 'mixed includes null.');
        } else {
            $this->assertFalse($return->allowsNull(), $name . ' must not declare a nullable return type.');
        }

        $actual = $method->getParameters();
        $this->assertCount(count($parameters), $actual, $name . ' parameter count mismatch.');

        foreach ($parameters as $index => $expected) {
            $parameter = $actual[$index];
            $this->assertSame($expected[0], $parameter->getName());
            $this->assertFalse($parameter->isPassedByReference());
            $this->assertFalse($parameter->isVariadic());
            $this->assertParameterType($parameter, $expected[1]);

            $optional = $expected[2] ?? false;
            $this->assertSame($optional, $parameter->isOptional());
            if ($optional) {
                $this->assertTrue($parameter->isDefaultValueAvailable());
                $this->assertSame($expected[3], $parameter->getDefaultValue());
            } else {
                $this->assertFalse($parameter->isDefaultValueAvailable());
            }
        }
    }

    private function assertParameterType(\ReflectionParameter $parameter, string $expected): void
    {
        $type = $parameter->getType();
        $this->assertNotNull($type);

        if ($expected === 'mixed') {
            $this->assertInstanceOf(\ReflectionNamedType::class, $type);
            $this->assertSame('mixed', $type->getName());
            $this->assertTrue($type->allowsNull());

            return;
        }

        if (str_starts_with($expected, '?')) {
            $this->assertInstanceOf(\ReflectionNamedType::class, $type);
            $this->assertSame(substr($expected, 1), $type->getName());
            $this->assertTrue($type->allowsNull());

            return;
        }

        if (str_contains($expected, '|')) {
            $this->assertInstanceOf(\ReflectionUnionType::class, $type);
            $names = [];
            foreach ($type->getTypes() as $part) {
                $this->assertInstanceOf(\ReflectionNamedType::class, $part);
                $names[] = $part->getName();
            }
            // PHP's reflection does not promise to return union members in source order.
            $this->assertEqualsCanonicalizing(explode('|', $expected), $names);

            return;
        }

        $this->assertInstanceOf(\ReflectionNamedType::class, $type);
        $this->assertSame($expected, $type->getName());
        $this->assertFalse($type->allowsNull());
    }

    /**
     * @param class-string $interface
     * @param list<string> $expected
     */
    private function assertMethodNames(string $interface, array $expected): void
    {
        if (!$this instanceof TestCase) {
            throw new \LogicException('SignatureAssertions must be used by a test case.');
        }

        $class = new \ReflectionClass($interface);
        $names = [];
        foreach ($class->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() === $interface) {
                $names[] = $method->getName();
            }
        }

        $this->assertSame($expected, $names);
    }

    /**
     * @param class-string $interface
     */
    private function assertDocContains(string $interface, string $method, string $needle): void
    {
        if (!$this instanceof TestCase) {
            throw new \LogicException('SignatureAssertions must be used by a test case.');
        }

        $comment = (new \ReflectionMethod($interface, $method))->getDocComment();
        $this->assertIsString($comment);
        $stripped = preg_replace('#^\s*/\*\*|\*/\s*$#', '', $comment);
        $stripped = preg_replace('#^\s*\*\s?#m', '', (string) $stripped);
        $normalized = preg_replace('/\s+/', ' ', trim((string) $stripped));
        $this->assertIsString($normalized);
        $this->assertStringContainsString($needle, $normalized);
    }
}
