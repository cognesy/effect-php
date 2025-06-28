<?php

declare(strict_types=1);

namespace EffectPHP\Core\Fiber;

use EffectPHP\Core\Contracts\FiberHandle;
use Fiber;

/**
 * FiberHandle implementation using PHP's native Fiber
 */
final class PHPFiberHandle implements FiberHandle
{
    private Fiber $fiber;
    private bool $cancelled = false;

    public function __construct(Fiber $fiber)
    {
        $this->fiber = $fiber;
    }

    public function await(): mixed
    {
        if ($this->cancelled) {
            throw new \RuntimeException('Fiber was cancelled');
        }

        // If fiber is suspended, we need to resume it until completion
        while (!$this->fiber->isTerminated()) {
            if ($this->fiber->isSuspended()) {
                $this->fiber->resume();
            }
        }

        return $this->fiber->getReturn();
    }

    public function cancel(): void
    {
        $this->cancelled = true;
        // Note: PHP Fibers don't have built-in cancellation
        // This is a limitation we'd need to work around
    }

    public function isRunning(): bool
    {
        return $this->fiber->isStarted() && !$this->fiber->isTerminated() && !$this->cancelled;
    }

    public function isCompleted(): bool
    {
        return $this->fiber->isTerminated() && !$this->cancelled;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }
}