<?php

declare(strict_types=1);

namespace EffectPHP\Core\RuntimeV2\Contracts;

/**
 * Control mechanism for managing forked effects
 * 
 * Equivalent to EffectTS RuntimeFiber, but made more generic to
 * allow for different execution models (e.g., async, parallel)
 * and execution strategies and environments.
 */
interface ExecutionControl
{
    /**
     * Wait for completion and return result
     *
     * @template A
     * @return A
     * @throws \Throwable
     */
    public function await(): mixed;
    
    /**
     * Cancel the execution
     * 
     * @return void
     */
    public function cancel(): void;
    
    /**
     * Check if execution is still ongoing
     * 
     * @return bool
     */
    public function isRunning(): bool;
    
    /**
     * Check if execution completed successfully
     * 
     * @return bool
     */
    public function isCompleted(): bool;
    
    /**
     * Check if execution was cancelled
     * 
     * @return bool
     */
    public function isCancelled(): bool;
}