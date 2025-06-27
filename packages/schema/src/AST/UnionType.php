<?php

declare(strict_types=1);

namespace EffectPHP\Schema\AST;

final class UnionType extends BaseASTNode
{
    public function __construct(
        private readonly array $types,
        array $annotations = []
    ) {
        parent::__construct($annotations);
    }

    public function getTypes(): array
    {
        return $this->types;
    }

    public function accept(ASTVisitorInterface $visitor): mixed
    {
        return $visitor->visitUnionType($this);
    }
}
