<?php

namespace EffectPHP\Core\Effects\Execution;

use Closure;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Effects\BaseEffect;
use EffectPHP\Core\Effects\SuspendEffect;

class AsyncEffect extends BaseEffect {
    public function __construct(
        public readonly Closure $computation,
        public readonly ?Closure $errorHandler = null,
    ) {}

    public function flatMap(callable $chain): Effect {
        return new SuspendEffect($this, $chain);
    }
}