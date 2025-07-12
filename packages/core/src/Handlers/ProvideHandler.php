<?php declare(strict_types=1);

namespace EffectPHP\Core\Handlers;

use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Effects\ProvideEffect;
use EffectPHP\Core\Effects\PureEffect;
use EffectPHP\Core\RuntimeState;
use EffectPHP\Core\Scope;

final class ProvideHandler implements EffectHandler
{
    public function supports(Effect $node): bool {
        return $node instanceof ProvideEffect;
    }

    public function handle(Effect $node, RuntimeState $state): RuntimeState {
        /* @var ProvideEffect $node */
        $parentContext = $state->context;
        $childContext = $node->layer->apply($parentContext);
        $stack = $state->stack;
        $childScope = new Scope();

        // When the inner effect completes, close the child scope.
        $stack->push(static function (mixed $value) use ($childScope): PureEffect {
            $childScope->close();
            return new PureEffect($value);
        });

        return $state->with(
            context: $childContext,
            stack: $stack,
            scope: $childScope,
            value: $node->inner,
        );
    }
}