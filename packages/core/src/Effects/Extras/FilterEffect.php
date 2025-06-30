<?php

namespace EffectPHP\Core\Effects\Extras;

use Closure;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Effects\BaseEffect;
use EffectPHP\Core\Effects\SuspendEffect;
use EffectPHP\Core\Exceptions\FilterException;

/**
 * Effect that conditionally filters values based on a predicate
 *
 * If the predicate returns true, the value passes through unchanged.
 * If the predicate returns false, the effect fails with FilterException.
 * The predicate evaluation is deferred to runtime.
 *
 * @template R
 * @template E of \Throwable
 * @template A
 * @extends BaseEffect<R, E|FilterException, A>
 */
final class FilterEffect extends BaseEffect
{
    public function __construct(
        public readonly Effect $source,
        public readonly Closure $predicate,
        public readonly ?string $errorMessage = null,
    ) {}

    public function flatMap(callable $chain): Effect {
        return new SuspendEffect($this, $chain);
    }
}