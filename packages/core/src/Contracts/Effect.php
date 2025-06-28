<?php

declare(strict_types=1);

namespace EffectPHP\Core\Contracts;

use EffectPHP\Core\Exceptions\TimeoutException;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Layer\Layer;
use EffectPHP\Core\Scope;
use EffectPHP\Core\Utils\Duration;
use EffectPHP\Core\Schedule\Schedule;
use Throwable;

/**
 * An immutable description of a computation that may:
 * - Require environment/dependencies R
 * - Fail with typed error E
 * - Succeed with value A
 *
 * @template R Environment requirements
 * @template E of Throwable Error type
 * @template A Success value type
 */
interface Effect
{
    /**
     * Transform the success value (Functor law)
     *
     * @template B
     * @param callable(A): B $mapper
     * @return Effect<R, E, B>
     */
    public function map(callable $mapper): Effect;

    /**
     * Chain dependent computations (Monad law)
     *
     * @template R2
     * @template E2 of Throwable
     * @template B
     * @param callable(A): Effect<R2, E2, B> $chain
     * @return Effect<R&R2, E|E2, B>
     */
    public function flatMap(callable $chain): Effect;


    /**
     * Handle typed errors with recovery
     *
     * @template R2
     * @template E2 of Throwable
     * @template A2
     * @param class-string<E>|callable(E): bool $errorType
     * @param callable(E): Effect<R2, E2, A2> $handler
     * @return Effect<R&R2, E2, A|A2>
     */
    public function catchError(string|callable $errorType, callable $handler): Effect;

    /**
     * Provide fallback with natural language naming
     *
     * @template R2
     * @template E2 of Throwable
     * @template A2
     * @param Effect<R2, E2, A2> $fallback
     * @return Effect<R&R2, E2, A|A2>
     */
    public function orElse(Effect $fallback): Effect;

    /**
     * Execute side effect while preserving value
     *
     * @param callable(A): Effect<mixed, never, mixed> $sideEffect
     * @return Effect<R, E, A>
     */
    public function whenSucceeds(callable $sideEffect): Effect;

    /**
     * Ensure cleanup runs regardless of outcome
     *
     * @param callable(): Effect<mixed, never, mixed> $cleanup
     * @return Effect<R, E, A>
     */
    public function ensuring(callable $cleanup): Effect;

    /**
     * Add timeout constraint
     *
     * @param Duration $timeout
     * @return Effect<R, E|TimeoutException, A>
     */
    public function timeoutAfter(Duration $timeout): Effect;

    /**
     * Retry with intelligent scheduling
     *
     * @param Schedule $schedule
     * @return Effect<R, E, A>
     */
    public function retryWith(Schedule $schedule): Effect;

    /**
     * Provide dependencies to eliminate requirements
     *
     * @template RProvided
     * @param Context<RProvided> $context
     * @return Effect<R&(~RProvided), E, A>
     */
    public function providedWith(Context $context): Effect;

    /**
     * Build layer and provide its services
     *
     * @template RLayer
     * @template ELayer of Throwable
     * @param Layer<RLayer, ELayer, R> $layer
     * @return Effect<RLayer, E|ELayer, A>
     */
    public function providedByLayer(Layer $layer): Effect;

    /**
     * Execute in managed scope with guaranteed cleanup
     *
     * @template B
     * @param callable(Scope): Effect<R, E, B> $scoped
     * @return Effect<R, E, B>
     */
    public function withinScope(callable $scoped): Effect;

    /**
     * Execute effects in parallel
     *
     * @template B
     * @param Effect<R, E, B> ...$others
     * @return Effect<R, E, array{A, B}>
     */
    public function zipWithPar(Effect ...$others): Effect;

    /**
     * Race multiple effects, return first to complete
     *
     * @template B
     * @param Effect<R, E, B> ...$competitors
     * @return Effect<R, E, A|B>
     */
    public function raceWith(Effect ...$competitors): Effect;
}