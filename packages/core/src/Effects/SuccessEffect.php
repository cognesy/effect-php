<?php

declare(strict_types=1);

namespace EffectPHP\Core\Effects;

/**
 * Success effect - immediate value
 *
 * @template A
 * @extends EffectBase<never, never, A>
 */
final class SuccessEffect extends EffectBase
{
    public function __construct(public readonly mixed $value) {}
}