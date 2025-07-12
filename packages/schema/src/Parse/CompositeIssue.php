<?php declare(strict_types=1);

namespace EffectPHP\Schema\Parse;

use EffectPHP\Schema\Contracts\ParseIssueInterface;

final class CompositeIssue implements ParseIssueInterface
{
    public function __construct(
        private readonly array $issues,
        private readonly array $path = [],
        private readonly string $message = 'Multiple validation issues',
    ) {}

    public function getTag(): string {
        return 'Composite';
    }

    public function getPath(): array {
        return $this->path;
    }

    public function getMessage(): string {
        return $this->message;
    }

    public function getActual(): mixed {
        return $this->issues;
    }

    public function getExpected(): mixed {
        return 'valid data';
    }

    public function getIssues(): array {
        return $this->issues;
    }
}
