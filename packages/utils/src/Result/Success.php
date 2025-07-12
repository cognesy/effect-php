<?php declare(strict_types=1);

namespace EffectPHP\Utils\Result;

use EffectPHP\Utils\Either\Either;

/**
 * Successful Result containing a value
 *
 * @template A
 * @extends Result<A>
 */
final readonly class Success extends Result
{
    /**
     * @param A $value
     */
    public function __construct(
        public mixed $value,
    ) {}

    public function isSuccess(): bool {
        return true;
    }

    public function isFailure(): bool {
        return false;
    }

    public function map(callable $mapper): Result {
        return new Success($mapper($this->value));
    }

    public function flatMap(callable $mapper): Result {
        return $mapper($this->value);
    }

    public function fold(callable $onFailure, callable $onSuccess): mixed {
        return $onSuccess($this->value);
    }

    /**
     * Get the success value directly
     * Safe to call since this is a Success instance
     *
     * @return A
     */
    public function getValue(): mixed {
        return $this->value;
    }
}