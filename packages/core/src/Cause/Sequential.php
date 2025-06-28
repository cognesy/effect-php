<?php
declare(strict_types=1);

namespace EffectPHP\Core\Cause;

final readonly class Sequential extends Cause
{
    public function __construct(public array $causes) {}

    public function map(callable $mapper): self
    {
        return new Sequential(array_map(fn($c) => $c->map($mapper), $this->causes));
    }

    public function toException(): \Throwable
    {
        $lastCause = $this->causes[array_key_last($this->causes)];
        return $lastCause->toException();
    }

    public function prettyPrint(): string
    {
        $prettyParts = array_map(fn($c) => $c->prettyPrint(), $this->causes);
        return "⏭️ Sequential Failures:\n" . implode("\n", array_map(fn($p) => "  ▶ $p", $prettyParts));
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