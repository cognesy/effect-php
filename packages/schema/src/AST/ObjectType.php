<?php declare(strict_types=1);

namespace EffectPHP\Schema\AST;

use EffectPHP\Schema\Contracts\ASTVisitorInterface;

final class ObjectType extends BaseASTNode
{
    public function __construct(
        private readonly array $properties,
        private readonly array $required = [],
        array $annotations = [],
    ) {
        parent::__construct($annotations);
    }

    public function getProperties(): array {
        return $this->properties;
    }

    public function getRequired(): array {
        return $this->required;
    }

    public function accept(ASTVisitorInterface $visitor): mixed {
        return $visitor->visitObjectType($this);
    }
}
