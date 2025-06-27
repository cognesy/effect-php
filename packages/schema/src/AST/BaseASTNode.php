<?php

declare(strict_types=1);

namespace EffectPHP\Schema\AST;

abstract class BaseASTNode implements ASTNodeInterface
{
    protected array $annotations = [];

    public function __construct(array $annotations = [])
    {
        $this->annotations = $annotations;
    }

    public function getAnnotations(): array
    {
        return $this->annotations;
    }

    public function withAnnotations(array $annotations): self
    {
        $clone = clone $this;
        $clone->annotations = array_merge($this->annotations, $annotations);
        return $clone;
    }

    abstract public function accept(ASTVisitorInterface $visitor): mixed;
}
