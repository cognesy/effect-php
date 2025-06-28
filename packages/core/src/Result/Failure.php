<?php

namespace EffectPHP\Core\Result;

use EffectPHP\Core\Cause\Cause;
use EffectPHP\Core\Either;

/**
 * Failed Result containing a Cause
 *
 * @extends Result<never>
 */
final class Failure extends Result
{
    public function __construct(
        public readonly Cause $cause
    ) {}

    public function isSuccess(): bool
    {
        return false;
    }

    public function isFailure(): bool
    {
        return true;
    }

    public function map(callable $mapper): Result
    {
        return $this;
    }

    public function flatMap(callable $mapper): Result
    {
        return $this;
    }

    public function toEither(): Either
    {
        return Either::left($this->cause->error);
    }

    public function fold(callable $onFailure, callable $onSuccess): mixed
    {
        return $onFailure($this->cause);
    }
    
    /**
     * Get the error directly
     * Safe to call since this is a Failure instance
     * 
     * @return \Throwable
     */
    public function getError(): \Throwable
    {
        return $this->cause->error;
    }
    
    /**
     * Get the cause directly
     * Safe to call since this is a Failure instance
     * 
     * @return Cause
     */
    public function getCause(): Cause
    {
        return $this->cause;
    }
}