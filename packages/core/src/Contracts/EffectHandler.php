<?php declare(strict_types=1);

namespace EffectPHP\Core\Contracts;

use EffectPHP\Core\RuntimeState;

interface EffectHandler
{
    public function supports(Effect $node): bool;

    public function handle(Effect $node, RuntimeState $state): RuntimeState;
}
