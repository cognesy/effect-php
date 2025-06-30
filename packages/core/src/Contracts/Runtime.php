<?php

namespace EffectPHP\Core\Contracts;

use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\RuntimeV2\Contracts\ExecutionControl;
use EffectPHP\Core\RuntimeV2\Contracts\ExecutionStrategy;

/**
 * Primary runtime contract exposed to application code.
 */
interface Runtime
{
    /**
     * Execute the effect and return its value (blocking the *current*
     * Fiber/Coroutine only).
     *
     * @template A
     * @param Effect<never, mixed, A> $effect
     * @return A
     * @throws \Throwable
     */
    public function run(Effect $effect): mixed;

    /**
     * Structured‑concurrency fork.
     *
     * @template A
     * @param Effect<never, mixed, A> $effect
     * @return ExecutionControl<A>
     */
    public function fork(Effect $effect): ExecutionControl;

    /** Create a copy with a different root context (for dependency‑injection layers). */
    public function withContext(Context $context): static;

    public function strategy(): ExecutionStrategy;
}