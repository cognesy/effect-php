<?php

namespace EffectPHP\Core\Effects\Extras;

use Closure;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Effects\BaseEffect;
use EffectPHP\Core\Effects\SuspendEffect;

/**
 * Effect that lifts a callable into the Effect system
 *
 * Converts regular PHP callables into Effects for seamless integration.
 * The callable execution is deferred to the runtime.
 *
 * @template R
 * @template E of \Throwable
 * @template A
 * @extends BaseEffect<R, E, A>
 */
final class FromCallableEffect extends BaseEffect
{
    public function __construct(
        public readonly Closure $computation,
        public readonly array $args = [],
    ) {}

    public function flatMap(callable $chain): Effect {
        return new SuspendEffect($this, $chain);
    }
}