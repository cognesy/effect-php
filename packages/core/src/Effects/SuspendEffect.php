<?php

declare(strict_types=1);

namespace EffectPHP\Core\Effects;

use Closure;
use EffectPHP\Core\Contracts\Effect;

/**
 * Stack-safe suspension for chaining
 *
 * @template R
 * @template E of \Throwable
 * @template A
 * @extends EffectBase<R, E, A>
 */
final class SuspendEffect extends EffectBase
{
    public function __construct(
        public readonly Effect $source,
        public readonly Closure $continuation
    ) {}

    /**
     * Chain another operation - delegate to base implementation
     */
    public function flatMap(callable $chain): Effect
    {
        // Use the default flatMap from EffectBase which creates FlatMapEffect
        // This avoids infinite recursion in SuspendEffect continuation fusion
        return parent::flatMap($chain);
    }
}