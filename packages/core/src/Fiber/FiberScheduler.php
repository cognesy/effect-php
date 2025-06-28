<?php

declare(strict_types=1);

namespace EffectPHP\Core\Fiber;

use EffectPHP\Core\Contracts\Clock;
use EffectPHP\Core\Utils\Duration;
use Fiber;

/**
 * Scheduler for managing suspended fibers
 * 
 * This scheduler is responsible for:
 * - Tracking suspended fibers and their resume conditions
 * - Managing fiber suspension/resumption cycles
 * - Working with any Clock implementation through the Clock interface
 * 
 * Time scheduling is delegated to the Clock implementation.
 */
final class FiberScheduler
{
    /** @var array<string, mixed> */
    private array $suspendedFibers = [];
    
    /** @var array<string, mixed> */
    private array $resumeValues = [];

    /**
     * Suspend a fiber with a resume value
     */
    public function suspendFiber(Fiber $fiber, mixed $resumeValue = null): void
    {
        $fiberId = spl_object_hash($fiber);
        $this->suspendedFibers[$fiberId] = $fiber;
        if ($resumeValue !== null) {
            $this->resumeValues[$fiberId] = $resumeValue;
        }
    }

    /**
     * Mark a fiber for resumption with a value
     */
    public function resumeFiber(Fiber $fiber, mixed $resumeValue = null): void
    {
        $fiberId = spl_object_hash($fiber);
        if ($resumeValue !== null) {
            $this->resumeValues[$fiberId] = $resumeValue;
        }
    }

    /**
     * Process one tick of the scheduler
     * 
     * This just maintains the scheduler state - actual time-based
     * scheduling is handled by the Clock implementations.
     */
    public function tick(): void
    {
        // In the new design, the Clock handles scheduling
        // This method can be used for any non-time-based scheduling logic
    }

    /**
     * Check if a fiber should be resumed
     */
    public function shouldResumeFiber(Fiber $fiber): bool
    {
        $fiberId = spl_object_hash($fiber);
        return isset($this->resumeValues[$fiberId]);
    }

    /**
     * Get the resume value for a fiber
     */
    public function getResumeValue(Fiber $fiber): mixed
    {
        $fiberId = spl_object_hash($fiber);
        if (isset($this->resumeValues[$fiberId])) {
            $value = $this->resumeValues[$fiberId];
            unset($this->resumeValues[$fiberId]);
            unset($this->suspendedFibers[$fiberId]);
            
            // If it's a timeout exception, throw it
            if ($value instanceof \Throwable) {
                throw $value;
            }
            
            return $value;
        }
        return null;
    }

    /**
     * Get suspended fibers (for debugging)
     */
    public function getSuspendedFibers(): array
    {
        return $this->suspendedFibers;
    }
}

