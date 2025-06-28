<?php

declare(strict_types=1);

namespace EffectPHP\Schema\Compiler;

use EffectPHP\Schema\AST\AnyType;
use EffectPHP\Schema\AST\ASTNodeInterface;
use EffectPHP\Schema\AST\ASTVisitorInterface;
use EffectPHP\Schema\AST\ArrayType;
use EffectPHP\Schema\AST\BooleanType;
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

final class JsonSchemaCompiler extends BaseCompiler implements ASTVisitorInterface
{
    public function getTarget(): string
    {
        return 'json-schema';
    }

    protected function doCompile(ASTNodeInterface $ast): mixed
    {
        return $ast->accept($this);
    }

    public function visitStringType(StringType $node): array
    {
        $schema = ['type' => 'string'];

        $annotations = $node->getAnnotations();
        if (isset($annotations['minLength'])) {
            $schema['minLength'] = $annotations['minLength'];
        }
        if (isset($annotations['maxLength'])) {
            $schema['maxLength'] = $annotations['maxLength'];
        }
        if (isset($annotations['pattern'])) {
            $schema['pattern'] = $annotations['pattern'];
        }
        if (isset($annotations['format'])) {
            $schema['format'] = $annotations['format'];
        }
        if (isset($annotations['description'])) {
            $schema['description'] = $annotations['description'];
        }

        return $schema;
    }

    public function visitNumberType(NumberType $node): array
    {
        $schema = ['type' => 'number'];

        $annotations = $node->getAnnotations();
        if (isset($annotations['minimum'])) {
            $schema['minimum'] = $annotations['minimum'];
        }
        if (isset($annotations['maximum'])) {
            $schema['maximum'] = $annotations['maximum'];
        }
        if (isset($annotations['description'])) {
            $schema['description'] = $annotations['description'];
        }

        return $schema;
    }

    public function visitBooleanType(BooleanType $node): array
    {
        $schema = ['type' => 'boolean'];

        $annotations = $node->getAnnotations();
        if (isset($annotations['description'])) {
            $schema['description'] = $annotations['description'];
        }

        return $schema;
    }

    public function visitLiteralType(LiteralType $node): array
    {
        return ['const' => $node->getValue()];
    }

    public function visitArrayType(ArrayType $node): array
    {
        $schema = [
            'type' => 'array',
            'items' => $this->compile($node->getItemType())
        ];

        $annotations = $node->getAnnotations();
        if (isset($annotations['minItems'])) {
            $schema['minItems'] = $annotations['minItems'];
        }
        if (isset($annotations['maxItems'])) {
            $schema['maxItems'] = $annotations['maxItems'];
        }
        if (isset($annotations['description'])) {
            $schema['description'] = $annotations['description'];
        }

        return $schema;
    }

    public function visitObjectType(ObjectType $node): array
    {
        $schema = ['type' => 'object'];

        $properties = [];
        foreach ($node->getProperties() as $key => $propertyAST) {
            $properties[$key] = $this->compile($propertyAST);
        }

        if (!empty($properties)) {
            $schema['properties'] = $properties;
        }

        if (!empty($node->getRequired())) {
            $schema['required'] = $node->getRequired();
        }

        $annotations = $node->getAnnotations();
        if (isset($annotations['description'])) {
            $schema['description'] = $annotations['description'];
        }
        if (isset($annotations['additionalProperties'])) {
            $schema['additionalProperties'] = $annotations['additionalProperties'];
        }

        return $schema;
    }

    public function visitUnionType(UnionType $node): array
    {
        $oneOf = [];
        foreach ($node->getTypes() as $type) {
            $oneOf[] = $this->compile($type);
        }

        $schema = ['oneOf' => $oneOf];

        $annotations = $node->getAnnotations();
        if (isset($annotations['description'])) {
            $schema['description'] = $annotations['description'];
        }

        return $schema;
    }

    public function visitRefinementType(RefinementType $node): array
    {
        // For JSON Schema, we compile the base type and add refinement info as description
        $baseSchema = $this->compile($node->getFrom());

        // Merge annotations from the refinement
        $annotations = $node->getAnnotations();
        $hasExplicitDescription = isset($annotations['description']);
        
        foreach ($annotations as $key => $value) {
            $baseSchema[$key] = $value;
        }

        // Only add refinement info if there's no explicit description
        if (!$hasExplicitDescription) {
            $refinementName = $node->getName();
            if (isset($baseSchema['description'])) {
                $baseSchema['description'] .= " (refined: {$refinementName})";
            } else {
                $baseSchema['description'] = "Refined: {$refinementName}";
            }
        }

        return $baseSchema;
    }

    public function visitTransformationType(TransformationType $node): array
    {
        // For JSON Schema, we typically want the input format (from)
        // since that's what external systems will send
        return $this->compile($node->getFrom());
    }

    public function visitRecordType(RecordType $node): array
    {
        $schema = [
            'type' => 'object',
            'additionalProperties' => $this->compile($node->getValueType())
        ];

        // Add key pattern constraint if key type has restrictions
        $keyType = $node->getKeyType();
        if ($keyType instanceof StringType) {
            $keyAnnotations = $keyType->getAnnotations();
            if (isset($keyAnnotations['pattern'])) {
                $schema['patternProperties'] = [
                    $keyAnnotations['pattern'] => $this->compile($node->getValueType())
                ];
                unset($schema['additionalProperties']);
            }
        }

        $annotations = $node->getAnnotations();
        if (isset($annotations['description'])) {
            $schema['description'] = $annotations['description'];
        }

        return $schema;
    }

    public function visitAnyType(AnyType $node): array
    {
        $schema = [];

        $annotations = $node->getAnnotations();
        if (isset($annotations['description'])) {
            $schema['description'] = $annotations['description'];
        }

        return $schema;
    }

    public function visitTupleType(TupleType $node): array
    {
        $itemSchemas = [];
        foreach ($node->getElementTypes() as $elementType) {
            $itemSchemas[] = $this->compile($elementType);
        }

        $schema = [
            'type' => 'array',
            'prefixItems' => $itemSchemas,
            'items' => false, // No additional items allowed
            'minItems' => count($itemSchemas),
            'maxItems' => count($itemSchemas)
        ];

        $annotations = $node->getAnnotations();
        if (isset($annotations['description'])) {
            $schema['description'] = $annotations['description'];
        }

        return $schema;
    }

    public function visitNonEmptyArrayType(NonEmptyArrayType $node): array
    {
        $schema = [
            'type' => 'array',
            'items' => $this->compile($node->getItemType()),
            'minItems' => 1
        ];

        $annotations = $node->getAnnotations();
        if (isset($annotations['description'])) {
            $schema['description'] = $annotations['description'];
        }

        return $schema;
    }

    public function visitEnumType(EnumType $node): array
    {
        $enumClass = $node->getEnumClass();
        $cases = $enumClass::cases();
        
        // Extract enum values for JSON Schema
        $enumValues = [];
        foreach ($cases as $case) {
            $enumValues[] = isset($case->value) ? $case->value : $case->name;
        }

        $schema = ['enum' => $enumValues];

        $annotations = $node->getAnnotations();
        if (isset($annotations['description'])) {
            $schema['description'] = $annotations['description'];
        } else {
            $schema['description'] = "Enum: {$enumClass}";
        }

        return $schema;
    }
}
