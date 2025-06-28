# Array<string, mixed> Gap Analysis

## Problem Identified

The failing tests show validation failures for `AdvancedUser` class with this property:
```php
/**
 * User preferences
 * @var array<string, mixed>
 */
public array $preferences;
```

Test data that fails validation:
```php
'preferences' => [
    'theme' => 'dark',        // string value
    'notifications' => true,   // boolean value  
    'language' => 'en'        // string value
]
```

## Root Cause Analysis

### Current Schema System Limitations

1. **Missing Record/Map Schema Type**
   - EffectTS: `Schema.Record(keySchema, valueSchema)` 
   - PHP Current: Only `Schema::object()` with fixed properties
   - **Gap**: No way to validate associative arrays with dynamic keys

2. **Missing Mixed/Any Type Support**
   - EffectTS: `Schema.Any`, `Schema.Unknown`
   - PHP Current: No equivalent schema types
   - **Gap**: Cannot validate properties that accept multiple types

3. **No Union Helper for Mixed Types**
   - EffectTS: `Schema.Union()` with multiple primitive types
   - PHP Current: Only manual union arrays
   - **Gap**: No convenient way to express "string | boolean | number"

## Critical Gaps to Fill

### 🔴 CRITICAL: Record Schema Implementation

**EffectTS Pattern:**
```typescript
Schema.Record(Schema.String, Schema.Union(
  Schema.String,
  Schema.Number, 
  Schema.Boolean
))
```

**Required PHP Implementation:**
```php
Schema::record(
    Schema::string(),  // key type
    Schema::union([    // value type - mixed
        Schema::string(),
        Schema::number(),
        Schema::boolean()
    ])
)
```

### 🔴 CRITICAL: Any/Unknown Schema Types

**EffectTS Pattern:**
```typescript
Schema.Any        // accepts anything
Schema.Unknown    // accepts anything, requires validation
```

**Required PHP Implementation:**
```php
Schema::any()      // accepts mixed
Schema::unknown()  // accepts mixed with validation
```

### 🔴 CRITICAL: Mixed Type Helper

**Convenience method for common `array<string, mixed>` pattern:**
```php
Schema::mixed()  // shorthand for common mixed union
```

## Implementation Priority

### Phase 1: Core Record Support (1-2 weeks)
1. **RecordSchema class** - validates associative arrays
2. **Schema::record()** factory method
3. **AnySchema class** - accepts any value type
4. **Schema::any()** factory method

### Phase 2: Mixed Type Helpers (1 week)  
1. **Schema::mixed()** - common union of primitives
2. **UnknownSchema class** - validates unknown with checks
3. **Schema::unknown()** factory method

### Phase 3: Enhanced Validation (1 week)
1. **Key validation** for record schemas
2. **Value type inference** from PHPDoc `array<K,V>` annotations
3. **Automatic mixed detection** in reflection system

## Required Changes

### New Schema Classes
```php
// packages/schema/src/Schema/RecordSchema.php
class RecordSchema extends BaseSchema
{
    public function __construct(
        private SchemaInterface $keySchema,
        private SchemaInterface $valueSchema
    ) {}
}

// packages/schema/src/Schema/AnySchema.php  
class AnySchema extends BaseSchema
{
    public function decode(mixed $input): Effect {
        return Eff::succeed($input); // Accept anything
    }
}
```

### Factory Method Updates
```php
// packages/schema/src/Schema.php
public static function record(
    SchemaInterface $keySchema, 
    SchemaInterface $valueSchema
): SchemaInterface {
    return new RecordSchema($keySchema, $valueSchema);
}

public static function any(): SchemaInterface {
    return new AnySchema();
}

public static function mixed(): SchemaInterface {
    return self::union([
        self::string(),
        self::number(), 
        self::boolean(),
        self::any() // for objects/arrays
    ]);
}
```

### Reflection System Updates
```php
// packages/schema/src/Metadata/TypeHintExtractor.php
// Add support for array<string, mixed> parsing
private function parseArrayType(string $type): ?SchemaInterface {
    if (preg_match('/array<(\w+),\s*mixed>/', $type, $matches)) {
        $keyType = $this->mapPrimitiveType($matches[1]);
        return Schema::record($keyType, Schema::mixed());
    }
    // ... existing logic
}
```

## Success Criteria

1. ✅ `array<string, mixed>` properties validate correctly
2. ✅ Record schemas accept dynamic keys  
3. ✅ Mixed values (string|bool|number) validate properly
4. ✅ All failing tests pass
5. ✅ JSON Schema compilation works for records
6. ✅ Full Effect integration maintained

## Testing Requirements

1. **Unit tests** for RecordSchema, AnySchema
2. **Integration tests** for array<string, mixed> reflection
3. **JSON Schema** compilation tests for record types
4. **Effect composition** tests for complex record validation

This implementation will close the primary gap preventing `array<string, mixed>` validation and bring PHP Schema closer to EffectTS feature parity.