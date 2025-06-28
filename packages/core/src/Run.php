<?php

namespace EffectPHP\Core;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\FiberHandle;
use EffectPHP\Core\Contracts\Promise;
use EffectPHP\Core\Result\Result;
use EffectPHP\Core\Runtime\RuntimeManager;

class Run
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
    public static function sync(Effect $effect): mixed
    {
        return RuntimeManager::default()->runSync($effect);
    }

    /**
     * Execute effect and return Promise
     *
     * Equivalent to EffectTS Effect.runPromise()
     *
     * @template A
     * @param Effect<never, mixed, A> $effect
     * @return Promise<A>
     */
    public static function promise(Effect $effect): Promise
    {
        return RuntimeManager::default()->runPromise($effect);
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
    public static function callback(Effect $effect, callable $callback): void
    {
        RuntimeManager::default()->runCallback($effect, $callback);
    }

    /**
     * Fork effect and return handle for concurrent management
     *
     * Equivalent to EffectTS Effect.runFork()
     *
     * @template A
     * @param Effect<never, mixed, A> $effect
     * @return FiberHandle<A>
     */
    public static function fork(Effect $effect): FiberHandle
    {
        return RuntimeManager::default()->runFork($effect);
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
    public static function syncResult(Effect $effect): Result
    {
        return RuntimeManager::default()->runSyncResult($effect);
    }

    /**
     * Execute effect and return Promise that resolves to Result
     *
     * Equivalent to EffectTS Effect.runPromiseResult()
     *
     * @template A
     * @param Effect<never, mixed, A> $effect
     * @return Promise<Result<A>>
     */
    public static function promiseResult(Effect $effect): Promise
    {
        return RuntimeManager::default()->runPromiseResult($effect);
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
    public static function safely(Effect $effect): Result
    {
        return RuntimeManager::default()->runSafely($effect);
    }
}