<?php

declare(strict_types=1);

namespace EffectPHP\Core;

use EffectPHP\Core\Contracts\Effect;
use Throwable;

/**
 * Option monad
 *
 * @template A
 * @psalm-immutable
 */
final readonly class Option
{
    private function __construct(
        private mixed $value,
        private bool $isEmpty
    ) {}

    /**
     * template A // not needed
     * @param A $value
     * @return Option<A>
     */
    public static function some(mixed $value): self
    {
        return new self($value, false);
    }

    /**
     * @return Option<never>
     */
    public static function none(): self
    {
        return new self(null, true);
    }

    /**
     * @template B
     * @param callable(A): B $mapper
     * @return Option<B>
     */
    public function map(callable $mapper): self
    {
        return $this->isEmpty ? self::none() : self::some($mapper($this->value));
    }

    /**
     * @template B
     * @param callable(A): Option<B> $mapper
     * @return Option<B>
     */
    public function flatMap(callable $mapper): self
    {
        return $this->isEmpty ? self::none() : $mapper($this->value);
    }

    public function isSome(): bool
    {
        return !$this->isEmpty;
    }

    public function isNone(): bool
    {
        return $this->isEmpty;
    }

    /**
     * Natural language alternative to getOrElse
     *
     * @param A $default
     * @return A
     */
    public function whenNone(mixed $default): mixed
    {
        return $this->isEmpty ? $default : $this->value;
    }

    /**
     * @param Option<A> $alternative
     * @return Option<A>
     */
    public function otherwiseUse(self $alternative): self
    {
        return $this->isEmpty ? $alternative : $this;
    }

    /**
     * Convert to Effect with natural error handling
     *
     * @template E of Throwable
     * @param E $whenEmpty
     * @return Effect<never, E, A>
     */
    public function toEffect(Throwable $whenEmpty): Effect
    {
        return $this->isEmpty
            ? Eff::fail($whenEmpty)
            : Eff::succeed($this->value);
    }
}