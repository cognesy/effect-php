<?php declare(strict_types=1);

namespace EffectPHP\Core\Effects;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Layer;
use EffectPHP\Core\Traits\Combinators;

final class ProvideEffect implements Effect
{
    use Combinators;

    public function __construct(
        public readonly Effect $inner,
        public readonly Layer $layer
    ) {}
}