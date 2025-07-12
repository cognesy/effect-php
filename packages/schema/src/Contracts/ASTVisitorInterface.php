<?php declare(strict_types=1);

namespace EffectPHP\Schema\Contracts;

use EffectPHP\Schema\AST\AnyType;
use EffectPHP\Schema\AST\ArrayType;
use EffectPHP\Schema\AST\BooleanType;
use EffectPHP\Schema\AST\DateTimeType;
use EffectPHP\Schema\AST\DateType;
use EffectPHP\Schema\AST\EnumType;
use EffectPHP\Schema\AST\LiteralType;
use EffectPHP\Schema\AST\NonEmptyArrayType;
use EffectPHP\Schema\AST\NumberType;
use EffectPHP\Schema\AST\ObjectType;
use EffectPHP\Schema\AST\RecordType;
use EffectPHP\Schema\AST\RefinementType;
use EffectPHP\Schema\AST\StringType;
use EffectPHP\Schema\AST\TransformationType;
use EffectPHP\Schema\AST\TupleType;
use EffectPHP\Schema\AST\UnionType;

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
    public function visitRecordType(RecordType $node): mixed;
    public function visitAnyType(AnyType $node): mixed;
    public function visitTupleType(TupleType $node): mixed;
    public function visitNonEmptyArrayType(NonEmptyArrayType $node): mixed;
    public function visitEnumType(EnumType $node): mixed;

    public function visitDateTimeType(DateTimeType $param);

    public function visitDateType(DateType $param);
}
