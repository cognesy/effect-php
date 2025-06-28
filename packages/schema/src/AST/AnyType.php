<?php

declare(strict_types=1);

namespace EffectPHP\Schema\AST;

/**
 * AST node for any/mixed types
 */
final class AnyType extends BaseASTNode
{
    public function __construct(array $annotations = [])
    {
        parent::__construct($annotations);
    }

    public function accept(ASTVisitorInterface $visitor): mixed
    {
        return $visitor->visitAnyType($this);
    }

    public function withAnnotations(array $newAnnotations): self
    {
        return new self(array_merge($this->annotations, $newAnnotations));
    }
}