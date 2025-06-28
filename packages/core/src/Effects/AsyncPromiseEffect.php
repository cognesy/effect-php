<?php

declare(strict_types=1);

namespace EffectPHP\Core\Effects;

use Closure;
use EffectPHP\Core\Contracts\Effect;

/**
 * Effect for async operations using promises
 * 
 * Delegates promise execution to runtime-specific adapters
 */
final class AsyncPromiseEffect extends BaseEffect
{
    public function __construct(
        public readonly Closure $computation,
        public readonly ?Closure $errorHandler = null
    ) {}

    public function flatMap(callable $chain): Effect
    {
        return new SuspendEffect($this, $chain);
    }
}