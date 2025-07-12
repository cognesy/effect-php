<?php declare(strict_types=1);

namespace EffectPHP\Schema\Parse;

use EffectPHP\Schema\Contracts\ParseIssueInterface;

final class MissingIssue implements ParseIssueInterface
{
    public function __construct(
        private readonly array $path = [],
        private readonly string $message = 'Required value is missing',
    ) {}

    public function getTag(): string {
        return 'Missing';
    }

    public function getPath(): array {
        return $this->path;
    }

    public function getMessage(): string {
        return $this->message;
    }

    public function getActual(): mixed {
        return null;
    }

    public function getExpected(): mixed {
        return 'required value';
    }
}
