<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtime\Handlers;

use EffectPHP\Core\Cause\Cause;
use EffectPHP\Core\Contracts\Clock;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\FailureEffect;
use EffectPHP\Core\Effects\RetryEffect;
use EffectPHP\Core\Effects\SuccessEffect;
use EffectPHP\Core\Exceptions\ServiceNotFoundException;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Runtime\EffectHandler;
use EffectPHP\Core\Utils\Duration;
use EffectPHP\Core\Schedule\ExponentialNode;
use EffectPHP\Core\Schedule\FibonacciNode;
use EffectPHP\Core\Schedule\FixedDelayNode;
use EffectPHP\Core\Schedule\LinearNode;
use EffectPHP\Core\Schedule\OnceNode;
use EffectPHP\Core\Schedule\RepeatNode;
use EffectPHP\Core\Schedule\ScheduleNode;
use EffectPHP\Core\Utils\Fibonacci;
use LogicException;

final class RetryEffectHandler implements EffectHandler
{
    public function canHandle(Effect $effect): bool
    {
        return $effect instanceof RetryEffect;
    }

    public function handle(Effect $effect, array &$stack, Context $context, Runtime $runtime): Effect
    {
        /** @var RetryEffect $effect */
        $lastError = null;
        $attempt = 0;
        $schedule = $effect->schedule->getNode();

        // Get Clock service for delay operations
        try {
            $clock = $context->getService(Clock::class);
        } catch (ServiceNotFoundException $e) {
            $clock = null; // Fallback to direct usleep
        }

        while ($this->shouldRetry($schedule, $attempt)) {
            $result = $runtime->tryRun($effect->source, $context);
            if ($result instanceof SuccessEffect) {
                return $result;
            }

            $lastError = $result;
            $attempt++;

            if ($this->shouldRetry($schedule, $attempt)) {
                $delay = $this->getRetryDelay($schedule, $attempt);
                
                if ($clock !== null) {
                    // Use Clock service for sleep operation
                    $clock->sleepFor($delay);
                } else {
                    // Fallback to direct usleep
                    usleep($delay->toMicroseconds());
                }
            }
        }

        return $lastError ?? new FailureEffect(Cause::fail(new LogicException('Retry failed')));
    }

    private function shouldRetry(ScheduleNode $schedule, int $attempt): bool
    {
        if ($schedule instanceof OnceNode) {
            return $attempt < 1;
        }

        if ($schedule instanceof RepeatNode) {
            return $attempt < $schedule->times;
        }

        return $attempt < 3; // Default retry limit
    }

    private function getRetryDelay(ScheduleNode $schedule, int $attempt): Duration
    {
        if ($schedule instanceof FixedDelayNode) {
            return $schedule->delay;
        }

        if ($schedule instanceof ExponentialNode) {
            return Duration::milliseconds(
                (int)($schedule->base->toMilliseconds() * ($schedule->factor ** $attempt))
            );
        }

        if ($schedule instanceof FibonacciNode) {
            return $this->fibonacciDelay($schedule->base, $attempt);
        }

        if ($schedule instanceof LinearNode) {
            return $schedule->base->times($attempt + 1);
        }

        return Duration::milliseconds(100);
    }

    private function fibonacciDelay(Duration $base, int $n): Duration
    {
        $fib = Fibonacci::make($n)->get();
        return Duration::milliseconds($base->toMilliseconds() * $fib);
    }
}