<?php

declare(strict_types=1);

namespace EffectPHP\RuntimeV1\Fiber;

use EffectPHP\Core\Clock\Clock;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Effects\Execution\SleepEffect;
use EffectPHP\Core\Effects\SuccessEffect;
use EffectPHP\Core\Exceptions\ServiceNotFoundException;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Utils\ContinuationStack;
use Fiber;
use FiberRuntime;
use Runtime;

/**
 * Fiber-aware handler for SleepEffect
 * 
 * This handler properly suspends fibers during sleep operations,
 * enabling virtual time testing and true async behavior.
 */
final class FiberSleepEffectHandler implements EffectHandler
{
    public function canHandle(Effect $effect): bool
    {
        return $effect instanceof SleepEffect;
    }

    public function handle(Effect $effect, ContinuationStack $stack, Context $context, Runtime $runtime): Effect
    {
        /** @var SleepEffect $effect */
        try {
            // Get the Clock service from context
            $clock = $context->getService(Clock::class);
            
            // If we're in a fiber-based runtime, use fiber suspension
            if ($runtime instanceof FiberRuntime) {
                $this->handleWithFiber($effect, $clock, $runtime);
            } else {
                // Fallback to direct clock sleep
                $clock->sleep($effect->duration, fn() => null);
            }
            
            return new SuccessEffect(null);
        } catch (ServiceNotFoundException $e) {
            // Fallback to direct usleep if Clock service not available
            usleep($effect->duration->toMicroseconds());
            return new SuccessEffect(null);
        }
    }

    private function handleWithFiber(SleepEffect $effect, Clock $clock, FiberRuntime $runtime): void
    {
        $currentFiber = Fiber::getCurrent();
        
        if (!$currentFiber) {
            // Not in a fiber, delegate to clock
            $clock->sleep($effect->duration, fn() => null);
            return;
        }

        $scheduler = $runtime->getFiberScheduler();
        
        // Use the Clock's sleep method with a continuation
        // The Clock implementation decides how to handle the timing
        $clock->sleep($effect->duration, function() use ($scheduler, $currentFiber) {
            // When the sleep completes, mark the fiber for resumption
            $scheduler->resumeFiber($currentFiber);
        });
        
        // Suspend the current fiber
        $scheduler->suspendFiber($currentFiber);
        Fiber::suspend();
    }
}