<?php

namespace EffectPHP\Core\Promise;

interface PromiseInterface
{
    /**
     * Attach callbacks to be executed when the promise is fulfilled or rejected.
     *
     * @param callable|null $onFulfilled Called with the fulfillment value.
     * @param callable|null $onRejected Called with a \Throwable when rejected.
     *
     * @return PromiseInterface A *new* promise that resolves with the
     * return value of the handler (or the original outcome when no handler).
     */
    public function then(
        ?callable $onFulfilled = null,
        ?callable $onRejected = null,
    ) : PromiseInterface;
}