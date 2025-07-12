# EffectPHP Schema

A powerful schema validation and transformation library for PHP, inspired by EffectTS Schema. Provides type-safe, composable schemas with Effect-based error handling and bidirectional data transformation.

## Features

- **Type-safe validation** - Validate data against schemas with precise error reporting
- **Bidirectional transformation** - Convert between different data representations (decode/encode)
- **Effect-based composition** - Leverage EffectPHP's Effect system for error handling
- **Rich schema types** - Comprehensive set of built-in schema types
- **Extensible architecture** - Easy to extend with custom schemas and transformations

## Installation

```bash
composer require effect-php/schema
```

## Quick Start

```php
use EffectPHP\Schema\Schema;

// Basic string validation
$schema = Schema::string();
$result = Run::syncResult($schema->decode('hello world'));
// Result: Right('hello world')

// Object validation with required fields
$userSchema = Schema::object([
    'name' => Schema::string(),
    'email' => Schema::email(Schema::string()),
    'age' => Schema::number(),
    'created_at' => Schema::datetime(),
], ['name', 'email', 'age']);

$userData = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30,
    'created_at' => '2023-12-25T15:30:00+00:00'
];

$result = Run::syncResult($userSchema->decode($userData));
// Result: Right with validated data and DateTime object
```

## Schema Types

### Primitive Types

```php
// Basic types
Schema::string()        // string validation
Schema::number()        // numeric validation
Schema::boolean()       // boolean validation
Schema::literal('foo')  // exact value matching

// Date/Time types
Schema::date()          // Date validation (Y-m-d format)
Schema::datetime()      // DateTime validation (ISO 8601)
```

### String Refinements

```php
// Length constraints
Schema::minLength(Schema::string(), 3)
Schema::maxLength(Schema::string(), 100)
Schema::nonEmptyString()

// Pattern matching
Schema::email(Schema::string())
Schema::pattern(Schema::string(), '/^[A-Z]{2,}$/')
Schema::startsWith(Schema::string(), 'prefix_')
Schema::endsWith(Schema::string(), '_suffix')
```

### Numeric Refinements

```php
// Value constraints
Schema::min(Schema::number(), 0)
Schema::max(Schema::number(), 100)
```

### Collection Types

```php
// Arrays
Schema::array(Schema::string())                    // Array of strings
Schema::nonEmptyArray(Schema::number())            // Non-empty array
Schema::tuple(Schema::string(), Schema::number())  // Fixed-length tuple

// Objects
Schema::object([
    'id' => Schema::number(),
    'name' => Schema::string(),
], ['id', 'name'])  // Required fields specified

// Records (key-value maps)
Schema::record(Schema::string(), Schema::number())  // Map<string, number>
```

### Advanced Types

```php
// Union types
Schema::union([
    Schema::string(),
    Schema::number()
])

// Nullable types
Schema::nullOr(Schema::string())  // string | null

// Enums
Schema::enum(['draft', 'published', 'archived'])

// Collections with mixed types
Schema::collection(Schema::string())  // Flexible collection handling
```

## Transformations

Schemas support bidirectional transformations between different data representations:

```php
// Date transformation example
$dateSchema = Schema::date();

// Decode: string -> DateTime
$result = Run::syncResult($dateSchema->decode('2023-12-25'));
$date = $result->fold(fn($e) => null, fn($v) => $v); // DateTime object

// Encode: DateTime -> string
$result = Run::syncResult($dateSchema->encode($date));
$dateString = $result->fold(fn($e) => null, fn($v) => $v); // '2023-12-25'

// Custom transformations
$uppercaseSchema = Schema::transform(
    Schema::string(),
    Schema::string(),
    fn($str) => strtoupper($str),      // decode transformation
    fn($str) => strtolower($str)       // encode transformation
);
```

## Error Handling

Schemas return Effects that can be safely handled:

```php
$schema = Schema::object([
    'email' => Schema::email(Schema::string()),
    'age' => Schema::min(Schema::number(), 0),
], ['email', 'age']);

$result = Run::syncResult($schema->decode([
    'email' => 'invalid-email',
    'age' => -5
]));

if ($result->isFailure()) {
    $error = $result->fold(fn($e) => $e, fn($v) => null);
    // ParseError with detailed issue information
    foreach ($error->getIssues() as $issue) {
        echo "Field: " . implode('.', $issue->getPath()) . "\n";
        echo "Error: " . $issue->getMessage() . "\n";
    }
}
```

## Schema Composition

Schemas are highly composable and can be combined in various ways:

```php
// Reusable schemas
$emailSchema = Schema::email(Schema::string());
$positiveNumberSchema = Schema::min(Schema::number(), 0);

// Complex object schema
$articleSchema = Schema::object([
    'title' => Schema::minLength(Schema::string(), 1),
    'content' => Schema::string(),
    'author' => Schema::object([
        'name' => Schema::string(),
        'email' => $emailSchema,
    ], ['name', 'email']),
    'tags' => Schema::array(Schema::string()),
    'published_at' => Schema::nullOr(Schema::datetime()),
    'view_count' => $positiveNumberSchema,
], ['title', 'content', 'author']);

// Array of articles
$articlesSchema = Schema::array($articleSchema);
```

## JSON Schema Compilation

Convert schemas to JSON Schema format for API documentation and client generation:

```php
use EffectPHP\Schema\Compiler\JsonSchemaCompiler;

$compiler = new JsonSchemaCompiler();
$jsonSchema = $compiler->compile($userSchema);

// Outputs standard JSON Schema
echo json_encode($jsonSchema, JSON_PRETTY_PRINT);
```

## Testing

Run the test suite:

```bash
# Run all tests
./vendor/bin/pest

# Run specific test file
./vendor/bin/pest tests/Unit/DateTimeTest.php

# Run with coverage
./vendor/bin/pest --coverage
```

## Architecture

The library follows a layered architecture:

- **Schema Layer** - High-level schema definitions and factory methods
- **AST Layer** - Abstract Syntax Tree representations of schemas
- **Parser Layer** - Validation and transformation logic
- **Compiler Layer** - Code generation and schema compilation

## Contributing

1. Fork the repository
2. Create a feature branch
3. Add tests for new functionality
4. Ensure all tests pass
5. Submit a pull request

## License

MIT License - see LICENSE file for details.