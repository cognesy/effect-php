<?php declare(strict_types=1);

namespace EffectPHP\Utils\Clock;

use EffectPHP\Utils\Duration;

/**
 * Test Clock implementation with virtual time control
 *
 * Based on EffectTS TestClock, this implementation provides:
 * - Virtual time that only advances when explicitly adjusted
 * - Deterministic and fast testing of time-dependent effects
 */
final class VirtualClock implements Clock
{
    private int $currentTime;
    private int $nanoTimeBase;

    public function __construct(int $initialTime = 0) {
        $this->currentTime = $initialTime;
        $this->nanoTimeBase = hrtime(true);
    }

    public function currentTimeMillis(): int {
        return $this->currentTime;
    }

    public function sleep(Duration $duration): void {
        $this->advance($duration);
    }

    public function nanoTime(): int {
        // Calculate nano time based on current virtual time
        $elapsedMillis = $this->currentTime;
        return $this->nanoTimeBase + ($elapsedMillis * 1_000_000);
    }

    /**
     * Adjust the clock time by the specified duration
     *
     * @param Duration $duration Amount to advance time
     * @return void
     */
    public function advance(Duration $duration): void {
        $this->currentTime = $this->currentTime + $duration->toMilliseconds();
    }
}