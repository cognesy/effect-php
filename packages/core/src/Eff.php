<?php

declare(strict_types=1);

namespace EffectPHP\Core;

use EffectPHP\Core\Contracts\Clock;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\FiberHandle;
use EffectPHP\Core\Contracts\Promise;
use EffectPHP\Core\Exceptions\ServiceNotFoundException;
use EffectPHP\Core\Result\Result;
use EffectPHP\Core\Effects\AsyncPromiseEffect;
use EffectPHP\Core\Effects\FailureEffect;
use EffectPHP\Core\Effects\NeverEffect;
use EffectPHP\Core\Effects\ParallelEffect;
use EffectPHP\Core\Effects\RaceEffect;
use EffectPHP\Core\Effects\ServiceAccessEffect;
use EffectPHP\Core\Effects\SleepEffect;
use EffectPHP\Core\Effects\SuccessEffect;
use EffectPHP\Core\Effects\SyncEffect;
use EffectPHP\Core\Effects\SuspendEffect;
use EffectPHP\Core\Cause\Cause;
use EffectPHP\Core\Runtime\RuntimeManager;
use EffectPHP\Core\Utils\Duration;
use EffectPHP\Core\Exceptions\UnknownException;
use Throwable;

/**
 * Effect factory
 */
final class Eff
{
    /**
     * Lift pure value into Effect with zero cost
     *
     * @template A
     * @param A $value
     * @return Effect<never, never, A>
     */
    public static function succeed(mixed $value): Effect
    {
        return new SuccessEffect($value);
    }

    /**
     * Create failed Effect with structured cause
     *
     * @template E of Throwable
     *
     * @param Throwable $error
     *
     * @psalm-return FailureEffect<Throwable>
     */
    public static function fail(Throwable $error): FailureEffect
    {
        return new FailureEffect(Cause::fail($error));
    }

    /**
     * Lift synchronous computation with error handling
     *
     * @template A
     *
     * @param callable(): A $computation
     */
    public static function sync(callable $computation): SyncEffect
    {
        return new SyncEffect($computation);
    }

    /**
     * Create effect from computation that might throw
     *
     * @template A
     * @param callable(): A $computation
     * @return Effect<never, UnknownException, A>
     */
    public static function try(callable $computation): Effect
    {
        return new SyncEffect(function() use ($computation) {
            try {
                return $computation();
            } catch (Throwable $e) {
                throw new UnknownException($e->getMessage(), 0, $e);
            }
        });
    }

    /**
     * Create effect from computation that might throw with custom error handler
     *
     * @template A
     * @template E of Throwable
     * @param callable(): A $computation
     * @param callable(Throwable): E $catch
     * @return Effect<never, E, A>
     */
    public static function tryWithCatch(callable $computation, callable $catch): Effect
    {
        return new SyncEffect(function() use ($computation, $catch) {
            try {
                return $computation();
            } catch (Throwable $e) {
                throw $catch($e);
            }
        });
    }

    /**
     * Create suspended effect for lazy evaluation
     *
     * @template R
     * @template E of Throwable
     * @template A
     * @param callable(): Effect<R, E, A> $computation
     * @return Effect<R, E, A>
     */
    public static function suspend(callable $computation): Effect
    {
        return new SuspendEffect(new SuccessEffect(null), $computation);
    }


    /**
     * Create effect from async computation that cannot fail
     *
     * @template A
     * @param callable(): Promise<A> $computation
     * @return Effect<never, never, A>
     */
    public static function promise(callable $computation): Effect
    {
        return new AsyncPromiseEffect($computation);
    }

    /**
     * Create effect from async computation that might fail
     *
     * @template A
     * @param callable(): Promise<A> $computation
     * @return Effect<never, UnknownException, A>
     */
    public static function tryPromise(callable $computation): Effect
    {
        return new AsyncPromiseEffect(
            $computation,
            fn(Throwable $e) => new UnknownException($e->getMessage(), 0, $e)
        );
    }

    /**
     * Create effect from async computation that might fail with custom error handler
     *
     * @template A
     * @template E of Throwable
     * @param callable(): Promise<A> $computation
     * @param callable(Throwable): E $catch
     * @return Effect<never, E, A>
     */
    public static function tryPromiseWith(callable $computation, callable $catch): Effect
    {
        return new AsyncPromiseEffect($computation, $catch);
    }

