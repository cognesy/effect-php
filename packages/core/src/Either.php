<?php

declare(strict_types=1);

namespace EffectPHP\Core;

use EffectPHP\Core\Contracts\Effect;
use RuntimeException;
use Throwable;

/**
 * Either monad with enhanced ergonomics
 *
 * @template L
 * @template R
 * @psalm-immutable
 */
final readonly class Either
{
    private function __construct(
        private mixed $value,
        private bool $isLeft
    ) {}

    /**
     * @template L
     * @param L $value
     * @return Either<L, never>
     */
    public static function left(mixed $value): self
    {
        return new self($value, true);
    }

    /**
     * @template R
     * @param R $value
     * @return Either<never, R>
     */
    public static function right(mixed $value): self
    {
        return new self($value, false);
    }

    public function isLeft(): bool
    {
        return $this->isLeft;
    }

    public function isRight(): bool
    {
        return !$this->isLeft;
    }

    /**
     * @template R2
     *
     * @param callable(R): R2 $mapper
     *
     * @psalm-return self<L, R>|self<never, mixed>
     */
    public function map(callable $mapper): self
    {
        return $this->isLeft ? $this : self::right($mapper($this->value));
    }

    /**
     * @template L2
     *
     * @param callable(L): L2 $mapper
     *
     * @psalm-return self<L, R>|self<mixed, never>
     */
    public function mapLeft(callable $mapper): self
    {
        return $this->isLeft ? self::left($mapper($this->value)) : $this;
    }

    /**
     * @template R2
     *
     * @param callable(R): Either<L, R2> $mapper
     *
     * @psalm-return self<L, R2>|self<L, R>
     */
    public function flatMap(callable $mapper): self
    {
        return $this->isLeft ? $this : $mapper($this->value);
    }

    /**
     * Natural language folding
     *
     * @template T
     * @param callable(L): T $whenLeft
     * @param callable(R): T $whenRight
     * @return T
     */
    public function fold(callable $whenLeft, callable $whenRight): mixed
    {
        return $this->isLeft ? $whenLeft($this->value) : $whenRight($this->value);
    }

    /**
     * Convert to Effect with proper error handling
     *
     * @return Effect<never, L, R>
     */
    public function toEffect(): Effect
    {
        return $this->isLeft
            ? Eff::fail($this->value instanceof Throwable ? $this->value : new RuntimeException((string) $this->value))
            : Eff::succeed($this->value);
    }
}