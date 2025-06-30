<?php

namespace EffectPHP\Core\Promise;

use EffectPHP\Core\Utils\ContinuationQueue;
use Throwable;

/**
 * @template T
 * @implements PromiseFactoryInterface<T>
 */
final class SyncPromiseAdapter implements PromiseInterface, PromiseFactoryInterface
{
    private string $state = Promise::STATE_PENDING;
    private mixed $value = null;

    /** @var ContinuationQueue<callable():void> */
    private ContinuationQueue $callbacks;

    public function __construct() {
        $this->callbacks = new ContinuationQueue();
    }

    /* --------------------------------------------------------------------- */
    /* PromiseInterface                                                      */
    /* --------------------------------------------------------------------- */

    public function then(
        ?callable $onFulfilled = null,
        ?callable $onRejected = null,
    ): PromiseInterface {
        $next = new self();

        $this->callbacks->push(function () use ($onFulfilled, $onRejected, $next): void {
            try {
                if ($this->state === Promise::STATE_FULFILLED) {
                    $callback = $onFulfilled ?? static fn($value) => $value;
                    $next->settle($callback($this->value));
                } else { // STATE_REJECTED
                    if ($onRejected) {
                        $next->settle($onRejected($this->value));
                    } else {
                        $next->reject($this->value); // propagate
                    }
                }
            } catch (Throwable $e) {
                $next->reject($e);
            }
        });

        // Fast-path if already settled.
        if ($this->state !== Promise::STATE_PENDING) {
            $this->runCallbacks();
        }

        return $next;
    }

    /* --------------------------------------------------------------------- */
    /* Static conveniences                                                   */
    /* --------------------------------------------------------------------- */

    public static function fromCallable(callable $callback): PromiseInterface {
        $promise = new self();
        try {
            $promise->settle($callback());
        } catch (Throwable $reason) {
            $promise->reject($reason);
        }

        return $promise;
    }

    public static function resolved(mixed $value): PromiseInterface {
        $promise = new self();
        $promise->resolve($value);
        return $promise;
    }

    public static function rejected(Throwable $reason): PromiseInterface {
        $promise = new self();
        $promise->reject($reason);
        return $promise;
    }

    public static function pending() : PromiseInterface {
        return new self();
    }

    /* --------------------------------------------------------------------- */
    /* Internal helpers                                                      */
    /* --------------------------------------------------------------------- */

    private function settle(mixed $item): void {
        // Promise assimilation (simple, synchronous).
        if ($item instanceof PromiseInterface) {
            $item->then(
                fn($value) => $this->resolve($value),
                fn(Throwable $reason) => $this->reject($reason),
            );
            return;
        }

        $this->resolve($item);
    }

    private function resolve(mixed $value): void {
        if ($this->state !== Promise::STATE_PENDING) {
            return;
        }

        if ($value === $this) { // Self-resolution guard
            $this->reject(
                new \LogicException('A promise cannot be resolved with itself.'),
            );
            return;
        }

        $this->state = Promise::STATE_FULFILLED;
        $this->value = $value;
        $this->runCallbacks();
    }

    private function reject(Throwable $reason): void {
        if ($this->state !== Promise::STATE_PENDING) {
            return;
        }

        $this->state = Promise::STATE_REJECTED;
        $this->value = $reason;
        $this->runCallbacks();
    }

    private function runCallbacks(): void {
        while (!$this->callbacks->isEmpty()) {
            ($this->callbacks->pop())();
        }
    }

    /* --------------------------------------------------------------------- */
    /* Introspection (package-private, used by facade)                       */
    /* --------------------------------------------------------------------- */

    public function getState(): string {
        return $this->state;
    }

    public function getValue(): mixed {
        return $this->value;
    }
}
