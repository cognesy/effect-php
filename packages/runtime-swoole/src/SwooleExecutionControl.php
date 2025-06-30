<?php

namespace EffectPHP\Swoole;

use EffectPHP\Core\RuntimeV2\Contracts\ExecutionControl;
use EffectPHP\Core\RuntimeV2\ExecutionControl\SwChannel;
use EffectPHP\Core\RuntimeV2\ExecutionControl\SwCoroutine;

class SwooleExecutionControl implements ExecutionControl {
    public function __construct(
        private int $cid,
        private SwChannel $channel
    ) {
        if (!SwCoroutine::exists($cid)) {
            throw new \RuntimeException("Coroutine with ID {$cid} does not exist.");
        }

        if (!$channel instanceof SwChannel) {
            throw new \InvalidArgumentException('Channel must be an instance of SwChannel');
        }
    }

    public function resolve(): mixed {
        [$value, $error] = $this->channel->pop();
        if ($error instanceof \Throwable) {
            throw $error;
        }
        return $value;
    }

    public function isRunning(): bool {
        return SwCoroutine::exists($this->cid);
    }

    public function cancel(): void {
        if ($this->isRunning()) {
            SwCoroutine::cancel($this->cid);
        }
    }

    public function await(): mixed {
        if (!$this->isRunning()) {
            throw new \RuntimeException('Cannot await a coroutine that is not running');
        }

        // Wait for the channel to receive a value
        $value = $this->channel->pop();

        if ($value instanceof \Throwable) {
            throw $value;
        }

        return $value;
    }

    public function isCompleted(): bool {
        // Check if the coroutine has completed
        return !SwCoroutine::exists($this->cid) || $this->channel->isEmpty();
    }

    public function isCancelled(): bool {
        // Check if the coroutine has been cancelled
        return !SwCoroutine::exists($this->cid) && $this->channel->isEmpty();
    }
}