<?php

declare(strict_types=1);

namespace EffectPHP\Schema\AST;

interface ASTVisitorInterface
{
    public function visitStringType(StringType $node): mixed;
    public function visitNumberType(NumberType $node): mixed;
    public function visitBooleanType(BooleanType $node): mixed;
    public function visitLiteralType(LiteralType $node): mixed;
    public function visitArrayType(ArrayType $node): mixed;
    public function visitObjectType(ObjectType $node): mixed;
    public function visitUnionType(UnionType $node): mixed;
    public function visitRefinementType(RefinementType $node): mixed;
    public function visitTransformationType(TransformationType $node): mixed;
}
