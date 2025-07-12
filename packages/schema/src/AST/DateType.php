<?php declare(strict_types=1);

namespace EffectPHP\Schema\AST;

use EffectPHP\Schema\Contracts\ASTVisitorInterface;

final class DateType extends BaseASTNode
{
    public function accept(ASTVisitorInterface $visitor): mixed {
        return $visitor->visitDateType($this);
    }
}