<?php

namespace EffectPHP\Core\RuntimeV2\Strategies;

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
 * Simple, fully synchronous execution strategy.
 *
 * ‑ Blocks the current thread during sleep() via the provided Clock.
 * ‑ Executes effects immediately without task scheduling.
 *
 * Suitable for CLI tools, scripts, or environments where Fibers are
 * unavailable / unnecessary.
 */
final class SyncExecutionStrategy implements ExecutionStrategy
{
    private Clock $clock;
    private PromiseFactoryInterface $promiseFactory;

    public function __construct(
        ?Clock $clock = null,
        ?PromiseFactoryInterface $promiseFactory = null,
    ) {
        $this->clock = $clock ?? new SystemClock();
        $this->promiseFactory = $promiseFactory ?? new SyncPromiseAdapter();
    }

    public function sleep(Duration $duration): void {
        // Delegates to clock – SystemClock blocks, VirtualClock advances instantly.
        $this->clock->sleep($duration, static fn() => null);
    }

    public function suspend(Closure $continuation): mixed {
        // In synchronous mode we just invoke the continuation immediately.
        return $continuation();
    }

    public function fork(Effect $effect, Runtime $runtime): ExecutionControl {
        // Even though the outer runtime is synchronous, we still fork the effect
        // into a PHP Fiber so that child computations can run independently and
        // be awaited via ExecutionControl.
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
