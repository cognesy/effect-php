<?php

namespace EffectPHP\Core\RuntimeV2;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\CatchEffect;
use EffectPHP\Core\Effects\SuccessEffect;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\RuntimeV2\Contracts\ExecutionControl;
use EffectPHP\Core\RuntimeV2\Contracts\ExecutionStrategy;
use EffectPHP\Core\Utils\ContinuationStack;

/**
 * Universal interpreter – walks the effect tree using handler registry.
 */
final class RuntimeKernel implements Runtime
{
    private readonly ExecutionStrategy $strategy;
    private readonly Context $rootContext;
    private readonly EffectHandlerRegistry $registry;

    public function __construct(
        ExecutionStrategy $strategy,
        Context $rootContext = null,
        EffectHandlerRegistry $registry = null,
    ) {
        $this->rootContext = $rootContext ?? Context::empty();
        $this->registry = $registry ?? EffectHandlerRegistry::createUniversal();
        $this->strategy = $strategy;
    }

    /* ---------------- Runtime ---------------- */

    public function run(Effect $effect): mixed {
        /** @var ContinuationStack<callable|CatchEffect> $stack */
        $stack = new ContinuationStack();
        $ctx = $this->rootContext;
        $current = $effect;

        while (true) {
            // Fast‑exit: success value with nothing more to execute.
            if ($current instanceof SuccessEffect && empty($stack)) {
                /** @var SuccessEffect $current */
                return $current->value;
            }

            // Ask the registry for a handler.
            $handler = $this->registry->getHandler($current);
            $current = $handler->handle($current, $stack, $ctx, $this);
        }
    }

    public function fork(Effect $effect): ExecutionControl {
        return $this->strategy->fork($effect, $this);
    }

    public function withContext(Context $context): static {
        return new self($this->strategy, $context, $this->registry);
    }

    public function strategy(): ExecutionStrategy { return $this->strategy; }
}