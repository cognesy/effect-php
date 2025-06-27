<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtimes\Default\Handlers;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\SleepEffect;
use EffectPHP\Core\Effects\SuccessEffect;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Runtimes\Default\EffectHandler;

/**
 * Handler for SleepEffect - implements duration-based delays
 * 
 * For synchronous runtime: Uses blocking sleep (usleep)
 * For async runtime: Should integrate with event loop timers
 */
final class SleepEffectHandler implements EffectHandler
{
    public function canHandle(Effect $effect): bool
    {
        return $effect instanceof SleepEffect;
    }

    public function handle(Effect $effect, array &$stack, Context $context, Runtime $runtime): Effect
    {
        /** @var SleepEffect $effect */
        
        // For synchronous runtime, we use blocking sleep
        // In async runtimes, this should:
        // 1. Schedule a timer with the event loop
        // 2. Suspend the current fiber
        // 3. Resume after the duration expires
        usleep($effect->duration->toMicroseconds());
        
        return new SuccessEffect(null);
    }
}