<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtime\Handlers;

use EffectPHP\Core\Contracts\Clock;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\SleepEffect;
use EffectPHP\Core\Effects\SuccessEffect;
use EffectPHP\Core\Exceptions\ServiceNotFoundException;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Runtime\EffectHandler;

/**
 * Handler for SleepEffect - implements duration-based delays
 * 
 * Uses the Clock service to provide abstraction over time operations.
 * This enables TestClock for time-independent testing.
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
        
        try {
            // Get the Clock service from context
            $clock = $context->getService(Clock::class);
            
            // Use Clock service for sleep operation
            // This enables TestClock for time-independent testing
            $clock->sleep($effect->duration, fn() => null);
            
            return new SuccessEffect(null);
        } catch (ServiceNotFoundException $e) {
            // Fallback to direct usleep if Clock service not available
            // This shouldn't happen with the default runtime, but provides safety
            usleep($effect->duration->toMicroseconds());
            return new SuccessEffect(null);
        }
    }
}