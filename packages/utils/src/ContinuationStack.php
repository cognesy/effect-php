<?php declare(strict_types=1);

namespace EffectPHP\Utils;

/**
 * LIFO stack for continuation frames (callbacks, effects, etc.).
 *
 * @template TFrame
 */
final class ContinuationStack
{
    private function __construct(
        private mixed $head = null,
        private ?self $tail = null,
    ) {}

    public static function empty(): self {
        return new self();
    }

    public function push(mixed $frame): self {
        return new self($frame, $this);
    }

    public function pop(): self {
        return $this->isEmpty()
            ? $this
            : ($this->tail ?? self::empty());
    }

    public function current(): mixed {
        return $this->isEmpty()
            ? null
            : $this->head;
    }

    public function isEmpty(): bool {
        return $this->head === null;
    }
}