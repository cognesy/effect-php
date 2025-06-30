<?php

namespace EffectPHP\Workerman;

use EffectPHP\Core\RuntimeV2\Contracts\ExecutionControl;

class WorkermanExecutionControl implements ExecutionControl {
    public function await(): mixed {
    }

    public function cancel(): void {
        // TODO: Implement cancel() method.
    }

    public function isRunning(): bool {
        // TODO: Implement isRunning() method.
    }

    public function isCompleted(): bool {
        // TODO: Implement isCompleted() method.
    }

    public function isCancelled(): bool {
        // TODO: Implement isCancelled() method.
    }
}