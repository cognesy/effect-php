<?php

declare(strict_types=1);

namespace EffectPHP\Core\Cause;

use Throwable;

/**
 * Structured representation of failures with superior composition
 *
 * @template E of Throwable
 * @psalm-immutable
 */
abstract readonly class Cause
{
    /**
     * @template E of Throwable
     * @param E $error
     * @return Fail<E>
     */
    public static function fail(Throwable $error): Fail
    {
        return new Fail($error);
    }

    public static function interrupt(): Interrupt
    {
        return new Interrupt();
    }

    /**
     * @param Cause[] $causes
     */
    public static function parallel(array $causes): Parallel
    {
        return new Parallel($causes);
    }

    /**
     * @param Cause[] $causes
     */
    public static function sequential(array $causes): Sequential
    {
        return new Sequential($causes);
    }

    /**
     * Enhanced error composition
     *
     * @param Cause $other
     * @return Cause
     */
    public function and(Cause $other): Cause
    {
        return self::parallel([$this, $other]);
    }

    /**
     * @template E2 of Throwable
     * @param callable(E): E2 $mapper
     * @return Cause<E2>
     */
    abstract public function map(callable $mapper): Cause;

    abstract public function toException(): Throwable;

    /**
     * Superior error reporting
     */
    abstract public function prettyPrint(): string;

    /**
     * Check if this cause contains a specific error type
     *
     * @param class-string<Throwable> $errorType
     */
    abstract public function contains(string $errorType): bool;
}

