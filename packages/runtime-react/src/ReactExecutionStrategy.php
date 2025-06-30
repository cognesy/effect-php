<?php
namespace EffectPHP\React;

use EffectPHP\Core\RuntimeV1\Strategies\Closure;
use EffectPHP\Core\RuntimeV1\Strategies\Duration;
use EffectPHP\Core\RuntimeV1\Strategies\Effect;
use EffectPHP\Core\RuntimeV1\Strategies\ExecutionControl;
use EffectPHP\Core\RuntimeV1\Strategies\ExecutionControlAdapter;
use EffectPHP\Core\RuntimeV1\Strategies\ExecutionStrategy;
use EffectPHP\Core\RuntimeV1\Strategies\Fiber;
use EffectPHP\Core\RuntimeV1\Strategies\PromiseInterface;
use EffectPHP\Core\RuntimeV1\Strategies\Runtime;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred as ReactDeferred;

final class ReactExecutionStrategy implements ExecutionStrategy
{
    public function __construct(private readonly LoopInterface $eventLoop)
    {
    }

    public function sleep(Duration $duration): void
    {
        $currentFiber = Fiber::getCurrent();
        $this->eventLoop->addTimer($duration->asSeconds(), static function () use ($currentFiber): void {
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
        $error  = null;

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
        $this->eventLoop->futureTick(static fn () => $fiber->start());
        return new ExecutionControlAdapter($fiber);
    }

    public function async(Closure $callback): PromiseInterface
    {
        $deferred = new ReactDeferred();
        $this->eventLoop->futureTick(static function () use ($callback, $deferred): void {
            try {
                $deferred->resolve($callback());
            } catch (\Throwable $throwable) {
                $deferred->reject($throwable);
            }
        });
        return $deferred->promise();
    }

    public function now(): int
    {
        return (int) (microtime(true) * 1_000);
    }
}
