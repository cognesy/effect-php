<?php

declare(strict_types=1);

namespace EffectPHP\Schema\AST;

/**
 * AST node for record (key-value map) types
 */
final class RecordType extends BaseASTNode
{
    private ASTNodeInterface $keyType;
    private ASTNodeInterface $valueType;

    public function __construct(
        ASTNodeInterface $keyType,
        ASTNodeInterface $valueType,
        array $annotations = []
    ) {
        $this->keyType = $keyType;
        $this->valueType = $valueType;
        parent::__construct($annotations);
    }

    public function getKeyType(): ASTNodeInterface
    {
        return $this->keyType;
    }

    public function getValueType(): ASTNodeInterface
    {
        return $this->valueType;
    }

    public function accept(ASTVisitorInterface $visitor): mixed
    {
        return $visitor->visitRecordType($this);
    }

    public function withAnnotations(array $newAnnotations): self
    {
        return new self(
            $this->keyType,
            $this->valueType,
            array_merge($this->annotations, $newAnnotations)
        );
    }
}