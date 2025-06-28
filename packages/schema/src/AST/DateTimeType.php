<?php

declare(strict_types=1);

namespace EffectPHP\Schema\AST;

final class DateTimeType extends BaseASTNode
{
    public function accept(ASTVisitorInterface $visitor): mixed
    {
        return $visitor->visitDateTimeType($this);
    }
}