<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtime\Clock;

use EffectPHP\Core\Contracts\Clock;
use EffectPHP\Core\Utils\Duration;

/**
 * Test Clock implementation with virtual time control
 * 
 * Based on EffectTS TestClock, this implementation provides:
 * - Virtual time that only advances when explicitly adjusted
 * - Manages its own scheduled sleep continuations
 * - Deterministic and fast testing of time-dependent effects
 * 
 * The TestClock tracks scheduled sleep operations and executes their
 * continuations when virtual time advances past their scheduled time.
 */
final class TestClock implements Clock
{
    private int $currentTime = 0;
    private int $nanoTimeBase;
    
    /** @var array<int, array{continuation: callable, scheduledAt: int}> */
    private array $scheduledSleeps = [];
    private int $nextSleepId = 0;

    public function __construct(int $initialTime = 0)
    {
        $this->currentTime = $initialTime;
        $this->nanoTimeBase = hrtime(true);
    }

    public function currentTimeMillis(): int
    {
        return $this->currentTime;
    }

    public function sleep(Duration $duration, callable $continuation): void
    {
        // In TestClock, we immediately advance time and execute the continuation
        // This enables instant completion of sleep effects for fast, deterministic testing
        $this->advanceTo($this->currentTime + $duration->toMilliseconds());
        $continuation();
    }

    public function nanoTime(): int
    {
        // Calculate nano time based on current virtual time
        $elapsedMillis = $this->currentTime;
        return $this->nanoTimeBase + ($elapsedMillis * 1_000_000);
    }

    /**
     * Adjust the clock time by the specified duration
     * 
     * This is the key method that enables time passage independence.
     * When time is adjusted, all scheduled sleep continuations at or 
     * before the new time are executed immediately.
     * 
     * @param Duration $duration Amount to advance time
     * @return void
     */
    public function adjust(Duration $duration): void
    {
        $this->advanceTo($this->currentTime + $duration->toMilliseconds());
    }

    /**
     * Set clock to a specific absolute time
     * 
     * @param int $timeMillis Absolute time in milliseconds
     * @return void
     */
    public function setTime(int $timeMillis): void
    {
        if ($timeMillis < $this->currentTime) {
            throw new \InvalidArgumentException(
                "Cannot set time backwards from {$this->currentTime} to {$timeMillis}"
            );
        }
        
        $this->advanceTo($timeMillis);
    }

    /**
     * Get all currently scheduled sleeps (for debugging/testing)
     * 
     * @return array<int, array{continuation: callable, scheduledAt: int}>
     */
    public function getScheduledSleeps(): array
    {
        return $this->scheduledSleeps;
    }

    /**
     * Clear all scheduled sleeps
     * 
     * @return void
     */
    public function clearScheduledSleeps(): void
    {
        $this->scheduledSleeps = [];
    }

    /**
     * Check if there are any sleeps scheduled at or before the given time
     * 
     * @param int $timeMillis Time to check against
     * @return bool True if sleeps are ready to execute
     */
    public function hasSleepsReadyAt(int $timeMillis): bool
    {
        foreach ($this->scheduledSleeps as $sleep) {
            if ($sleep['scheduledAt'] <= $timeMillis) {
                return true;
            }
        }
        return false;
    }

    /**
     * Internal method to advance time and execute scheduled sleep continuations
     * 
     * @param int $newTime New absolute time
     * @return void
     */
    private function advanceTo(int $newTime): void
    {
        $this->currentTime = $newTime;
        
        // Execute all sleep continuations scheduled at or before current time
        $executedSleeps = [];
        
        foreach ($this->scheduledSleeps as $sleepId => $sleep) {
            if ($sleep['scheduledAt'] <= $this->currentTime) {
                $executedSleeps[] = $sleepId;
                ($sleep['continuation'])();
            }
        }
        
        // Remove executed sleeps
        foreach ($executedSleeps as $sleepId) {
            unset($this->scheduledSleeps[$sleepId]);
        }
    }
}