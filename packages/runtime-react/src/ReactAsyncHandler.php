<?php declare(strict_types=1);

use EffectPHP\Core\Context;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Effects\AsyncEffect;
use React\Promise\PromiseInterface;

final class ReactAsyncHandler implements EffectHandler
{
    public function supports(Effect $node): bool {
        return $node instanceof AsyncEffect;
    }

    public function handle(Effect $node, callable $next, Context $ctx): PromiseInterface {
        /* @var AsyncEffect $node */
        return ($node->asyncOperation)();
    }
}