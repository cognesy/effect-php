<?php

declare(strict_types=1);

namespace EffectPHP\Schema\AST;

final class LiteralType extends BaseASTNode
{
    public function __construct(
        private readonly mixed $value,
        array $annotations = []
    ) {
        parent::__construct($annotations);
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function accept(ASTVisitorInterface $visitor): mixed
    {
        return $visitor->visitLiteralType($this);
    }
}
