<?php

declare(strict_types=1);

namespace EffectPHP\Core\Effects;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Schedule\Schedule;

final class RetryEffect extends BaseEffect
{
    public function __construct(
        public readonly Effect $source,
        public readonly Schedule $schedule
    ) {}

    public function flatMap(callable $chain): Effect
    {
        return new SuspendEffect($this, $chain);
    }
}