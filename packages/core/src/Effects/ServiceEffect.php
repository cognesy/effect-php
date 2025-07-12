<?php declare(strict_types=1);

namespace EffectPHP\Core\Effects;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Traits\Combinators;

final class ServiceEffect implements Effect
{
    use Combinators;

    public function __construct(
        public readonly string $serviceClass,
    ) {}
}