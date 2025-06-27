<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtime\Scheduler;

use EffectPHP\Core\Utils\Duration;

/**
 * Scheduler interface for managing task execution timing
 * 
 * Schedulers control when and how operations are executed,
 * enabling different execution strategies for different environments.
 */
interface Scheduler
{
    /**
     * Schedule a task to run after a delay
     */
    public function schedule(callable $task, Duration $delay): ScheduledTask;

    /**
     * Schedule a task to run immediately
     */
    public function scheduleImmediate(callable $task): ScheduledTask;

    /**
     * Process one tick of scheduled tasks
     */
    public function tick(): void;

    /**
     * Get the current time in milliseconds
     */
    public function currentTime(): int;

    /**
     * Advance time (for virtual schedulers)
     */
    public function advanceTime(Duration $duration): void;

    /**
     * Check if this scheduler supports virtual time
     */
    public function isVirtual(): bool;
}