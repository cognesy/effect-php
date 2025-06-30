<?php
declare(strict_types=1);

namespace EffectPHP\Core\RuntimeV2\ExecutionControl;

use EffectPHP\Core\RuntimeV2\Contracts\ExecutionControl;
use Throwable;

/**
 * Default implementation of ExecutionControl for synchronous runtime
 *
 * Since DefaultRuntime executes synchronously, the execution is already completed
 * when the handle is created.
 */
final class SyncExecutionControl implements ExecutionControl
{
    private mixed $result;
    private ?Throwable $error;
    private bool $cancelled = false;

    public function __construct(
        mixed $result = null,
        ?Throwable $error = null,
    ) {
        $this->result = $result;
        $this->error = $error;
    }

    public function await(): mixed {
        if ($this->cancelled) {
            throw new \RuntimeException('Execution was cancelled');
        }
        if ($this->error !== null) {
            throw $this->error;
        }
        return $this->result;
    }

    public function cancel(): void {
        $this->cancelled = true;
    }

    public function isRunning(): bool {
        return false; // already completed
    }

    public function isCompleted(): bool {
        return !$this->cancelled;
    }

    public function isCancelled(): bool {
        return $this->cancelled;
    }
}