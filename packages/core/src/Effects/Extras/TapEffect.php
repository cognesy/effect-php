<?php

namespace EffectPHP\Core\Effects\Extras;

use Closure;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Effects\BaseEffect;
use EffectPHP\Core\Effects\SuspendEffect;

/**
 * Effect that executes a side effect without changing the value
 *
 * Runs the side effect for its effect (logging, metrics, etc.) but preserves
 * the original value in the effect chain. The side effect can be either a
 * callable or an Effect.
 *
 * @template R
 * @template E of \Throwable
 * @template A
 * @extends BaseEffect<R, E, A>
 */
final class TapEffect extends BaseEffect
{
    public function __construct(
        public readonly Effect $source,
        public readonly Closure|Effect $sideEffect,
    ) {}

    public function flatMap(callable $chain): Effect {
        return new SuspendEffect($this, $chain);
    }
}