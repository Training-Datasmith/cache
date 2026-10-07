<?php

declare(strict_types=1);

namespace Psr\Cache\Tests\Fixtures;

final class SampleValue
{
    /** @var string */
    public $label;

    /** @var int */
    public $count;

    public function __construct(string $label, int $count)
    {
        $this->label = $label;
        $this->count = $count;
    }
}
