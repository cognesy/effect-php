<?php

declare(strict_types=1);

namespace EffectPHP\Schema\AST;

final class ArrayType extends BaseASTNode
{
    public function __construct(
        private readonly ASTNodeInterface $itemType,
        array $annotations = []
    ) {
        parent::__construct($annotations);
    }

    public function getItemType(): ASTNodeInterface
    {
        return $this->itemType;
    }

    public function accept(ASTVisitorInterface $visitor): mixed
    {
        return $visitor->visitArrayType($this);
    }
}
