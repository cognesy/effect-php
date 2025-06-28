<?php

namespace EffectPHP\Core\Result;

use EffectPHP\Core\Either;

/**
 * Successful Result containing a value
 *
 * @template A
 * @extends Result<A>
 */
final class Success extends Result
{
    /**
     * @param A $value
     */
    public function __construct(
        public readonly mixed $value
    ) {}

    public function isSuccess(): bool
    {
        return true;
    }

    public function isFailure(): bool
    {
        return false;
    }

    public function map(callable $mapper): Result
    {
        return new Success($mapper($this->value));
    }

    public function flatMap(callable $mapper): Result
    {
        return $mapper($this->value);
    }

    public function toEither(): Either
    {
        return Either::right($this->value);
    }

    public function fold(callable $onFailure, callable $onSuccess): mixed
    {
        return $onSuccess($this->value);
    }
    
    /**
     * Get the success value directly
     * Safe to call since this is a Success instance
     * 
     * @return A
     */
    public function getValue(): mixed
    {
        return $this->value;
    }
}

