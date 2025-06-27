<?php

declare(strict_types=1);

namespace EffectPHP\Schema\Parse;

use Exception;

final class ParseError extends Exception
{
    /**
     * @param ParseIssueInterface[] $issues
     */
    public function __construct(
        private readonly array $issues,
        string $message = 'Schema validation failed'
    ) {
        parent::__construct($message);
    }

    /**
     * @return ParseIssueInterface[]
     */
    public function getIssues(): array
    {
        return $this->issues;
    }

    public function getFormattedMessage(): string
    {
        $messages = [];
        foreach ($this->issues as $issue) {
            $path = empty($issue->getPath()) ? 'root' : implode('.', $issue->getPath());
            $messages[] = "[{$path}] {$issue->getMessage()}";
        }
        return implode('; ', $messages);
    }
}
