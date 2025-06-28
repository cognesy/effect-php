<?php

declare(strict_types=1);

namespace EffectPHP\Core\Scheduler;

use Closure;

/**
 * Represents a scheduled task
 */
final class ScheduledTask
{
    public function __construct(
        public readonly int $id,
        public readonly Closure $task,
        public readonly int $scheduledTime,
        public bool $cancelled = false
    ) {}

    public function cancel(): void
    {
        $this->cancelled = true;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    public function isReady(int $currentTime): bool
    {
        return !$this->cancelled && $currentTime >= $this->scheduledTime;
    }

    public function execute(): void
    {
        if (!$this->cancelled) {
            ($this->task)();
        }
    }
}