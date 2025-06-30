<?php

declare(strict_types=1);

namespace EffectPHP\RuntimeV1;

/**
 * Promise abstraction for async operations
 * 
 * Provides a unified interface for different promise implementations
 * (AmpPHP, ReactPHP, Guzzle Promise/A+)
 */
interface Promise
{
    /**
     * Register callbacks for promise resolution
     *
     * @param callable(mixed): mixed $onFulfilled
     * @param callable(\Throwable): mixed|null $onRejected
     * @return Promise
     */
    public function then(callable $onFulfilled, ?callable $onRejected = null): Promise;

    /**
     * Wait for promise resolution synchronously
     * 
     * Used for synchronous execution contexts
     * 
     * @return mixed
     * @throws \Throwable
     */
    public function wait(): mixed;
}