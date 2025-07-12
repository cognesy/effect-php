<?php declare(strict_types=1);

namespace EffectPHP\Utils;

use IteratorAggregate;
use SplStack;
use Traversable;

/**
 * Typed LIFO stack for continuation frames (callbacks, effects, etc.).
 *
 * • Built on SPL's doubly-linked list (`SplStack`) → O(1) push/pop, low memory.
 * • Immutable reference semantics – no need for &-by-ref parameters.
 *
 * @template TFrame
 * @implements IteratorAggregate<TFrame>
 */
final class ContinuationStack implements IteratorAggregate
{
    /** @var SplStack<TFrame> */
    private SplStack $frames;

    public function __construct() {
        $this->frames = new SplStack();
    }

    /** @param TFrame $frame */
    public function push(mixed $frame): void {
        $this->frames->push($frame);
    }

    /** @return TFrame|null */
    public function pop(): mixed {
        return $this->frames->isEmpty() ? null : $this->frames->pop();
    }

    /** @return TFrame|null */
    public function peek(): mixed {
        return $this->frames->isEmpty() ? null : $this->frames->top();
    }

    public function isEmpty(): bool {
        return $this->frames->isEmpty();
    }

    /** @return Traversable<TFrame> */
    public function getIterator(): Traversable {
        foreach ($this->frames as $frame) {
            yield $frame;
        }
    }
}