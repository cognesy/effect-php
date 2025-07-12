<?php declare(strict_types=1);

namespace EffectPHP\Schema\Contracts;

interface CompilerInterface
{
    public function compile(ASTNodeInterface $ast): mixed;

    public function getTarget(): string;
}
