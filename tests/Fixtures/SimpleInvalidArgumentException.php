<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Fixtures;

/**
 * Library-style exception: SPL invalid argument plus the PSR-6 marker interface.
 */
class SimpleInvalidArgumentException extends \InvalidArgumentException implements \Psr\Cache\InvalidArgumentException
{
}
