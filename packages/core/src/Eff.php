<?php

declare(strict_types=1);

namespace EffectPHP\Core;

use EffectPHP\Core\Contracts\Clock;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Exceptions\ServiceNotFoundException;
use EffectPHP\Core\Effects\AsyncMapEffect;
use EffectPHP\Core\Effects\FailureEffect;
use EffectPHP\Core\Effects\NeverEffect;
use EffectPHP\Core\Effects\ParallelEffect;
use EffectPHP\Core\Effects\RaceEffect;
use EffectPHP\Core\Effects\ServiceAccessEffect;
use EffectPHP\Core\Effects\SleepEffect;
use EffectPHP\Core\Effects\SuccessEffect;
use EffectPHP\Core\Effects\SyncEffect;
use EffectPHP\Core\Cause\Cause;
use EffectPHP\Core\Runtime\RuntimeManager;
use EffectPHP\Core\Utils\Duration;
use Throwable;

/**
 * Effect factory with superior DX and natural language
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
     * Lift async computation with proper fiber foundation
     *
     * @template A
     *
     * @param callable(): A $computation
     */
    public static function async(callable $computation): AsyncMapEffect
    {
        return new AsyncMapEffect(new SuccessEffect(null), $computation);
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
        return RuntimeManager::default()->unsafeRun($effect);
    }

    /**
     * Execute effect safely using default runtime
     * 
     * Equivalent to EffectTS Effect.runPromise() but returns Either
     * Returns Either<Error, Success> for safe error handling
     *
     * @template A
     * @template E
     * @param Effect<never, E, A> $effect
     * @return Either<E, A>
     */
    public static function runSafely(Effect $effect): Either
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