<?php

require_once __DIR__ . '/autoload.php';

use EffectPHP\Schema\Schema;
use EffectPHP\Core\Eff;

echo "=== Testing Collection Schema Types ===\n\n";

// Test 1: Record Schema
echo "1. Testing Record Schema:\n";
$recordSchema = Schema::record(Schema::string(), Schema::mixed());

$recordData = [
    'name' => 'John',
    'age' => 30,
    'active' => true
];

$recordResult = Eff::runSafely($recordSchema->decode($recordData));
if ($recordResult->isRight()) {
    echo "✓ Record schema validation passed\n";
    $decoded = $recordResult->fold(fn($e) => null, fn($v) => $v);
    echo "Record data: " . json_encode($decoded) . "\n";
} else {
    echo "✗ Record schema validation failed\n";
}

echo "\n";

// Test 2: Tuple Schema
echo "2. Testing Tuple Schema:\n";
$tupleSchema = Schema::tuple(
    Schema::string(),
    Schema::number(),
    Schema::boolean()
);

$tupleData = ["Alice", 25, true];

$tupleResult = Eff::runSafely($tupleSchema->decode($tupleData));
if ($tupleResult->isRight()) {
    echo "✓ Tuple schema validation passed\n";
    $decoded = $tupleResult->fold(fn($e) => null, fn($v) => $v);
    echo "Tuple data: " . json_encode($decoded) . "\n";
} else {
    echo "✗ Tuple schema validation failed\n";
    $error = $tupleResult->fold(fn($e) => $e, fn($v) => null);
    echo "Error: " . $error->getMessage() . "\n";
}

// Test wrong tuple length
echo "Testing tuple with wrong length:\n";
$wrongTupleData = ["Alice", 25]; // Missing boolean
$wrongTupleResult = Eff::runSafely($tupleSchema->decode($wrongTupleData));
if ($wrongTupleResult->isLeft()) {
    echo "✓ Tuple correctly rejected wrong length\n";
} else {
    echo "✗ Tuple incorrectly accepted wrong length\n";
}

echo "\n";

// Test 3: NonEmptyArray Schema
echo "3. Testing NonEmptyArray Schema:\n";
$nonEmptyArraySchema = Schema::nonEmptyArray(Schema::string());

$nonEmptyData = ["apple", "banana", "cherry"];

$nonEmptyResult = Eff::runSafely($nonEmptyArraySchema->decode($nonEmptyData));
if ($nonEmptyResult->isRight()) {
    echo "✓ NonEmptyArray schema validation passed\n";
    $decoded = $nonEmptyResult->fold(fn($e) => null, fn($v) => $v);
    echo "NonEmptyArray data: " . json_encode($decoded) . "\n";
} else {
    echo "✗ NonEmptyArray schema validation failed\n";
}

// Test empty array rejection
echo "Testing empty array rejection:\n";
$emptyData = [];
$emptyResult = Eff::runSafely($nonEmptyArraySchema->decode($emptyData));
if ($emptyResult->isLeft()) {
    echo "✓ NonEmptyArray correctly rejected empty array\n";
} else {
    echo "✗ NonEmptyArray incorrectly accepted empty array\n";
}

echo "\n";

// Test 4: JSON Schema Compilation
echo "4. Testing JSON Schema Compilation:\n";
use EffectPHP\Schema\Compiler\JsonSchemaCompiler;

$compiler = new JsonSchemaCompiler();

echo "Record Schema JSON:\n";
$recordJson = $compiler->compile($recordSchema->getAST());
echo json_encode($recordJson, JSON_PRETTY_PRINT) . "\n\n";

echo "Tuple Schema JSON:\n";
$tupleJson = $compiler->compile($tupleSchema->getAST());
echo json_encode($tupleJson, JSON_PRETTY_PRINT) . "\n\n";

echo "NonEmptyArray Schema JSON:\n";
$nonEmptyJson = $compiler->compile($nonEmptyArraySchema->getAST());
echo json_encode($nonEmptyJson, JSON_PRETTY_PRINT) . "\n\n";

// Test 5: Complex nested example
echo "5. Testing Complex Nested Schema:\n";
$complexSchema = Schema::object([
    'id' => Schema::number(),
    'coordinates' => Schema::tuple(Schema::number(), Schema::number()), // [x, y]
    'tags' => Schema::nonEmptyArray(Schema::string()),
    'metadata' => Schema::record(Schema::string(), Schema::mixed())
], ['id', 'coordinates', 'tags', 'metadata']);

$complexData = [
    'id' => 123,
    'coordinates' => [10.5, 20.3],
    'tags' => ['location', 'public'],
    'metadata' => [
        'created' => '2024-01-01',
        'visible' => true,
        'priority' => 5
    ]
];

$complexResult = Eff::runSafely($complexSchema->decode($complexData));
if ($complexResult->isRight()) {
    echo "✓ Complex nested schema validation passed\n";
    $decoded = $complexResult->fold(fn($e) => null, fn($v) => $v);
    echo "Complex data: " . json_encode($decoded) . "\n";
} else {
    echo "✗ Complex nested schema validation failed\n";
    $error = $complexResult->fold(fn($e) => $e, fn($v) => null);
    echo "Error: " . $error->getMessage() . "\n";
}

echo "\n=== Collection Schema Tests Complete ===\n";