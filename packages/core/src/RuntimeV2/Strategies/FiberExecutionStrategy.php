<?php

namespace EffectPHP\Core\RuntimeV2\Strategies;

/* ------------------------------------------------------------------------- */

use Closure;
use EffectPHP\Core\Clock\Clock;
use EffectPHP\Core\Clock\SystemClock;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Promise\PromiseFactoryInterface;
use EffectPHP\Core\Promise\PromiseInterface;
use EffectPHP\Core\Promise\SyncPromiseAdapter;
use EffectPHP\Core\RuntimeV2\Contracts\ExecutionControl;
use EffectPHP\Core\RuntimeV2\Contracts\ExecutionStrategy;
use EffectPHP\Core\RuntimeV2\ExecutionControl\FiberExecutionControl;
use EffectPHP\Core\Utils\Duration;
use Fiber;

/**
 * Cooperative, fiber‑based execution strategy.
 *
 * ‑ Uses Clock::sleep() to register a continuation that resumes the current
 *   fiber when the (virtual or real) sleep completes.
 * ‑ Implements suspend() by capturing the current fiber and resuming it via a
 *   trampoline continuation supplied to the caller.
 *
 * When paired with VirtualClock this strategy enables deterministic, fast
 * tests; when paired with SystemClock it behaves like an async runtime that
 * still blocks the thread during sleep, but keeps the API uniform.
 */
final class FiberExecutionStrategy implements ExecutionStrategy
{
    private Clock $clock;
    private readonly PromiseFactoryInterface $promiseFactory;

    public function __construct(
        ?Clock $clock,
        ?PromiseFactoryInterface $promiseFactory,
    ) {
        $this->clock = $clock ?? new SystemClock();
        $this->promiseFactory = $promiseFactory ?? new SyncPromiseAdapter();
    }

    public function sleep(Duration $duration): void {
        $current = Fiber::getCurrent();

        // Register continuation with the clock; the continuation will resume
        // this fiber once the clock says the sleep has elapsed.
        $this->clock->sleep($duration, static function () use ($current): void {
            if ($current->isSuspended()) {
                $current->resume();
            }
        });

        // Suspend until the continuation above resumes us.
        Fiber::suspend();
    }

    public function suspend(Closure $continuation): mixed {
        $current = Fiber::getCurrent();
        $result = null;
        $error = null;

        // Provide the caller a function capturing the current fiber context.
        $continuation(static function (mixed $value = null, ?\Throwable $throwable = null) use (&$result, &$error, $current): void {
            $result = $value;
            $error = $throwable;
            if ($current->isSuspended()) {
                $current->resume();
            }
        });

        // Park this fiber until the supplied continuation resumes it.
        Fiber::suspend();

        if ($error instanceof \Throwable) {
            throw $error;
        }
        return $result;
    }

    public function fork(Effect $effect, Runtime $runtime): ExecutionControl {
        $fiber = new Fiber(static fn() => $runtime->run($effect));
        $fiber->start();
        return new FiberExecutionControl($fiber);
    }

    public function defer(Closure $callback): PromiseInterface {
        return $this->promiseFactory->fromCallable($callback);
    }

    public function now(): int {
        return $this->clock->currentTimeMillis();
    }

    public function clock(): Clock {
        return $this->clock;
    }
}
