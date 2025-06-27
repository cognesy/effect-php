<?php

declare(strict_types=1);

namespace EffectPHP\Core\Effects;

use Closure;
use EffectPHP\Core\Contracts\Effect;

/**
 * Effect representing synchronous computation with error handling
 * 
 * Pure Effect description - stores computation for runtime execution.
 * Different runtimes can handle sync operations differently:
 * - Default runtime: Direct execution
 * - Async runtimes: May defer to event loop
 * - Testing runtimes: May mock or instrument
 */
final class SyncEffect extends EffectBase
{
    public function __construct(
        public readonly Closure $computation
    ) {}

    public function flatMap(callable $chain): Effect
    {
        return new SuspendEffect($this, $chain);
    }
}