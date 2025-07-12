<?php declare(strict_types=1);

namespace EffectPHP\Utils\Result;

use Throwable;

/**
 * Result represents the outcome of an execution
 *
 * Provides structured success/failure handling
 *
 * @template A Success value type
 */
abstract readonly class Result
{
    /**
     * Attempt to execute a callable and return Result
     *
     * @template T
     * @param callable(...$args): T $fn Function to execute
     * @param mixed ...$args Arguments to pass to the function
     * @return Result<T>
     */
    public static function try(callable $fn, mixed ...$args): Result {
        try {
            $result = $fn(...$args);
            return match (true) {
                $result instanceof Result => $result,
                default => self::succeed($result),
            };
        } catch (Throwable $e) {
            return self::fail($e);
        }
    }

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
     * @param Throwable $cause
     * @return Failure
     */
    public static function fail(Throwable $cause): Failure {
        return new Failure($cause);
    }

    /**
     * Create failed Result from Throwable
     *
     * @param Throwable $error
     * @return Failure
     */
    public static function die(Throwable $error): Failure {
        return new Failure($error);
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
     * Fold the Result into a single value
     *
     * @template B
     * @param callable(Throwable): B $onFailure
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
        return $this->fold(fn($cause) => $cause->toException(), fn($value) => null);
    }
}