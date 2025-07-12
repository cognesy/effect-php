<?php declare(strict_types=1);

namespace EffectPHP\Utils\Result;

use Throwable;

/**
 * Failed Result containing a cause (error or exception)
 *
 * @extends Result<never>
 */
final readonly class Failure extends Result
{
    public function __construct(
        public Throwable $cause,
    ) {}

    public function isSuccess(): bool {
        return false;
    }

    public function isFailure(): bool {
        return true;
    }

    public function map(callable $mapper): Result {
        return $this;
    }

    public function flatMap(callable $mapper): Result {
        return $this;
    }

    public function fold(callable $onFailure, callable $onSuccess): mixed {
        return $onFailure($this->cause);
    }

    /**
     * Get the error directly
     * Safe to call since this is a Failure instance
     *
     * @return Throwable
     */
    public function getError(): Throwable {
        return $this->cause;
    }
}