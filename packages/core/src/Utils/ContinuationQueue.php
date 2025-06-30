<?php

namespace EffectPHP\Core\Utils;

use SplQueue;

/**
 * A dedicated, typed FIFO queue for continuation frames (callbacks, effects).
 *
 * • Built on SPL’s doubly-linked list (`SplQueue`) → O(1) enqueue/dequeue, low memory.
 * • Immutable reference semantics – no need for &-by-ref parameters.
 * • Clear intent API (`push()`, `pop()`, `peek()`, `isEmpty()`).
 *
 * @template TFrame
 * @implements \IteratorAggregate<TFrame>
 */
final class ContinuationQueue implements \IteratorAggregate
{
    /** @var SplQueue<TFrame> */
    private SplQueue $frames;

    public function __construct() {
        $this->frames = new SplQueue();
    }

    /** @param callable $frame */
    public function push(mixed $frame): void {
        $this->frames->enqueue($frame);
    }

    /** @return TFrame|null */
    public function pop(): mixed {
        return $this->frames->dequeue();
    }

    /** @return TFrame|null */
    public function peek(): mixed {
        return $this->frames->bottom();
    }

    public function isEmpty(): bool {
        return $this->frames->isEmpty();
    }

    /**
     * @return iterable<TFrame>
     */
    public function getIterator(): iterable {
        foreach ($this->frames as $frame) {
            yield $frame;
        }
    }
}