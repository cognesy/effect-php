<?php declare(strict_types=1);

namespace EffectPHP\Utils\Clock;

use EffectPHP\Utils\Duration;

/**
 * Clock service provides time-related operations with abstraction for testing
 * 
 * This provides:
 * - Time passage independence in tests via TestClock
 * - Precise timing control for time-dependent effects
 * - Sleep scheduling that respects virtual vs real time
 */
interface Clock
{
    /**
     * Get current time in milliseconds since Unix epoch
     * 
     * @return int Current time in milliseconds
     */
    public function currentTimeMillis(): int;

    /**
     * Get high-resolution time in nanoseconds for precise measurements
     * 
     * @return int High-resolution time in nanoseconds
     */
    public function nanoTime(): int;

    /**
     * Sleep for the specified duration
     * 
     * This method represents a sleep operation that respects the clock's
     * time model. In real-time clocks, this blocks. In test clocks,
     * this schedules the continuation for when time advances.
     * 
     * @param Duration $duration Time to sleep
     * @return void
     */
    public function sleep(Duration $duration): void;

    /**
     * Advance time (for virtual clocks only)
     * 
     * Real-time clocks ignore this. Virtual clocks advance their
     * internal time and execute any scheduled continuations.
     * 
     * @param Duration $duration Amount to advance time
     * @return void
     */
    public function advance(Duration $duration): void;
}