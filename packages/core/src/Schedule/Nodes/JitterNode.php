<?php

declare(strict_types=1);

namespace EffectPHP\Core\Schedule\Nodes;

use EffectPHP\Core\Utils\Duration;

/** @internal */
final readonly class JitterNode extends ScheduleNode
{
    public function __construct(
        public ScheduleNode $source,
        public float $factor
    ) {}
}