<?php

declare(strict_types=1);

namespace EffectPHP\Core\Effects;

use EffectPHP\Core\Cause\Cause;

/**
 * Failure effect - immediate error
 *
 * @template E of \Throwable
 * @extends BaseEffect<never, E, never>
 */
final class FailureEffect extends BaseEffect
{
    public function __construct(public readonly Cause $cause) {}
}