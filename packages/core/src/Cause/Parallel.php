<?php
declare(strict_types=1);

namespace EffectPHP\Core\Cause;

use EffectPHP\Core\Exceptions\CompositeException;

final readonly class Parallel extends Cause
{
    public function __construct(public array $causes) {}

    public function map(callable $mapper): self
    {
        return new Parallel(array_map(fn($c) => $c->map($mapper), $this->causes));
    }

    public function toException(): \Throwable
    {
        $messages = array_map(fn($c) => $c->toException()->getMessage(), $this->causes);
        return new CompositeException("Parallel failures:\n  • " . implode("\n  • ", $messages));
    }

    public function prettyPrint(): string
    {
        $prettyParts = array_map(fn($c) => $c->prettyPrint(), $this->causes);
        return "🔀 Parallel Failures:\n" . implode("\n", array_map(fn($p) => "  └─ $p", $prettyParts));
    }

    public function contains(string $errorType): bool
    {
        foreach ($this->causes as $cause) {
            if ($cause->contains($errorType)) {
                return true;
            }
        }
        return false;
    }
}

