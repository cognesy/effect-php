<?php

namespace EffectPHP\Workerman;

use Closure;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\RuntimeV2\Contracts\ExecutionControl;
use EffectPHP\Core\RuntimeV2\Contracts\ExecutionStrategy;
use EffectPHP\Core\RuntimeV2\ExecutionControl\FiberExecutionControl;
use EffectPHP\Core\Utils\Duration;
use Fiber;
use React\Promise\PromiseInterface;
use Workerman\Timer;

final class WorkermanExecutionStrategy implements ExecutionStrategy
{
    public function sleep(Duration $duration): void {
        $currentFiber = Fiber::getCurrent();
        Timer::add($duration->toSeconds(), static function () use ($currentFiber): void {
            if ($currentFiber->isSuspended()) {
                $currentFiber->resume();
            }
        }, [], false);
        Fiber::suspend();
    }

    public function suspend(Closure $continuation): mixed {
        $currentFiber = Fiber::getCurrent();
        $result = null;
        $error = null;

        ($continuation)(static function (mixed $value = null, ?\Throwable $throwable = null) use (&$result, &$error, $currentFiber): void {
            $result = $value;
            $error = $throwable;
            if ($currentFiber->isSuspended()) {
                $currentFiber->resume();
            }
        });

        Fiber::suspend();

        if ($error instanceof \Throwable) {
            throw $error;
        }
        return $result;
    }

    public function fork(Effect $effect, Runtime $runtime): ExecutionControl {
        $currentFiber = Fiber::getCurrent();
        $result = null;
        $error = null;

        $effect->run(static function (mixed $value = null, ?\Throwable $throwable = null) use (&$result, &$error, $currentFiber): void {
            $result = $value;
            $error = $throwable;
            if ($currentFiber->isSuspended()) {
                $currentFiber->resume();
            }
        });

        Fiber::suspend();

        if ($error instanceof \Throwable) {
            throw $error;
        }
        return new FiberExecutionControl($currentFiber);
    }

    public function defer(Closure $callback): PromiseInterface {
    }

    public function now(): int {
        return (int)(microtime(true) * 1_000);
    }
}
