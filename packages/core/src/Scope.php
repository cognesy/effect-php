<?php

declare(strict_types=1);

namespace EffectPHP\Core;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Effects\Extras\ScopeEffect;
use WeakMap;

/**
 * Resource management with guaranteed cleanup
 * 
 * - Uses __destruct() for automatic cleanup when scope object destroyed
 * - WeakMap for automatic finalizer cleanup
 * - Explicit closure capture with use() keyword
 * - RAII-style resource management
 * - Pure Effect composition - no direct runtime execution
 * - Finalizers executed in reverse order
 * - Handles success/failure/interruption cases
 */
final class Scope
{
    private static WeakMap $finalizers;
    private array $cleanupActions = [];
    private bool $closed = false;

    public function __construct()
    {
        // Initialize WeakMap if not exists (PHP8 WeakMap advantage)
        self::$finalizers ??= new WeakMap();
        self::$finalizers[$this] = [];
    }

    /**
     * Automatic cleanup when scope object destroyed
     */
    public function __destruct()
    {
        if (!$this->closed) {
            // Emergency cleanup if scope wasn't properly closed
            // In production, this should log a warning
            $this->executeCleanup();
        }
    }

    /**
     * Add cleanup action (follows EffectTS addFinalizer pattern)
     * 
     * @param callable(): Effect<mixed, never, mixed> $finalizer
     * @return Effect<never, never, null>
     */
    public function addFinalizer(callable $finalizer): Effect
    {
        if ($this->closed) {
            return Eff::fail(new \LogicException('Cannot add finalizer to closed scope'));
        }

        $this->cleanupActions[] = $finalizer;
        return Eff::succeed(null);
    }

    /**
     * Acquire resource with guaranteed release (bracket pattern)
     * 
     * @template R
     * @template E of \Throwable
     * @template A
     * @param Effect<R, E, A> $acquire
     * @param callable(A): Effect<mixed, never, mixed> $release
     * @return Effect<R, E, A>
     */
    public function acquireResource(Effect $acquire, callable $release): Effect
    {
        return $acquire->flatMap(function($resource) use ($release) {
            return $this->addFinalizer(fn() => $release($resource))
                ->map(fn() => $resource);
        });
    }

    /**
     * Close scope - returns Effect for composition (EffectTS spirit)
     *
     * @psalm-return Effect<never, \Throwable, mixed>|Effect<never, never, null>
     */
    public function close(): Effect
    {
        if ($this->closed) {
            return Eff::succeed(null);
        }

        $this->closed = true;
        
        // Build Effect chain for cleanup (pure composition)
        $cleanup = Eff::succeed(null);
        
        // Execute finalizers in reverse order (EffectTS pattern)
        foreach (array_reverse($this->cleanupActions) as $finalizer) {
            $cleanup = $cleanup->flatMap(fn() => $finalizer());
        }

        return $cleanup;
    }

    /**
     * Create scoped effect (follows EffectTS Effect.scoped pattern)
     *
     * @template A
     *
     * @param callable(Scope): Effect<mixed, mixed, A> $scoped
     */
    public static function make(callable $scoped): ScopeEffect
    {
        return new ScopeEffect($scoped);
    }

    /**
     * Internal cleanup execution (for __destruct)
     */
    private function executeCleanup(): void
    {
        $this->closed = true;
        
        // Emergency cleanup - execute finalizers synchronously
        // This breaks the Effect pattern but ensures cleanup in __destruct
        foreach (array_reverse($this->cleanupActions) as $finalizer) {
            try {
                // In emergency cleanup, we can't honor Effect composition
                // This is a PHP8 adaptation - finalizers should be simple cleanup
                $finalizer();
            } catch (\Throwable $e) {
                // Log error but continue cleanup
                error_log("Scope cleanup error: " . $e->getMessage());
            }
        }
    }
}