<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtime;

use EffectPHP\Core\Contracts\FiberHandle;

/**
 * Default implementation of FiberHandle for synchronous runtime
 * 
 * Since DefaultRuntime executes synchronously, the "fiber" is already completed
 * when the handle is created.
 */
final class DefaultFiberHandle implements FiberHandle
{
    private mixed $result;
    private ?\Throwable $error;
    private bool $cancelled = false;

    public function __construct(mixed $result = null, ?\Throwable $error = null)
    {
        $this->result = $result;
        $this->error = $error;
    }

    public function await(): mixed
    {
        if ($this->cancelled) {
            throw new \RuntimeException('Fiber was cancelled');
        }
        
        if ($this->error !== null) {
            throw $this->error;
        }
        
        return $this->result;
    }

    public function cancel(): void
    {
        $this->cancelled = true;
    }

    public function isRunning(): bool
    {
        return false; // Always completed in DefaultRuntime
    }

    public function isCompleted(): bool
    {
        return !$this->cancelled;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }
}