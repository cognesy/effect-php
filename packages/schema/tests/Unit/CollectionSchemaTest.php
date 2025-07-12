<?php declare(strict_types=1);

use EffectPHP\Core\Run;
use EffectPHP\Schema\Schema;
use EffectPHP\Schema\Schema\CollectionSchema;

test('basic collection validation', function () {
    $schema = Schema::collection(Schema::string());
    
    $result = Run::syncResult($schema->decode(['foo', 'bar', 'baz']));
    
    expect($result->isSuccess())->toBeTrue();
    expect($result->getValueOrNull())->toBe(['foo', 'bar', 'baz']);
});

test('collection with invalid input type', function () {
    $schema = Schema::collection(Schema::string());
    
    $result = Run::syncResult($schema->decode('not an array'));
    
    expect($result->isFailure())->toBeTrue();
});

test('collection with invalid item types', function () {
    $schema = Schema::collection(Schema::string());
    
    $result = Run::syncResult($schema->decode(['foo', 123, 'baz']));
    
    expect($result->isFailure())->toBeTrue();
});

test('non empty constraint', function () {
    $schema = Schema::collection(Schema::string())->nonEmpty();
    
    // Valid non-empty array
    $result = Run::syncResult($schema->decode(['foo']));
    expect($result->isSuccess())->toBeTrue();
    
    // Invalid empty array
    $result = Run::syncResult($schema->decode([]));
    expect($result->isFailure())->toBeTrue();
});

test('min constraint', function () {
    $schema = Schema::collection(Schema::string())->min(2);
    
    // Valid - meets minimum
    $result = Run::syncResult($schema->decode(['foo', 'bar']));
    expect($result->isSuccess())->toBeTrue();
    
    // Valid - exceeds minimum
    $result = Run::syncResult($schema->decode(['foo', 'bar', 'baz']));
    expect($result->isSuccess())->toBeTrue();
    
    // Invalid - below minimum
    $result = Run::syncResult($schema->decode(['foo']));
    expect($result->isFailure())->toBeTrue();
});

test('max constraint', function () {
    $schema = Schema::collection(Schema::string())->max(2);
    
    // Valid - meets maximum
    $result = Run::syncResult($schema->decode(['foo', 'bar']));
    expect($result->isSuccess())->toBeTrue();
    
    // Valid - below maximum
    $result = Run::syncResult($schema->decode(['foo']));
    expect($result->isSuccess())->toBeTrue();
    
    // Invalid - exceeds maximum
    $result = Run::syncResult($schema->decode(['foo', 'bar', 'baz']));
    expect($result->isFailure())->toBeTrue();
});

test('length constraint', function () {
    $schema = Schema::collection(Schema::string())->length(2);
    
    // Valid - exact length
    $result = Run::syncResult($schema->decode(['foo', 'bar']));
    expect($result->isSuccess())->toBeTrue();
    
    // Invalid - too short
    $result = Run::syncResult($schema->decode(['foo']));
    expect($result->isFailure())->toBeTrue();
    
    // Invalid - too long
    $result = Run::syncResult($schema->decode(['foo', 'bar', 'baz']));
    expect($result->isFailure())->toBeTrue();
});

test('between constraint', function () {
    $schema = Schema::collection(Schema::string())->between(2, 4);
    
    // Valid - within range
    $result = Run::syncResult($schema->decode(['foo', 'bar']));
    expect($result->isSuccess())->toBeTrue();
    
    $result = Run::syncResult($schema->decode(['foo', 'bar', 'baz', 'qux']));
    expect($result->isSuccess())->toBeTrue();
    
    // Invalid - below range
    $result = Run::syncResult($schema->decode(['foo']));
    expect($result->isFailure())->toBeTrue();
    
    // Invalid - above range
    $result = Run::syncResult($schema->decode(['foo', 'bar', 'baz', 'qux', 'quux']));
    expect($result->isFailure())->toBeTrue();
});

test('fluent chaining', function () {
    $schema = Schema::collection(Schema::string())
        ->nonEmpty()
        ->max(5);
    
    // Valid
    $result = Run::syncResult($schema->decode(['foo', 'bar']));
    expect($result->isSuccess())->toBeTrue();
    
    // Invalid - empty
    $result = Run::syncResult($schema->decode([]));
    expect($result->isFailure())->toBeTrue();
    
    // Invalid - too many items
    $result = Run::syncResult($schema->decode(['a', 'b', 'c', 'd', 'e', 'f']));
    expect($result->isFailure())->toBeTrue();
});

test('nested collections', function () {
    $schema = Schema::collection(
        Schema::collection(Schema::string())->nonEmpty()
    );
    
    // Valid nested array
    $result = Run::syncResult($schema->decode([
        ['foo', 'bar'],
        ['baz']
    ]));
    expect($result->isSuccess())->toBeTrue();
    
    // Invalid - empty nested array
    $result = Run::syncResult($schema->decode([
        ['foo', 'bar'],
        []
    ]));
    expect($result->isFailure())->toBeTrue();
});

test('encoding', function () {
    $schema = Schema::collection(Schema::string())->min(1);
    
    // Valid encoding
    $result = Run::syncResult($schema->encode(['foo', 'bar']));
    expect($result->isSuccess())->toBeTrue();
    expect($result->getValueOrNull())->toBe(['foo', 'bar']);
    
    // Invalid encoding - wrong type
    $result = Run::syncResult($schema->encode('not an array'));
    expect($result->isFailure())->toBeTrue();
    
    // Invalid encoding - violates constraints
    $result = Run::syncResult($schema->encode([]));
    expect($result->isFailure())->toBeTrue();
});

test('returns collection schema instance', function () {
    $schema = Schema::collection(Schema::string());
    
    expect($schema)->toBeInstanceOf(CollectionSchema::class);
    expect($schema->nonEmpty())->toBeInstanceOf(CollectionSchema::class);
    expect($schema->min(1))->toBeInstanceOf(CollectionSchema::class);
    expect($schema->max(10))->toBeInstanceOf(CollectionSchema::class);
});