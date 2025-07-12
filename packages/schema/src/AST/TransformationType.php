<?php declare(strict_types=1);

namespace EffectPHP\Schema\AST;

use EffectPHP\Schema\Contracts\ASTNodeInterface;
use EffectPHP\Schema\Contracts\ASTVisitorInterface;

final class TransformationType extends BaseASTNode
{
    public function __construct(
        private readonly ASTNodeInterface $from,
        private readonly ASTNodeInterface $to,
        private readonly mixed $decode,
        private readonly mixed $encode,
        array $annotations = [],
    ) {
        parent::__construct($annotations);
    }

    public function getFrom(): ASTNodeInterface {
        return $this->from;
    }

    public function getTo(): ASTNodeInterface {
        return $this->to;
    }

    public function getDecode(): mixed {
        return $this->decode;
    }

    public function getEncode(): mixed {
        return $this->encode;
    }

    public function accept(ASTVisitorInterface $visitor): mixed {
        return $visitor->visitTransformationType($this);
    }
}
