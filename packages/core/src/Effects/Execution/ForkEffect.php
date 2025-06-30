<?php

namespace EffectPHP\Core\Effects\Execution;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Effects\BaseEffect;
use EffectPHP\Core\Effects\SuspendEffect;

class ForkEffect extends BaseEffect
{
    public function __construct(
        public readonly Effect $forked,
    ) {}

    public function flatMap(callable $chain): Effect {
        return new SuspendEffect($this, $chain);
    }
}