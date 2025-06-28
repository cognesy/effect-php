<?php

declare(strict_types=1);

namespace EffectPHP\Schema\AST;

/**
 * AST node for tuple types (fixed-length arrays with typed elements)
 */
final class TupleType extends BaseASTNode
{
    /** @var ASTNodeInterface[] */
    private array $elementTypes;

    /**
     * @param ASTNodeInterface[] $elementTypes
     */
    public function __construct(array $elementTypes, array $annotations = [])
    {
        $this->elementTypes = $elementTypes;
        parent::__construct($annotations);
    }

    /**
     * @return ASTNodeInterface[]
     */
    public function getElementTypes(): array
    {
        return $this->elementTypes;
    }

    public function accept(ASTVisitorInterface $visitor): mixed
    {
        return $visitor->visitTupleType($this);
    }

    public function withAnnotations(array $annotations): self
    {
        return new self(
            $this->elementTypes,
            array_merge($this->annotations, $annotations)
        );
    }
}