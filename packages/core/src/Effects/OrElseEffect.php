<?php

declare(strict_types=1);

namespace EffectPHP\Core\Effects;

use EffectPHP\Core\Contracts\Effect;

final class OrElseEffect extends BaseEffect
{
    public function __construct(
        public readonly Effect $primary,
        public readonly Effect $fallback
    ) {}

    public function flatMap(callable $chain): Effect
    {
        return new SuspendEffect($this, $chain);
    }
}