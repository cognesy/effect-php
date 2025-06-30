<?php

namespace EffectPHP\Core\Utils;

final class TimeWheel
{
    private array $scheduled = [];

    public function schedule(callable $task, Duration $delay = null): void
    {
        $executeAt = $delay ? (microtime(true) + $delay->toSeconds()) : microtime(true);
        $this->scheduled[] = ['task' => $task, 'executeAt' => $executeAt];
        $this->processDue();
    }

    private function processDue(): void
    {
        $now = microtime(true);
        $ready = [];
        $remaining = [];

        foreach ($this->scheduled as $item) {
            if ($item['executeAt'] <= $now) {
                $ready[] = $item['task'];
            } else {
                $remaining[] = $item;
            }
        }

        $this->scheduled = $remaining;

        foreach ($ready as $task) {
            $task();
        }
    }
}