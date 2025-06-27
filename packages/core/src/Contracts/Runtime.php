<?php

declare(strict_types=1);

namespace EffectPHP\Core\Contracts;

use EffectPHP\Core\Either;
use EffectPHP\Core\Layer\Context;

/**
 * Runtime interface for executing Effects
 * 
 * A Runtime represents a system that can execute Effect programs with specific
 * execution models (synchronous, async, fiber-based, etc.)
 * 
 * Different runtime implementations can provide:
 * - Synchronous execution (blocking)
 * - Async execution with event loops (ReactPHP, Swoole, AmpPHP)
 * - Fiber-based cooperative multitasking
 * - Custom execution strategies
 */
interface Runtime
{
    /**
     * Execute effect safely, returning Either for error handling
     *
     * @template A
     * @template E of \Throwable
     * @param Effect<never, E, A> $effect
     * @return Either<E, A>
     */
    public function runSafely(Effect $effect): Either;

    /**
     * Execute effect, throwing on failure
     * 
     * @template A
     * @template E of \Throwable
     * @param Effect<never, E, A> $effect
     * @return A
     * @throws \Throwable
     */
    public function unsafeRun(Effect $effect): mixed;

    /**
     * Execute effect with specific context, returning result as Effect
     * 
     * @template A
     * @template E of \Throwable
     * @param Effect<never, E, A> $effect
     * @param Context $context
     * @return Effect<never, E, A>
     */
    public function tryRun(Effect $effect, Context $context): Effect;

    /**
     * Create a new runtime instance with different root context
     * 
     * @param Context $context
     * @return static
     */
    public function withContext(Context $context): static;

    /**
     * Get the runtime's name/identifier for debugging
     */
    public function getName(): string;
}