<?php declare(strict_types=1);

namespace EffectPHP\Utils;

use IteratorAggregate;
use SplQueue;
use Traversable;

/**
 * Typed FIFO queue for continuation frames (callbacks, effects, etc.).
 *
 * • Built on SPL's doubly-linked list (`SplQueue`) → O(1) enqueue/dequeue, low memory.
 * • Immutable reference semantics – no need for &-by-ref parameters.
 *
 * @template TFrame
 * @implements IteratorAggregate<TFrame>
 */
final class ContinuationQueue implements IteratorAggregate
{
    /** @var SplQueue<TFrame> */
    private SplQueue $frames;

    public function __construct() {
        $this->frames = new SplQueue();
    }

    /** @param TFrame $frame */
    public function push(mixed $frame): void {
        $this->frames->enqueue($frame);
    }

    /** @return TFrame|null */
    public function pop(): mixed {
        return $this->frames->isEmpty() ? null : $this->frames->dequeue();
    }

    /** @return TFrame|null */
    public function peek(): mixed {
        return $this->frames->isEmpty() ? null : $this->frames->bottom();
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