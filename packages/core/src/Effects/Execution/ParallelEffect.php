<?php

declare(strict_types=1);

namespace EffectPHP\Core\Effects\Execution;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Effects\BaseEffect;
use EffectPHP\Core\Effects\SuspendEffect;

final class ParallelEffect extends BaseEffect
{
    public function __construct(public readonly array $effects) {}

    public function flatMap(callable $chain): Effect
    {
        return new SuspendEffect($this, $chain);
    }
}