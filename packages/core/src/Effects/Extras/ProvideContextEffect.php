<?php

declare(strict_types=1);

namespace EffectPHP\Core\Effects\Extras;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Effects\BaseEffect;
use EffectPHP\Core\Effects\SuspendEffect;
use EffectPHP\Core\Layer\Context;

final class ProvideContextEffect extends BaseEffect
{
    public function __construct(
        public readonly Effect $source,
        public readonly Context $context
    ) {}

    public function flatMap(callable $chain): Effect
    {
        return new SuspendEffect($this, $chain);
    }
}