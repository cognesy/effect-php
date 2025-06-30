<?php

declare(strict_types=1);

namespace EffectPHP\Core\Effects;

use Closure;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Effects\Extras\FromCallableEffect;
use EffectPHP\Core\Effects\Extras\RetryEffect;
use EffectPHP\Core\Effects\Extras\TapEffect;
use EffectPHP\Core\Effects\Extras\TimeoutEffect;
use EffectPHP\Core\Schedule\Schedule;
use EffectPHP\Core\Utils\Duration;

/**
 * Stack-safe effect implementation with continuation fusion
 *
 * @template R
 * @template E of \Throwable
 * @template A
 * @implements Effect<R, E, A>
 */
abstract class BaseEffect implements Effect
{
    use Traits\HandlesExecution;
    use Traits\HandlesLayers;
    use Traits\HandlesFilters;
    use Traits\HandlesResources;

    public function map(callable $mapper): Effect {
        return new MapEffect($this, $mapper);
    }

    public function flatMap(callable $chain): Effect {
        return new FlatMapEffect($this, $chain);
    }

    public function catchError(string|callable $errorType, callable $handler): Effect {
        return new CatchEffect($this, $errorType, $handler);
    }

    public function orElse(Effect $fallback): Effect {
        return new OrElseEffect($this, $fallback);
    }

    public function ensuring(callable $cleanup): EnsuringEffect {
        return new EnsuringEffect($this, $cleanup);
    }

    public function timeoutAfter(Duration $timeout): TimeoutEffect {
        return new TimeoutEffect($this, $timeout);
    }

    public function retryWith(Schedule $schedule): RetryEffect {
        return new RetryEffect($this, $schedule);
    }

    /**
     * Execute side effect without changing the value
     *
     * @param callable(A): mixed|Effect<mixed, mixed, mixed> $sideEffect
     * @return Effect<R, E, A>
     */
    public function tap(callable|Effect $sideEffect): TapEffect {
        return new TapEffect($this, $sideEffect);
    }

    public function whenSucceeds(callable $sideEffect): Effect {
        return $this->flatMap(fn($value) => $sideEffect($value)->map(fn() => $value),
        );
    }

    /**
     * Lift a callable into an Effect
     *
     * @template A1
     * @param callable(): A1 $computation
     * @param mixed ...$args
     * @return Effect<never, \Throwable, A1>
     */
    public static function fromCallable(callable $computation, mixed ...$args): FromCallableEffect {
        return new FromCallableEffect($computation, $args);
    }

    /**
     * Create effect that calls callable with provided arguments
     *
     * @template A1
     * @param callable $computation
     * @param array $args
     * @return Effect<never, \Throwable, A1>
     */
    public static function call(callable $computation, array $args = []): FromCallableEffect {
        return new FromCallableEffect($computation, $args);
    }

    /**
     * Create effect from closure/anonymous function
     *
     * @template A1
     * @param \Closure(): A1 $closure
     * @return Effect<never, \Throwable, A1>
     */
    public static function suspend(Closure $closure): FromCallableEffect {
        return new FromCallableEffect($closure);
    }
}