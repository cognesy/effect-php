<?php

declare(strict_types=1);

namespace EffectPHP\Schema\AST;

final class EnumType extends BaseASTNode
{
    public function __construct(
        private readonly string $enumClass,
        array $annotations = []
    ) {
        parent::__construct($annotations);
    }

    public function getEnumClass(): string
    {
        return $this->enumClass;
    }

    public function accept(ASTVisitorInterface $visitor): mixed
    {
        return $visitor->visitEnumType($this);
    }
}