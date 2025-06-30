<?php

declare(strict_types=1);

namespace EffectPHP\Core\Effects\Extras;

use Closure;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Effects\BaseEffect;
use EffectPHP\Core\Effects\SuspendEffect;

/**
 * Effect that manages resource scopes with automatic cleanup
 * 
 * - Uses PHP's __destruct() for automatic cleanup
 * - Explicit closure capture with use() keyword
 * - Leverages WeakMap for automatic resource tracking
 */
final class ScopeEffect extends BaseEffect
{
    public function __construct(
        public readonly Closure $scoped
    ) {}

    public function flatMap(callable $chain): Effect
    {
        return new SuspendEffect($this, $chain);
    }
}