<?php

declare(strict_types=1);

namespace EffectPHP\Core\Effects;

use EffectPHP\Core\Cause\Cause;

/**
 * Failure effect - immediate error
 *
 * @template E of \Throwable
 * @extends EffectBase<never, E, never>
 */
final class FailureEffect extends EffectBase
{
    public function __construct(public readonly Cause $cause) {}
}