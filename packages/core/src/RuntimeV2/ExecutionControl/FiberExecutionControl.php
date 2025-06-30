<?php

declare(strict_types=1);

namespace EffectPHP\Core\RuntimeV2\ExecutionControl;

use EffectPHP\Core\RuntimeV2\Contracts\ExecutionControl;
use Fiber;

/**
 * ExecutionControl implementation using PHP's native Fiber
 */
final class FiberExecutionControl implements ExecutionControl
{
    private Fiber $fiber;
    private bool $cancelled = false;

    public function __construct(Fiber $fiber) {
        $this->fiber = $fiber;
    }

    public function await(): mixed {
        if ($this->cancelled) {
            throw new \RuntimeException('Fiber was cancelled');
        }

        try {
            while (!$this->fiber->isTerminated()) {
                if ($this->fiber->isSuspended()) {
                    $this->fiber->resume();
                }
            }
        } catch (\Throwable $e) {
            // Rethrow any exception from inside the fiber
            throw $e;
        }

        return $this->fiber->getReturn();
    }

    public function cancel(): void {
        $this->cancelled = true;
        // No direct cancellation in PHP Fibers – best‑effort only.
        // We may need some workaround here.
    }

    public function isRunning(): bool {
        return $this->fiber->isStarted() && !$this->fiber->isTerminated() && !$this->cancelled;
    }

    public function isCompleted(): bool {
        return $this->fiber->isTerminated() && !$this->cancelled;
    }

    public function isCancelled(): bool {
        return $this->cancelled;
    }
}
