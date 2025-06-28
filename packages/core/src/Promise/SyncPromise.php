<?php

declare(strict_types=1);

namespace EffectPHP\Core\Promise;

use EffectPHP\Core\Contracts\Promise;
use Throwable;

/**
 * Synchronous promise implementation for testing and fallback scenarios
 */
final class SyncPromise implements Promise
{
    private mixed $result = null;
    private ?Throwable $error = null;
    private bool $resolved = false;
    private readonly \Closure $computation;

    public function __construct(callable $computation)
    {
        $this->computation = $computation instanceof \Closure ? $computation : \Closure::fromCallable($computation);
    }

    public function then(callable $onFulfilled, ?callable $onRejected = null): Promise
    {
        return new self(function() use ($onFulfilled, $onRejected) {
            try {
                $result = $this->wait();
                return $onFulfilled($result);
            } catch (Throwable $error) {
                if ($onRejected !== null) {
                    return $onRejected($error);
                }
                throw $error;
            }
        });
    }

    public function wait(): mixed
    {
        if ($this->resolved) {
            if ($this->error !== null) {
                throw $this->error;
            }
            return $this->result;
        }

        try {
            $this->result = ($this->computation)();
            $this->resolved = true;
            return $this->result;
        } catch (Throwable $error) {
            $this->error = $error;
            $this->resolved = true;
            throw $error;
        }
    }
}