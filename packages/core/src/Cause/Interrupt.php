<?php
declare(strict_types=1);

namespace EffectPHP\Core\Cause;

use EffectPHP\Core\Exceptions\InterruptedException;

final readonly class Interrupt extends Cause
{
    public function map(callable $mapper): Cause
    {
        return $this;
    }

    public function toException(): \Throwable
    {
        return new InterruptedException();
    }

    public function prettyPrint(): string
    {
        return "🛑 Interrupted";
    }

    public function contains(string $errorType): bool
    {
        return InterruptedException::class === $errorType;
    }
}
