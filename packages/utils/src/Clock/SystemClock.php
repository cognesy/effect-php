<?php declare(strict_types=1);

namespace EffectPHP\Utils\Clock;

use EffectPHP\Utils\Duration;

/**
 * System Clock implementation using real system time
 *
 * This is the production implementation that uses actual system time
 * and blocking sleep operations.
 *
 * For testing, use VirtualClock with manual time control.
 */
final class SystemClock implements Clock
{
    public function currentTimeMillis(): int {
        return (int) (microtime(true) * 1000);
    }

    public function sleep(Duration $duration): void {
        // In real-time clock, we actually sleep and then call continuation
        $microseconds = $duration->toMicroseconds();
        if ($microseconds > 0) {
            usleep($microseconds);
        }
    }

    public function advance(Duration $duration): void {
        // Real-time clocks don't support virtual time adjustment
        // This is a no-op for compatibility with the interface
    }

    public function nanoTime(): int {
        // PHP's hrtime provides high-resolution time in nanoseconds
        return hrtime(true);
    }
}