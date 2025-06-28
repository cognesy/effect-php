<?php

declare(strict_types=1);

namespace EffectPHP\Core\Effects;

/**
 * Success effect - immediate value
 *
 * @template A
 * @extends BaseEffect<never, never, A>
 */
final class SuccessEffect extends BaseEffect
{
    public function __construct(public readonly mixed $value) {}
}