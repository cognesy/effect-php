<?php

namespace EffectPHP\Core\Utils;

use EffectPHP\Core\Effects\CatchEffect;
use SplStack;

/**
 * A dedicated, typed LIFO stack for effect continuations.
 *
 * • Built on SPL’s doubly-linked list (`SplStack`) → O(1) push/pop, low memory.
 * • Immutable reference semantics – no need for &-by-ref parameters.
 * • Clear intent API (`push()`, `pop()`, `peek()`, `isEmpty()`).
 *
 * @template TFrame
 * @implements \IteratorAggregate<TFrame>
 */
final class ContinuationStack implements \IteratorAggregate
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
        return $this->frames->pop();
    }

    /** @return TFrame|null */
    public function peek(): mixed {
        return $this->frames->top();
    }

    public function isEmpty(): bool {
        return $this->frames->isEmpty();
    }

    /**
     * Get the number of frames in the stack.
     *
     * @return iterable<TFrame>
     */
    public function getIterator(): iterable {
        foreach ($this->frames as $frame) {
            yield $frame;
        }
    }
}