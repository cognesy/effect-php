<?php

namespace EffectPHP\Core\RuntimeV2\Contracts;

use Closure;
use EffectPHP\Core\Clock\Clock;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Promise\PromiseInterface;
use EffectPHP\Core\Utils\Duration;

/**
 * The minimal surface every environment‑specific strategy must provide
 * so the unified runtime can walk the effect‑tree independently of
 * platform details.
 */
interface ExecutionStrategy
{
    /** Sleep or schedule the current task for the given duration. */
    public function sleep(Duration $duration): void;

    /** Suspend the current task and re‑enter via the continuation. */
    public function suspend(Closure $continuation): mixed;

    /** Fork the supplied effect into a child task and return control. */
    public function fork(Effect $effect, Runtime $runtime): ExecutionControl;

    /** Run a callback asynchronously, return a Promises/A+ promise. */
    public function defer(Closure $callback): PromiseInterface;

    public function clock(): Clock;

    public function now(): int;
}