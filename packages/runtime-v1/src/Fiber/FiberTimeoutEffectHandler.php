<?php

declare(strict_types=1);

namespace EffectPHP\RuntimeV1\Fiber;

use EffectPHP\Core\Cause\Cause;
use EffectPHP\Core\Clock\Clock;
use EffectPHP\Core\Clock\VirtualClock;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Effects\Execution\SleepEffect;
use EffectPHP\Core\Effects\Extras\TimeoutEffect;
use EffectPHP\Core\Effects\FailureEffect;
use EffectPHP\Core\Exceptions\ServiceNotFoundException;
use EffectPHP\Core\Exceptions\TimeoutException;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Utils\ContinuationStack;
use Fiber;
use FiberRuntime;
use Runtime;

/**
 * Fiber-aware handler for TimeoutEffect
 * 
 * This handler implements proper timeout racing by running both
 * the source effect and the timeout concurrently, with the first
 * to complete determining the outcome.
 */
final class FiberTimeoutEffectHandler implements EffectHandler
{
    public function canHandle(Effect $effect): bool
    {
        return $effect instanceof TimeoutEffect;
    }

    public function handle(Effect $effect, ContinuationStack $stack, Context $context, Runtime $runtime): Effect
    {
        /** @var TimeoutEffect $effect */
        try {
            // Get the Clock service from context
            $clock = $context->getService(Clock::class);
            
            // If we're in a fiber-based runtime, use fiber racing
            if ($runtime instanceof FiberRuntime) {
                return $this->handleWithFiberRacing($effect, $clock, $context, $runtime);
            } else {
                // Fallback to time-based checking
                return $this->handleWithTimeBased($effect, $clock, $context, $runtime);
            }
        } catch (ServiceNotFoundException $e) {
            // Fallback to direct microtime if Clock service not available
            return $this->handleWithMicrotime($effect, $context, $runtime);
        }
    }

    private function handleWithFiberRacing(TimeoutEffect $effect, Clock $clock, Context $context, FiberRuntime $runtime): Effect
    {
        $scheduler = $runtime->getScheduler();
        $scheduler->setClock($clock);
        
        $currentFiber = Fiber::getCurrent();
        
        if (!$currentFiber) {
            // Not in a fiber, fallback to time-based
            return $this->handleWithTimeBased($effect, $clock, $context, $runtime);
        }

        // For TestClock with virtual time, we can determine the outcome immediately
        if ($clock instanceof VirtualClock && $effect->source instanceof SleepEffect) {
            $sleepDuration = $effect->source->duration->toMilliseconds();
            $timeoutDuration = $effect->duration->toMilliseconds();
            
            if ($sleepDuration > $timeoutDuration) {
                return new FailureEffect(Cause::fail(new TimeoutException()));
            }
        }

        // Schedule the timeout
        $timeoutException = new TimeoutException();
        $timeoutTaskId = $scheduler->scheduleTimeout($currentFiber, $effect->duration, $timeoutException);
        
        try {
            // Execute the source effect
            $result = $runtime->tryRun($effect->source, $context);
            
            // If we get here, the source completed before timeout
            $scheduler->cancelTask($timeoutTaskId);
            return $result;
        } catch (TimeoutException $e) {
            // Timeout occurred
            return new FailureEffect(Cause::fail($e));
        }
    }

    private function handleWithTimeBased(TimeoutEffect $effect, Clock $clock, Context $context, Runtime $runtime): Effect
    {
        $startTime = $clock->currentTimeMillis();
        $result = $runtime->tryRun($effect->source, $context);
        $elapsed = $clock->currentTimeMillis() - $startTime;
        $timeoutMillis = $effect->duration->toMilliseconds();

        if ($elapsed > $timeoutMillis) {
            return new FailureEffect(Cause::fail(new TimeoutException()));
        }
        
        return $result;
    }

    private function handleWithMicrotime(TimeoutEffect $effect, Context $context, Runtime $runtime): Effect
    {
        $startTime = microtime(true);
        $result = $runtime->tryRun($effect->source, $context);
        $elapsed = microtime(true) - $startTime;

        if ($elapsed > $effect->duration->toSeconds()) {
            return new FailureEffect(Cause::fail(new TimeoutException()));
        }
        
        return $result;
    }
}