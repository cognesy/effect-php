<?php

namespace EffectPHP\Core\RuntimeV2\Strategies;

use Closure;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\RuntimeV2\Contracts\ExecutionControl;
use EffectPHP\Core\RuntimeV2\Contracts\ExecutionStrategy;
use EffectPHP\Core\RuntimeV2\ExecutionControl\SyncExecutionControl;
use EffectPHP\Core\Utils\Duration;
use Fiber;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use SplPriorityQueue;

/**
 * Deterministic scheduler with a virtual clock for fast, repeatable tests.
 */
final class TestExecutionStrategy implements ExecutionStrategy
{
    private int $clockMillis = 0;
    private readonly SplPriorityQueue $queue;

    public function __construct() {
        $this->queue = new SplPriorityQueue();
        $this->queue->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
    }

    public function sleep(Duration $duration): void {
        $wakeAt = $this->clockMillis + (int) ($duration->toSeconds() * 1_000);
        $currentFiber = Fiber::getCurrent();
        $this->queue->insert($currentFiber, -$wakeAt);
        Fiber::suspend();
    }

    public function suspend(Closure $continuation): mixed {
        $currentFiber = Fiber::getCurrent();
        $result = null;
        $error = null;
        // Call the continuation with the current fiber context
        ($continuation)($this->makeContinuationWithContext($currentFiber, $result, $error));
        Fiber::suspend();
        if ($error instanceof \Throwable) {
            throw $error;
        }
        return $result;
    }

    public function fork(Effect $effect, Runtime $runtime): ExecutionControl {
        $fiber = new Fiber(static fn() => $runtime->run($effect));
        $fiber->start();
        return new SyncExecutionControl($fiber);
    }

    public function defer(Closure $callback): PromiseInterface {
        return new Promise(
            function (callable $resolve, callable $reject) use ($callback) {
                try {
                    $result = $callback();
                    if ($result instanceof PromiseInterface) {
                        $result->then($resolve, $reject);
                    } else {
                        $resolve($result);
                    }
                } catch (\Throwable $e) {
                    $reject($e);
                }
            }
        );
    }

    public function now(): int {
        return $this->clockMillis;
    }

    /**
     * Advance the virtual clock by the given milliseconds, executing any
     * scheduled fibers whose wake‑time has passed.
     */
    public function advanceTime(int $milliseconds): void {
        $target = $this->clockMillis + $milliseconds;
        while (!$this->queue->isEmpty() && (-$this->queue->top()['priority']) <= $target) {
            $entry = $this->queue->extract();
            $wakeAt = -$entry['priority'];
            $this->clockMillis = $wakeAt;
            /** @var Fiber $fiber */
            $fiber = $entry['data'];
            if ($fiber->isSuspended()) {
                $fiber->resume();
            }
        }
        $this->clockMillis = $target;
    }

    private function makeContinuationWithContext(?Fiber $currentFiber, mixed $result, mixed $error) {
        // create a continuation that captures the current fiber context
        return static function (mixed $value = null, ?\Throwable $throwable = null) use (&$result, &$error, $currentFiber): void {
            $result = $value;
            $error = $throwable;
            if ($currentFiber->isSuspended()) {
                $currentFiber->resume();
            }
        };
    }
}
