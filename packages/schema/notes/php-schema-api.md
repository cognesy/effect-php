# PHP Schema API Cheatsheet

## ✅ Implemented Primitives
```php
Schema::string()
Schema::number()
Schema::boolean()
Schema::literal($value)
```

## ❌ Missing Primitives
```php
// Missing from EffectTS
Schema::bigint()
Schema::symbol()
Schema::object()  // generic object
Schema::undefined()
Schema::void()
Schema::any()
Schema::unknown()
Schema::never()
```

## ✅ Implemented Collections
```php
Schema::array(Schema::string())
Schema::object(['name' => Schema::string()], ['name'])  // with properties
Schema::union([Schema::string(), Schema::number()])
Schema::record(Schema::string(), Schema::mixed())      // key-value maps
Schema::tuple(Schema::string(), Schema::number())      // fixed-length typed arrays
Schema::nonEmptyArray(Schema::string())                // arrays with min 1 element
```

## ✅ Implemented Unions & Optionals
```php
Schema::union([Schema::string(), Schema::number()])
$schema->optional()  // via interface
$schema->nullable()  // via interface

// Union helpers (EffectTS style)
Schema::nullOr(Schema::string())        // string | null
Schema::nullishOr(Schema::number())     // number | null (same as nullOr in PHP)
Schema::undefinedOr(Schema::boolean())  // boolean | null (same as nullOr in PHP)
```

## ✅ Implemented Filters/Refinements
```php
Schema::string()->pipe(fn($s) => Schema::minLength($s, 1))
Schema::string()->pipe(fn($s) => Schema::maxLength($s, 10))
Schema::number()->pipe(fn($s) => Schema::min($s, 0))
Schema::number()->pipe(fn($s) => Schema::max($s, 100))
Schema::string()->pipe(fn($s) => Schema::email($s))
Schema::string()->pipe(fn($s) => Schema::pattern($s, '/regex/'))
Schema::refine($schema, fn($v) => $v > 0, 'positive')

// String filters
Schema::nonEmptyString()                             // non-empty string schema
Schema::string()->pipe(fn($s) => Schema::startsWith($s, 'prefix'))
Schema::string()->pipe(fn($s) => Schema::endsWith($s, 'suffix'))
Schema::string()->pipe(fn($s) => Schema::trimmed($s))
```

## ❌ Missing Filters
```php
// Missing string filters
Schema::lowercased()
Schema::uppercased()

// Missing number filters
Schema::greaterThan($n)
Schema::lessThan($n)
Schema::between($min, $max)
Schema::int()
Schema::positive()
Schema::nonNegative()

// Missing array filters
Schema::maxItems($n)
Schema::minItems($n)
Schema::itemsCount($n)
```

## ✅ Implemented Transformations
```php
Schema::transform($from, $to, $decode, $encode)
```

## ❌ Missing Built-in Transformations
```php
// Missing string transforms
Schema::split($delimiter)
Schema::trim()
Schema::lowercase()
Schema::uppercase()
Schema::parseJson()
Schema::stringFromBase64()
Schema::stringFromHex()

// Missing number transforms
Schema::numberFromString()
Schema::clamp($min, $max)

// Missing date transforms
Schema::date()  // from string
```

## ✅ Implemented Validation
```php
$schema->decode($input)     // Effect<never, Throwable, A>
$schema->encode($value)     // Effect<never, Throwable, I>
$schema->is($input)         // bool
$schema->assert($input)     // mixed|throw

// Static helper methods (EffectTS style)
Schema::decodeUnknownSync($schema)($input)    // A|throw
Schema::decodeUnknownEither($schema)($input)  // Either<Throwable, A>
Schema::encodeSync($schema)($value)           // I|throw
Schema::encodeEither($schema)($value)         // Either<Throwable, I>
Schema::is($schema)($input)                   // bool
Schema::asserts($schema)($input)              // A|throw
```

## ✅ Implemented Schema Operations
```php
$schema->pipe(fn($s) => $transform($s))
$schema->optional()
$schema->nullable()
$schema->annotate($key, $value)
$schema->compose($other)
```

## ❌ Missing Classes
```php
// Missing class support
class Person extends Schema::class('Person', [
    'name' => Schema::string(),
    'age' => Schema::number()
]) {
    public function greet() { /* ... */ }
}
```

## ❌ Missing Default Values
```php
// Missing constructor defaults
Schema::struct([
    'name' => Schema::string(),
    'age' => Schema::withConstructorDefault(Schema::number(), fn() => 0)
])

// Missing optional with defaults
Schema::optionalWith(Schema::string(), ['default' => fn() => ''])
```

## ❌ Missing Brands
```php
// Missing brand types
$UserId = Schema::number()->pipe(Schema::brand('UserId'))
```

## ❌ Missing Template Literals
```php
// Missing template literal support
Schema::templateLiteral($parts)
```

## ❌ Missing Enums
```php
// Missing enum support
Schema::enums(StatusEnum::class)
```

## ✅ Implemented Extras
```php
// PHP-specific features
UniversalSchemaReflector::fromClass(User::class)  // Class reflection
JsonSchemaCompiler::compile($ast)                 // JSON Schema output
```

## Summary
- **✅ Core functionality**: Primitives, collections, transformations, validation
- **✅ Effect integration**: Full Effect-based API
- **✅ PHP-specific**: Class reflection, JSON Schema compilation
- **❌ Missing ~60%** of EffectTS API surface area
- **❌ Major gaps**: Classes, defaults, brands, built-in transforms, filter helpers