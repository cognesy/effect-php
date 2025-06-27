<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtime\Scheduler;

use EffectPHP\Core\Utils\Duration;

/**
 * Virtual time scheduler implementation
 * 
 * Controls time manually for deterministic testing.
 * Time only advances when explicitly requested.
 */
final class VirtualTimeScheduler implements Scheduler
{
    private array $tasks = [];
    private int $nextTaskId = 0;
    private int $virtualTime = 0;

    public function __construct(int $initialTime = 0)
    {
        $this->virtualTime = $initialTime;
    }

    public function schedule(callable $task, Duration $delay): ScheduledTask
    {
        $taskId = $this->nextTaskId++;
        $scheduledTime = $this->virtualTime + $delay->toMilliseconds();
        
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
        $tasksToExecute = [];
        
        foreach ($this->tasks as $taskId => $task) {
            if ($task->isReady($this->virtualTime)) {
                $tasksToExecute[] = $taskId;
            }
        }
        
        // Sort by scheduled time to maintain execution order
        usort($tasksToExecute, function($a, $b) {
            return $this->tasks[$a]->scheduledTime <=> $this->tasks[$b]->scheduledTime;
        });
        
        foreach ($tasksToExecute as $taskId) {
            $task = $this->tasks[$taskId];
            unset($this->tasks[$taskId]);
            $task->execute();
        }
    }

    public function currentTime(): int
    {
        return $this->virtualTime;
    }

    public function advanceTime(Duration $duration): void
    {
        $this->virtualTime += $duration->toMilliseconds();
        $this->tick(); // Execute any tasks that are now ready
    }

    public function setTime(int $time): void
    {
        if ($time < $this->virtualTime) {
            throw new \InvalidArgumentException("Cannot set time backwards");
        }
        $this->virtualTime = $time;
        $this->tick();
    }

    public function isVirtual(): bool
    {
        return true;
    }

    public function getScheduledTasks(): array
    {
        return $this->tasks;
    }
}