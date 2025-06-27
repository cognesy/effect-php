<?php

declare(strict_types=1);

namespace EffectPHP\Core\Effects;

use EffectPHP\Core\Contracts\Effect;

final class RaceEffect extends EffectBase
{
    public function __construct(public readonly array $effects) {}

    public function flatMap(callable $chain): Effect
    {
        return new SuspendEffect($this, $chain);
    }
}