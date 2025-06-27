<?php

declare(strict_types=1);

namespace EffectPHP\Core\Schedule\Nodes;

use EffectPHP\Core\Utils\Duration;

/** @internal */
final readonly class ExponentialNode extends ScheduleNode
{
    public function __construct(
        public Duration $base,
        public float $factor
    ) {}
}