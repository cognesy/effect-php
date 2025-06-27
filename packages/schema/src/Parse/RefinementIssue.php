<?php

declare(strict_types=1);

namespace EffectPHP\Schema\Parse;

final class RefinementIssue implements ParseIssueInterface
{
    public function __construct(
        private readonly string $refinement,
        private readonly mixed $actual,
        private readonly array $path = [],
        private readonly string $message = 'Refinement validation failed'
    ) {}

    public function getTag(): string
    {
        return 'Refinement';
    }

    public function getPath(): array
    {
        return $this->path;
    }

    public function getMessage(): string
    {
        return "{$this->message}: {$this->refinement}";
    }

    public function getActual(): mixed
    {
        return $this->actual;
    }

    public function getExpected(): mixed
    {
        return $this->refinement;
    }
}
