<?php
namespace EffectPHP\AmPHP;

use Amp\DeferredFuture;
use Amp\Future;
use EffectPHP\Core\RuntimeV1\Strategies\Closure;
use EffectPHP\Core\RuntimeV1\Strategies\Duration;
use EffectPHP\Core\RuntimeV1\Strategies\Effect;
use EffectPHP\Core\RuntimeV1\Strategies\ExecutionControl;
use EffectPHP\Core\RuntimeV1\Strategies\ExecutionControlAdapter;
use EffectPHP\Core\RuntimeV1\Strategies\Fiber;
use EffectPHP\Core\RuntimeV1\Strategies\Promise;
use EffectPHP\Core\RuntimeV1\Strategies\PromiseInterface;
use EffectPHP\Core\RuntimeV1\Strategies\Runtime;
use EffectPHP\RuntimeV2\Contracts\ExecutionStrategy;

final class AmpExecutionStrategy implements ExecutionStrategy
{
    public function sleep(Duration $duration): void
    {
        \Amp\delay($duration->asSeconds());
    }

    public function suspend(Closure $continuation): mixed
    {
        $deferred = new DeferredFuture();
        ($continuation)(static function (mixed $value = null, ?\Throwable $throwable = null) use ($deferred): void {
            $throwable === null ? $deferred->complete($value) : $deferred->error($throwable);
        });
        return $deferred->getFuture()->await();
    }

    public function fork(Effect $effect, Runtime $runtime): ExecutionControl
    {
        $future = \Amp\async(static fn () => $runtime->run($effect));
        // Wrap future in fiber for structured‑concurrency semantics.
        $fiber = new Fiber(static fn () => $future->await());
        $fiber->start();
        return new ExecutionControlAdapter($fiber);
    }

    public function defer(Closure $callback): PromiseInterface
    {
        $future = \Amp\async($callback);
        return new Promise(static fn () => $future->await());
    }

    public function now(): int
    {
        return (int) (microtime(true) * 1_000);
    }
}
