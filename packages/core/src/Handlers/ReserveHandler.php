<?php declare(strict_types=1);

namespace EffectPHP\Core\Handlers;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Effects\ReserveEffect;
use EffectPHP\Core\Fx;
use EffectPHP\Core\RuntimeState;

final class ReserveHandler implements EffectHandler
{
    public function supports(Effect $node): bool {
        return $node instanceof ReserveEffect;
    }

    public function handle(Effect $node, RuntimeState $state): RuntimeState {
        /* @var ReserveEffect $node */
        $release = $node->release;

        return $state->withValue(
            $node->acquire->flatMap(
                static function (mixed $resource) use ($release, $state): Effect {
                    $state->scope->add(fn() => ($release)($resource));
                    return Fx::value($resource);
                },
            ),
        );
    }
}