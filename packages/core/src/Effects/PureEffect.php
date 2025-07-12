<?php declare(strict_types=1);

namespace EffectPHP\Core\Effects;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Traits\Combinators;

/** @template T */
final class PureEffect implements Effect
{
    use Combinators;

    public function __construct(public readonly mixed $value) {}
}
