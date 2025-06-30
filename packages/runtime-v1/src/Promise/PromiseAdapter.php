<?php

declare(strict_types=1);

namespace EffectPHP\RuntimeV1\Promise;

use EffectPHP\RuntimeV1\Promise;

/**
 * Adapter interface for different promise implementations
 * 
 * Allows runtime to work with various promise libraries
 * through a unified interface
 */
interface PromiseAdapter
{
    /**
     * Create promise from callable computation
     *
     * @param callable(): mixed $computation
     * @return \Promise
     */
    public function fromCallable(callable $computation): Promise;

    /**
     * Create resolved promise with value
     *
     * @param mixed $value
     * @return \Promise
     */
    public function resolve(mixed $value): Promise;

    /**
     * Create rejected promise with error
     *
     * @param \Throwable $error
     * @return \Promise
     */
    public function reject(\Throwable $error): Promise;

    /**
     * Check if this adapter can handle the given object
     *
     * @param mixed $promise
     * @return bool
     */
    public function canHandle(mixed $promise): bool;

    /**
     * Wrap external promise object
     *
     * @param mixed $promise
     * @return \Promise
     */
    public function wrap(mixed $promise): Promise;
}