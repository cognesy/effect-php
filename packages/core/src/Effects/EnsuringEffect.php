<?php

declare(strict_types=1);

namespace EffectPHP\Core\Effects;

use Closure;
use EffectPHP\Core\Contracts\Effect;

final class EnsuringEffect extends EffectBase
{
    public function __construct(
        public readonly Effect $source,
        public readonly Closure $cleanup
    ) {}

    public function flatMap(callable $chain): Effect
    {
        return new SuspendEffect($this, $chain);
    }
}