<?php

declare(strict_types=1);

namespace EffectPHP\Core\Result;

use EffectPHP\Core\Cause\Cause;
use EffectPHP\Core\Either;

/**
 * Result represents the outcome of an Effect execution
 *
 * Equivalent to EffectTS Result type - provides structured success/failure handling
 *
 * @template A Success value type
 */
abstract class Result
{
    /**
     * Create successful Result
     *
     * @template T
     * @param T $value
     * @return Success<T>
     */
    public static function succeed(mixed $value): Success {
        return new Success($value);
    }

    /**
     * Create failed Result with Cause
     *
     * @param Cause $cause
     * @return Failure
     */
    public static function fail(Cause $cause): Failure {
        return new Failure($cause);
    }

    /**
     * Create failed Result from Throwable
     *
     * @param \Throwable $error
     * @return Failure
     */
    public static function die(\Throwable $error): Failure {
        return new Failure(Cause::fail($error));
    }

    /**
     * Check if this Result represents success
     */
    abstract public function isSuccess(): bool;

    /**
     * Check if this Result represents failure
     */
    abstract public function isFailure(): bool;

    /**
     * Map the success value if present
     *
     * @template B
     * @param callable(A): B $mapper
     * @return Result<B>
     */
    abstract public function map(callable $mapper): Result;

    /**
     * FlatMap over the success value if present
     *
     * @template B
     * @param callable(A): Result<B> $mapper
     * @return Result<B>
     */
    abstract public function flatMap(callable $mapper): Result;

    /**
     * Convert to Either for compatibility
     *
     * @return Either<\Throwable, A>
     */
    abstract public function toEither(): Either;

    /**
     * Fold the Result into a single value
     *
     * @template B
     * @param callable(Cause): B $onFailure
     * @param callable(A): B $onSuccess
     * @return B
     */
    abstract public function fold(callable $onFailure, callable $onSuccess): mixed;

    /**
     * Get the success value or null if failed
     * Convenience method to avoid fold() for simple value access
     *
     * @return A|null
     */
    public function getValueOrNull(): mixed {
        return $this->fold(fn($cause) => null, fn($value) => $value);
    }

    /**
     * Get the error or null if successful
     * Convenience method to avoid fold() for simple error access
     *
     * @return \Throwable|null
     */
    public function getErrorOrNull(): ?\Throwable {
        return $this->fold(fn($cause) => $cause->error, fn($value) => null);
    }

    /**
     * Get the cause or null if successful
     * Convenience method to avoid fold() for simple cause access
     *
     * @return Cause|null
     */
    public function getCauseOrNull(): ?Cause {
        return $this->fold(fn($cause) => $cause, fn($value) => null);
    }
}

