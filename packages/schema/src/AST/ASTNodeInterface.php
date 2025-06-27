<?php

declare(strict_types=1);

namespace EffectPHP\Schema\AST;

interface ASTNodeInterface
{
    public function getAnnotations(): array;

    public function withAnnotations(array $annotations): self;

    public function accept(ASTVisitorInterface $visitor): mixed;
}
