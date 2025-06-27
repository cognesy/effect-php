<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtime\Scheduler;

/**
 * Represents a scheduled task
 */
final class ScheduledTask
{
    public function __construct(
        public readonly int $id,
        public readonly callable $task,
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