<?php

declare(strict_types=1);

namespace EffectPHP\Core\Scheduler;

use EffectPHP\Core\Utils\Duration;

/**
 * Real-time scheduler implementation
 * 
 * Uses system time and actual delays for task scheduling.
 * Suitable for production environments.
 */
final class RealTimeScheduler implements Scheduler
{
    private array $tasks = [];
    private int $nextTaskId = 0;

    public function schedule(callable $task, Duration $delay): ScheduledTask
    {
        $taskId = $this->nextTaskId++;
        $scheduledTime = $this->currentTime() + $delay->toMilliseconds();
        
        $scheduledTask = new ScheduledTask($taskId, $task, $scheduledTime);
        $this->tasks[$taskId] = $scheduledTask;
        
        return $scheduledTask;
    }

    public function scheduleImmediate(callable $task): ScheduledTask
    {
        return $this->schedule($task, Duration::milliseconds(0));
    }

    public function tick(): void
    {
        $currentTime = $this->currentTime();
        $tasksToExecute = [];
        
        foreach ($this->tasks as $taskId => $task) {
            if ($task->isReady($currentTime)) {
                $tasksToExecute[] = $taskId;
            }
        }
        
        foreach ($tasksToExecute as $taskId) {
            $task = $this->tasks[$taskId];
            unset($this->tasks[$taskId]);
            $task->execute();
        }
    }

    public function currentTime(): int
    {
        return (int)(microtime(true) * 1000);
    }

    public function advanceTime(Duration $duration): void
    {
        // Real-time scheduler doesn't support virtual time advancement
        // This is a no-op for compatibility
    }

    public function isVirtual(): bool
    {
        return false;
    }
}