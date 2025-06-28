<?php

require_once __DIR__ . '/autoload.php';

use EffectPHP\Schema\Schema;
use EffectPHP\Core\Eff;

echo "=== Testing Array<string, mixed> Support ===\n\n";

// Test 1: Manual Schema Creation
echo "1. Manual Schema Creation:\n";
$recordSchema = Schema::record(
    Schema::string(),
    Schema::mixed()
);

$testData = [
    'name' => 'John',
    'age' => 30,
    'active' => true,
    'tags' => ['admin', 'user']
];

$result = Eff::runSafely($recordSchema->decode($testData));
if ($result->isRight()) {
    echo "✓ Record validation passed\n";
    $decoded = $result->fold(fn($e) => null, fn($v) => $v);
    echo "Validated data: " . json_encode($decoded) . "\n";
} else {
    echo "✗ Record validation failed\n";
}

echo "\n";

// Test 2: Class Reflection with array<string, mixed>
echo "2. Class Reflection with array<string, mixed>:\n";

class UserPreferences
{
    /**
     * @var array<string, mixed>
     */
    public array $settings;

    public function __construct(array $settings = [])
    {
        $this->settings = $settings;
    }
}

use EffectPHP\Schema\Metadata\UniversalSchemaReflector;

$reflector = new UniversalSchemaReflector();
$schema = $reflector->fromClass(UserPreferences::class);

$userData = [
    'settings' => [
        'theme' => 'dark',
        'notifications' => true,
        'language' => 'en',
        'preferences' => ['email' => true, 'sms' => false]
    ]
];

$reflectionResult = Eff::runSafely($schema->decode($userData));
if ($reflectionResult->isRight()) {
    echo "✓ Class reflection validation passed\n";
    $decoded = $reflectionResult->fold(fn($e) => null, fn($v) => $v);
    echo "Validated data: " . json_encode($decoded) . "\n";
} else {
    echo "✗ Class reflection validation failed\n";
}

echo "\n";

// Test 3: JSON Schema Compilation
echo "3. JSON Schema Compilation:\n";
use EffectPHP\Schema\Compiler\JsonSchemaCompiler;

$compiler = new JsonSchemaCompiler();
$jsonSchema = $compiler->compile($recordSchema->getAST());

echo "Generated JSON Schema:\n";
echo json_encode($jsonSchema, JSON_PRETTY_PRINT) . "\n";

echo "\n=== All Tests Complete ===\n";