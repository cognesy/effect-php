<?php
declare(strict_types=1);

namespace EffectPHP\Core;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Result\Result;
use EffectPHP\Core\RuntimeV2\Contracts\ExecutionControl;
use EffectPHP\Core\RuntimeV2\RuntimeFacade;
use EffectPHP\Core\RuntimeV2\RuntimeKernel;
use EffectPHP\Core\RuntimeV2\Strategies\SyncExecutionStrategy;
use React\Promise\PromiseInterface;

class Run2
{
    /**
     * Execute effect synchronously using default runtime
     *
     * Equivalent to EffectTS Effect.runSync()
     * Throws on failure - use runSafely() for error handling
     *
     * @template A
     * @param Effect<never, mixed, A> $effect
     * @return A
     * @throws \Throwable
     */
    public static function sync(Effect $effect): mixed {
        return self::runtime()->run($effect);
    }

    /**
     * Execute effect and return Promise
     *
     * Equivalent to EffectTS Effect.runPromise()
     *
     * @template A
     * @param Effect<never, mixed, A> $effect
     * @return PromiseInterface<A>
     */
    public static function promise(Effect $effect): PromiseInterface {
        return self::runtime()->promise($effect);
    }

    /**
     * Execute effect with callback
     *
     * Equivalent to EffectTS Effect.runCallback()
     *
     * @template A
     * @param Effect<never, mixed, A> $effect
     * @param callable(mixed|\Throwable, A|null): void $callback
     * @return void
     */
    public static function callback(Effect $effect, callable $callback): void {
        self::runtime()->callback($effect, $callback);
    }

    /**
     * Fork effect and return handle for concurrent management
     *
     * Equivalent to EffectTS Effect.runFork()
     *
     * @template A
     * @param Effect<never, mixed, A> $effect
     * @return \EffectPHP\Core\RuntimeV2\Contracts\ExecutionControl<A>
     */
    public static function fork(Effect $effect): ExecutionControl {
        return self::runtime()->fork($effect);
    }

    /**
     * Execute effect synchronously and return Result
     *
     * Equivalent to EffectTS Effect.runSyncResult()
     *
     * @template A
     * @param Effect<never, mixed, A> $effect
     * @return Result<A>
     */
    public static function syncResult(Effect $effect): Result {
        try {
            $result = self::runtime()->run($effect);
            return Result::succeed($result);
        } catch (\Throwable $e) {
            return Result::die($e);
        }
    }

    /**
     * Execute effect and return Promise that resolves to Result
     *
     * Equivalent to EffectTS Effect.runPromiseResult()
     *
     * @template A
     * @param Effect<never, mixed, A> $effect
     * @return PromiseInterface<Result<A>>
     */
    public static function promiseResult(Effect $effect): PromiseInterface {
        return self::runtime()->promise($effect)->then(
            function (mixed $value) {
                return Result::succeed($value);
            },
            function (\Throwable $error) {
                return Result::die($error);
            }
        );
    }

    /**
     * Execute effect safely using default runtime
     *
     * Returns Result<A> for safe error handling
     *
     * @template A
     * @param Effect<never, mixed, A> $effect
     * @return Result<A>
     */
    public static function safely(Effect $effect): Result {
        try {
            $result = self::runtime()->run($effect);
            return Result::succeed($result);
        } catch (\Throwable $e) {
            return Result::die($e);
        }
    }

    private static function runtime() : RuntimeFacade {
        return (new RuntimeFacade(new RuntimeKernel(new SyncExecutionStrategy())));
    }
}
