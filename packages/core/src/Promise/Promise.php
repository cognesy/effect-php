<?php

declare(strict_types=1);

namespace EffectPHP\Core\Promise;

use Throwable;

/**
 * Public, adapter-agnostic façade.
 *
 * @template T
 * @implements PromiseInterface<T>
 */
final class Promise
{
    public const STATE_PENDING = 'pending';
    public const STATE_FULFILLED = 'fulfilled';
    public const STATE_REJECTED = 'rejected';

    private PromiseFactoryInterface $factory;

    /* --------------------------------------------------------------------- */
    /* Construction helpers                                                  */
    /* --------------------------------------------------------------------- */

    private function __construct(PromiseFactoryInterface $factory) {
        $this->factory = $factory;
    }

    public static function using(?PromiseFactoryInterface $factory = null) : self {
        return new self($factory ?? new SyncPromiseAdapter());
    }

    /** Run a callback that may throw/return a promise and wrap the result. */
    public function fromCallback(callable $callback): PromiseInterface {
        /** @var class-string $factoryClass */
        $factoryClass = get_class($this->factory);
        return $factoryClass::fromCallable($callback);
    }

    /** Synchronously resolve with a value. */
    public function resolved(mixed $value): PromiseInterface {
        /** @var class-string $factoryClass */
        $factoryClass = get_class($this->factory);
        return $factoryClass::resolved($value);
    }

    /** Synchronously reject with a reason. */
    public function rejected(Throwable $reason): PromiseInterface {
        /** @var class-string $factoryClass */
        $factoryClass = get_class($this->factory);
        return $factoryClass::rejected($reason);
    }

    /** Run a callback that may throw/return a promise and wrap the result. */
    public function pending(): PromiseInterface {
        /** @var class-string $factoryClass */
        $factoryClass = get_class($this->factory);
        return $factoryClass::pending();
    }
}
