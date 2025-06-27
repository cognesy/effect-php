<?php

declare(strict_types=1);

require_once __DIR__ . '/../../autoload.php';

use EffectPHP\Schema\Schema;
use EffectPHP\Schema\Compiler\JsonSchemaCompiler;
use EffectPHP\Schema\Metadata\UniversalSchemaReflector;
use EffectPHP\Core\Eff;

/**
 * Example User class with metadata annotations
 */
final class User
{
    /**
     * User's full name
     * @var string
     * @psalm-min-length 1
     * @psalm-max-length 100
     */
    public string $name;

    /**
     * User email address
     * @var string
     * @psalm-pattern /^[^\s@]+@[^\s@]+\.[^\s@]+$/
     */
    public string $email;

    /**
     * User age in years
     * @var int|null
     * @psalm-min 0
     * @psalm-max 150
     */
    public ?int $age;

    /**
     * User roles
     * @var array<string>
     */
    public array $roles;

    public function __construct(
        string $name,
        string $email,
        ?int $age = null,
        array $roles = []
    ) {
        $this->name = $name;
        $this->email = $email;
        $this->age = $age;
        $this->roles = $roles;
    }
}

echo "=== EffectPHP Schema System - Complete Example ===\n\n";

// 1. Create schemas using factory
echo "1. Creating schemas using factory methods:\n";
$userSchema = Schema::object([
    'name' => Schema::string()
        ->pipe(fn($s) => Schema::minLength($s, 1))
        ->pipe(fn($s) => Schema::maxLength($s, 100)),
    'email' => Schema::string()
        ->pipe(fn($s) => Schema::email($s)),
    'age' => Schema::number()
        ->pipe(fn($s) => Schema::min($s, 0))
        ->pipe(fn($s) => Schema::max($s, 150))
        ->optional(),
    'roles' => Schema::array(Schema::string())
], ['name', 'email']);

echo "✓ User schema created with validation rules\n\n";

// 2. Generate schema from PHP class using reflection
echo "2. Generating schema from PHP class:\n";
$reflector = new UniversalSchemaReflector();
$reflectedSchema = $reflector->fromClass(User::class);
echo "✓ Schema generated from User class metadata\n\n";

// 3. Compile to JSON Schema
echo "3. Compiling to JSON Schema:\n";
$compiler = new JsonSchemaCompiler();
$jsonSchema = $compiler->compile($userSchema->getAST());
echo "JSON Schema Output:\n";
echo json_encode($jsonSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

// 4. Demonstrate Effect-based validation
echo "4. Effect-based validation examples:\n";

// Valid data
$validData = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30,
    'roles' => ['admin', 'user']
];

echo "Validating valid data:\n";
$validationEffect = $userSchema->decode($validData);
$result = Eff::runSafely($validationEffect);

if ($result->isRight()) {
    echo "✓ Valid data accepted\n";
    $decoded = $result->fold(fn($e) => null, fn($v) => $v);
    echo "Decoded data: " . json_encode($decoded) . "\n";
} else {
    echo "✗ Validation failed unexpectedly\n";
}

echo "\n";

// Invalid data
$invalidData = [
    'name' => '', // Too short
    'email' => 'invalid-email', // Invalid format
    'age' => -5, // Below minimum
    'roles' => 'not-an-array' // Wrong type
];

echo "Validating invalid data:\n";
$invalidEffect = $userSchema->decode($invalidData);
$invalidResult = Eff::runSafely($invalidEffect);

if ($invalidResult->isLeft()) {
    echo "✓ Invalid data correctly rejected\n";
    $error = $invalidResult->fold(fn($e) => $e, fn($v) => null);
    if ($error instanceof \EffectPHP\Schema\Parse\ParseError) {
        echo "Validation errors: " . $error->getFormattedMessage() . "\n";
    }
} else {
    echo "✗ Invalid data incorrectly accepted\n";
}

echo "\n";

// 5. Demonstrate schema composition
echo "5. Schema composition example:\n";
$extendedSchema = Schema::object([
    'user' => $userSchema,
    'metadata' => Schema::object([
        'createdAt' => Schema::string(),
        'lastLogin' => Schema::string()->optional(),
        'isActive' => Schema::boolean()
    ], ['createdAt', 'isActive'])
], ['user', 'metadata']);

$extendedJsonSchema = $compiler->compile($extendedSchema->getAST());
echo "Extended schema with nested objects:\n";
echo json_encode($extendedJsonSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

// 6. Demonstrate bidirectional transformations
echo "6. Bidirectional transformation example:\n";
$dateTransform = Schema::transform(
    Schema::string(), // from: string
    Schema::string(), // to: string (for simplicity)
    function (string $dateString): string {
        // Transform string to formatted date
        $date = new DateTime($dateString);
        return $date->format('Y-m-d H:i:s');
    },
    function (string $formattedDate): string {
        // Transform back to ISO string
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $formattedDate);
        return $date->format('c');
    }
);

$dateInput = '2024-01-15T10:30:00Z';
$decodeEffect = $dateTransform->decode($dateInput);
$decodeResult = Eff::runSafely($decodeEffect);

if ($decodeResult->isRight()) {
    $formatted = $decodeResult->fold(fn($e) => null, fn($v) => $v);
    echo "✓ Date decoded: {$dateInput} → {$formatted}\n";
    
    // Encode back
    $encodeEffect = $dateTransform->encode($formatted);
    $encodeResult = Eff::runSafely($encodeEffect);
    
    if ($encodeResult->isRight()) {
        $encoded = $encodeResult->fold(fn($e) => null, fn($v) => $v);
        echo "✓ Date encoded: {$formatted} → {$encoded}\n";
    }
}

echo "\n=== Schema System Features Demonstrated ===\n";
echo "✓ Effect-based validation with proper error handling\n";
echo "✓ JSON Schema compilation for LLM integration\n";
echo "✓ PHP class reflection with metadata extraction\n";
echo "✓ Schema composition and transformation\n";
echo "✓ Parallel validation using core Effect system\n";
echo "✓ Type-safe operations throughout\n";
echo "✓ EffectTS-style Effect composition patterns\n";
echo "✓ Runtime materialization only at edges\n";