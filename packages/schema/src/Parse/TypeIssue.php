<?php declare(strict_types=1);

namespace EffectPHP\Schema\Parse;

use EffectPHP\Schema\Contracts\ParseIssueInterface;

final class TypeIssue implements ParseIssueInterface
{
    public function __construct(
        private readonly mixed $expected,
        private readonly mixed $actual,
        private readonly array $path = [],
        private readonly string $message = 'Type validation failed',
    ) {}

    public function getTag(): string {
        return 'Type';
    }

    public function getPath(): array {
        return $this->path;
    }

    public function getMessage(): string {
        return $this->message;
    }

    public function getActual(): mixed {
        return $this->actual;
    }

    public function getExpected(): mixed {
        return $this->expected;
    }
}
