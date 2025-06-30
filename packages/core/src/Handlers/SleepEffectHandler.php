<?php

declare(strict_types=1);

namespace EffectPHP\Core\Handlers;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\Execution\SleepEffect;
use EffectPHP\Core\Effects\SuspendEffect;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Utils\ContinuationStack;

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

    public function handle(Effect $effect, ContinuationStack $stack, Context $context, Runtime $runtime): Effect
    {
        /** @var \EffectPHP\Core\Effects\Execution\SleepEffect $effect */
        
        // Create suspended effect - runtime will call suspend()
        return new SuspendEffect($effect, function($resolve, $reject) use ($effect, $runtime) {
            try {
                $runtime->strategy()->sleep($effect->duration);
                $resolve(null);
            } catch (\Throwable $e) {
                $reject($e);
            }
        });
    }
}