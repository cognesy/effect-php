<?php

declare(strict_types=1);

namespace EffectPHP\Core\Effects\Extras;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Effects\BaseEffect;
use EffectPHP\Core\Effects\SuspendEffect;

final class ServiceAccessEffect extends BaseEffect
{
    public function __construct(public readonly string $serviceTag) {}

    public function flatMap(callable $chain): Effect
    {
        return new SuspendEffect($this, $chain);
    }
}