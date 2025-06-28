<?php

declare(strict_types=1);

namespace EffectPHP\Core\Contracts;

/**
 * Handle for managing forked effects
 * 
 * Equivalent to EffectTS RuntimeFiber
 */
interface FiberHandle
{
    /**
     * Wait for fiber completion and return result
     * 
     * @return mixed
     * @throws \Throwable
     */
    public function await(): mixed;
    
    /**
     * Cancel the running fiber
     * 
     * @return void
     */
    public function cancel(): void;
    
    /**
     * Check if fiber is still running
     * 
     * @return bool
     */
    public function isRunning(): bool;
    
    /**
     * Check if fiber completed successfully
     * 
     * @return bool
     */
    public function isCompleted(): bool;
    
    /**
     * Check if fiber was cancelled
     * 
     * @return bool
     */
    public function isCancelled(): bool;
}