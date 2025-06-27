<?php

declare(strict_types=1);

namespace EffectPHP\Schema\Parse;

interface ParseIssueInterface
{
    public function getTag(): string;
    public function getPath(): array;
    public function getMessage(): string;
    public function getActual(): mixed;
    public function getExpected(): mixed;
}