    /**
     * Access service from context
     *
     * @template T
     *
     * @param class-string<T> $serviceTag
     */
    public static function service(string $serviceTag): ServiceAccessEffect
    {
        return new ServiceAccessEffect($serviceTag);
    }

    /**
     * Access Clock service from context
     * 
     * Provides access to the Clock service for time-dependent operations.
     * In tests, this can be replaced with TestClock for time control.
     *
     * @return Effect<Clock, ServiceNotFoundException, Clock>
     */
    public static function clock(): Effect
    {
        return self::service(Clock::class);
    }

    /**
     * Get current time in milliseconds using Clock service
     * 
     * @return Effect<Clock, ServiceNotFoundException, int>
     */
    public static function currentTimeMillis(): Effect
    {
        return self::clock()->map(fn(Clock $clock) => $clock->currentTimeMillis());
    }

    /**
     * Execute an effect with access to Clock service
     * 
     * Similar to EffectTS Effect.clockWith pattern
     * 
     * @template A
     * @param callable(Clock): Effect<mixed, mixed, A> $f
     * @return Effect<Clock, ServiceNotFoundException, A>
     */
    public static function clockWith(callable $f): Effect
    {
        return self::clock()->flatMap($f);
    }

    /**
     * Execute effects in parallel with type safety
     *
     * @template A
     *
     * @param array $effects
     *
     * @return Effect<mixed, mixed, A[]>
     */
    public static function allInParallel(array $effects): Effect
    {
        return new ParallelEffect($effects);
    }

    /**
     * Race multiple effects
     *
     * @template A
     *
     * @param array $effects
     */
    public static function raceAll(array $effects): RaceEffect
    {
        return new RaceEffect($effects);
    }

    /**
     * Sleep for specified duration
     *
     * @param Duration $duration
     */
    public static function sleepFor(Duration $duration): SleepEffect
    {
        return new SleepEffect($duration);
    }

    /**
     * Effect that never completes
     */
    public static function never(): NeverEffect
    {
        return NeverEffect::instance();
    }

    /**
     * Conditional effect execution
     *
     * @template A
     * @param bool $condition
     * @param Effect<mixed, mixed, A> $effect
     * @return Effect<mixed, never, A|null>
     */
    public static function when(bool $condition, Effect $effect): Effect
    {
        return $condition ? $effect : self::succeed(null);
    }

    /**
     * Convert Option to Effect
     *
     * @template A
     * @param Option<A> $option
     * @param Throwable $whenEmpty
     * @return Effect<never, Throwable, A>
     */
    public static function fromOption(Option $option, Throwable $whenEmpty): Effect
    {
        return $option->toEffect($whenEmpty);
    }

    /**
     * Convert Either to Effect
     *
     * @template L
     * @template R
     * @param Either<L, R> $either
     * @return Effect<never, L, R>
     */
    public static function fromEither(Either $either): Effect
    {
        return $either->toEffect();
    }

    /**
     * Create scoped effect with automatic resource cleanup
     *
     * @template A
     * @param callable(Scope): Effect<mixed, mixed, A> $scoped
     * @return Effect<mixed, mixed, A>
     */
    public static function scoped(callable $scoped): Effect
    {
        return Scope::make($scoped);
    }

    // ===== EXECUTION METHODS (EffectTS-style API) =====

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
    public static function runSync(Effect $effect): mixed
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
    public static function runPromise(Effect $effect): Promise
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
    public static function runCallback(Effect $effect, callable $callback): void
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
    public static function runFork(Effect $effect): FiberHandle
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
    public static function runSyncResult(Effect $effect): Result
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
    public static function runPromiseResult(Effect $effect): Promise
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
    public static function runSafely(Effect $effect): Result
    {
        return RuntimeManager::default()->runSafely($effect);
    }

    /**
     * Execute effect synchronously (alias for runSync)
     * 
     * @template A
     * @param Effect<never, mixed, A> $effect
     * @return A
     * @throws \Throwable
     */
    public static function run(Effect $effect): mixed
    {
        return self::runSync($effect);
    }
}