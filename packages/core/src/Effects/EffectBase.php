<?php

declare(strict_types=1);

namespace EffectPHP\Core\Effects;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Layer\Layer;
use EffectPHP\Core\Utils\Duration;
use EffectPHP\Core\Schedule\Schedule;

/**
 * Stack-safe effect implementation with continuation fusion
 *
 * @template R
 * @template E of \Throwable
 * @template A
 * @implements Effect<R, E, A>
 */
abstract class EffectBase implements Effect
{
    /**
     * Optimized map with continuation fusion
     */
    public function map(callable $mapper): Effect
    {
        return new MapEffect($this, $mapper);
    }

    public function mapAsync(callable $mapper): Effect
    {
        return new AsyncMapEffect($this, $mapper);
    }

    public function flatMap(callable $chain): Effect
    {
        return new FlatMapEffect($this, $chain);
    }

    public function catchError(string|callable $errorType, callable $handler): Effect
    {
        return new CatchEffect($this, $errorType, $handler);
    }

    public function orElse(Effect $fallback): Effect
    {
        return new OrElseEffect($this, $fallback);
    }

    public function whenSucceeds(callable $sideEffect): Effect
    {
        return $this->flatMap(fn($value) =>
            $sideEffect($value)->map(fn() => $value)
        );
    }

    public function ensuring(callable $cleanup): Effect
    {
        return new EnsuringEffect($this, $cleanup);
    }

    public function timeoutAfter(Duration $timeout): Effect
    {
        return new TimeoutEffect($this, $timeout);
    }

    public function retryWith(Schedule $schedule): Effect
    {
        return new RetryEffect($this, $schedule);
    }

    public function providedWith(Context $context): Effect
    {
        return new ProvideContextEffect($this, $context);
    }

    public function providedByLayer(Layer $layer): Effect
    {
        return $layer->build()->flatMap(fn($ctx) => $this->providedWith($ctx));
    }

    public function withinScope(callable $scoped): Effect
    {
        return new ScopeEffect($scoped);
    }

    public function zipWithPar(Effect ...$others): Effect
    {
        return new ParallelEffect([$this, ...$others]);
    }

    public function raceWith(Effect ...$competitors): Effect
    {
        return new RaceEffect([$this, ...$competitors]);
    }
}