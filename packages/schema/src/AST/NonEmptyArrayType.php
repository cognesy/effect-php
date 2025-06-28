<?php

declare(strict_types=1);

namespace EffectPHP\Schema\AST;

/**
 * AST node for non-empty array types
 */
final class NonEmptyArrayType extends BaseASTNode
{
    private ASTNodeInterface $itemType;

    public function __construct(ASTNodeInterface $itemType, array $annotations = [])
    {
        $this->itemType = $itemType;
        parent::__construct($annotations);
    }

    public function getItemType(): ASTNodeInterface
    {
        return $this->itemType;
    }

    public function accept(ASTVisitorInterface $visitor): mixed
    {
        return $visitor->visitNonEmptyArrayType($this);
    }

    public function withAnnotations(array $annotations): self
    {
        return new self(
            $this->itemType,
            array_merge($this->annotations, $annotations)
        );
    }
}