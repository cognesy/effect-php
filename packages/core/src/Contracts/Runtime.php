<?php

declare(strict_types=1);

namespace EffectPHP\Core\Contracts;

use EffectPHP\Core\Either;
use EffectPHP\Core\Result;
use EffectPHP\Core\Layer\Context;

/**
 * Runtime interface for executing Effects
 * 
 * Provides EffectTS-compatible execution modes:
 * - runSync: Synchronous blocking execution
 * - runPromise: Returns Promise for async integration
 * - runCallback: Node.js-style callback execution
 * - runFork: Returns FiberHandle for concurrent management
 */
interface Runtime
{
    // ===== EffectTS-style Execution APIs =====
    
    /**
     * Execute effect synchronously (blocking)
     * Equivalent to EffectTS Effect.runSync()
     * 
     * @template A
     * @param Effect<never, mixed, A> $effect
     * @return A
     * @throws \Throwable
     */
    public function runSync(Effect $effect): mixed;
    
    /**
     * Execute effect and return Promise
     * Equivalent to EffectTS Effect.runPromise()
     * 
     * @template A
     * @param Effect<never, mixed, A> $effect
     * @return Promise<A>
     */
    public function runPromise(Effect $effect): Promise;
    
    /**
     * Execute effect with callback
     * Equivalent to EffectTS Effect.runCallback()
     * 
     * @template A
     * @param Effect<never, mixed, A> $effect
     * @param callable(mixed|\Throwable, A|null): void $callback
     * @return void
     */
    public function runCallback(Effect $effect, callable $callback): void;
    
    /**
     * Fork effect and return handle for concurrent management
     * Equivalent to EffectTS Effect.runFork()
     * 
     * @template A
     * @param Effect<never, mixed, A> $effect
     * @return FiberHandle<A>
     */
    public function runFork(Effect $effect): FiberHandle;
    
    /**
     * Execute effect synchronously and return Result
     * Equivalent to EffectTS Effect.runSyncResult()
     * 
     * @template A
     * @param Effect<never, mixed, A> $effect
     * @return Result<A>
     */
    public function runSyncResult(Effect $effect): Result;
    
    /**
     * Execute effect and return Promise that resolves to Result
     * Equivalent to EffectTS Effect.runPromiseResult()
     * 
     * @template A
     * @param Effect<never, mixed, A> $effect
     * @return Promise<Result<A>>
     */
    public function runPromiseResult(Effect $effect): Promise;
    
    // ===== Legacy APIs (for backward compatibility) =====
    
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
     * Execute effect, throwing on failure (alias for runSync)
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