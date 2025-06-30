<?php

declare(strict_types=1);

namespace EffectPHP\RuntimeV1\Promise;

use EffectPHP\RuntimeV1\Promise;

/**
 * Synchronous promise adapter for testing and fallback scenarios
 */
final class SyncPromiseAdapter implements PromiseAdapter
{
    public function fromCallable(callable $computation): Promise
    {
        return new SyncPromise($computation);
    }

    public function resolve(mixed $value): Promise
    {
        return new SyncPromise(fn() => $value);
    }

    public function reject(\Throwable $error): Promise
    {
        return new SyncPromise(fn() => throw $error);
    }

    public function canHandle(mixed $promise): bool
    {
        return $promise instanceof SyncPromise;
    }

    public function wrap(mixed $promise): Promise
    {
        if ($promise instanceof SyncPromise) {
            return $promise;
        }
        
        throw new \InvalidArgumentException('Cannot wrap non-SyncPromise object');
    }
}