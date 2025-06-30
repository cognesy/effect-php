<?php

namespace EffectPHP\Revolt;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\RuntimeV1\Strategies\ExecutionControl;
use EffectPHP\Core\RuntimeV1\Strategies\ExecutionControlAdapter;
use EffectPHP\Core\RuntimeV1\Strategies\Promise;
use EffectPHP\Core\RuntimeV1\Strategies\PromiseInterface;
use EffectPHP\Core\RuntimeV2\Contracts\ExecutionStrategy;
use EffectPHP\Core\Utils\Duration;
use Revolt\EventLoop\Driver as RevoltDriver;
use Runtime;

final class RevoltExecutionStrategy implements ExecutionStrategy
{
    public function __construct(private readonly RevoltDriver $eventLoop)
    {
    }

    public function sleep(Duration $duration): void
    {
        $currentFiber = Fiber::getCurrent();
        $this->eventLoop->delay($duration->asSeconds(), static function () use ($currentFiber): void {
            if ($currentFiber->isSuspended()) {
                $currentFiber->resume();
            }
        });
        Fiber::suspend();
    }

    public function suspend(Closure $continuation): mixed
    {
        $currentFiber = Fiber::getCurrent();
        $result = null;
        $error = null;

        ($continuation)(static function (mixed $value = null, ?\Throwable $throwable = null) use (&$result, &$error, $currentFiber): void {
            $result = $value;
            $error  = $throwable;
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

    public function fork(Effect $effect, Runtime $runtime): ExecutionControl
    {
        $fiber = new Fiber(static fn () => $runtime->run($effect));
        $this->eventLoop->defer(static fn () => $fiber->start());
        return new ExecutionControlAdapter($fiber);
    }

    public function defer(Closure $callback): PromiseInterface
    {
        $promise = new Promise();
        $this->eventLoop->defer(static function () use ($callback, $promise): void {
            try {
                $promise->resolve($callback());
            } catch (\Throwable $throwable) {
                $promise->reject($throwable);
            }
        });
        return $promise;
    }

    public function now(): int
    {
        return (int) (microtime(true) * 1_000);
    }
}
