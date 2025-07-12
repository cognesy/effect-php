<?php declare(strict_types=1);

namespace EffectPHP\Schema\AST;

use EffectPHP\Schema\Contracts\ASTNodeInterface;
use EffectPHP\Schema\Contracts\ASTVisitorInterface;

final class RefinementType extends BaseASTNode
{
    public function __construct(
        private readonly ASTNodeInterface $from,
        private readonly mixed $predicate,
        private readonly string $name,
        array $annotations = [],
    ) {
        parent::__construct($annotations);
    }

    public function getFrom(): ASTNodeInterface {
        return $this->from;
    }

    public function getPredicate(): mixed {
        return $this->predicate;
    }

    public function getName(): string {
        return $this->name;
    }

    public function accept(ASTVisitorInterface $visitor): mixed {
        return $visitor->visitRefinementType($this);
    }
}
