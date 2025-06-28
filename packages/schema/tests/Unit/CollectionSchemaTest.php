<?php

declare(strict_types=1);

use EffectPHP\Core\Eff;
use EffectPHP\Schema\Schema;
use EffectPHP\Schema\Schema\CollectionSchema;

test('basic collection validation', function () {
    $schema = Schema::collection(Schema::string());
    
    $result = Eff::runSafely($schema->decode(['foo', 'bar', 'baz']));
    
    expect($result->isRight())->toBeTrue();
    expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(['foo', 'bar', 'baz']);
});

test('collection with invalid input type', function () {
    $schema = Schema::collection(Schema::string());
    
    $result = Eff::runSafely($schema->decode('not an array'));
    
    expect($result->isLeft())->toBeTrue();
});

test('collection with invalid item types', function () {
    $schema = Schema::collection(Schema::string());
    
    $result = Eff::runSafely($schema->decode(['foo', 123, 'baz']));
    
    expect($result->isLeft())->toBeTrue();
});

test('non empty constraint', function () {
    $schema = Schema::collection(Schema::string())->nonEmpty();
    
    // Valid non-empty array
    $result = Eff::runSafely($schema->decode(['foo']));
    expect($result->isRight())->toBeTrue();
    
    // Invalid empty array
    $result = Eff::runSafely($schema->decode([]));
    expect($result->isLeft())->toBeTrue();
});

test('min constraint', function () {
    $schema = Schema::collection(Schema::string())->min(2);
    
    // Valid - meets minimum
    $result = Eff::runSafely($schema->decode(['foo', 'bar']));
    expect($result->isRight())->toBeTrue();
    
    // Valid - exceeds minimum
    $result = Eff::runSafely($schema->decode(['foo', 'bar', 'baz']));
    expect($result->isRight())->toBeTrue();
    
    // Invalid - below minimum
    $result = Eff::runSafely($schema->decode(['foo']));
    expect($result->isLeft())->toBeTrue();
});

test('max constraint', function () {
    $schema = Schema::collection(Schema::string())->max(2);
    
    // Valid - meets maximum
    $result = Eff::runSafely($schema->decode(['foo', 'bar']));
    expect($result->isRight())->toBeTrue();
    
    // Valid - below maximum
    $result = Eff::runSafely($schema->decode(['foo']));
    expect($result->isRight())->toBeTrue();
    
    // Invalid - exceeds maximum
    $result = Eff::runSafely($schema->decode(['foo', 'bar', 'baz']));
    expect($result->isLeft())->toBeTrue();
});

test('length constraint', function () {
    $schema = Schema::collection(Schema::string())->length(2);
    
    // Valid - exact length
    $result = Eff::runSafely($schema->decode(['foo', 'bar']));
    expect($result->isRight())->toBeTrue();
    
    // Invalid - too short
    $result = Eff::runSafely($schema->decode(['foo']));
    expect($result->isLeft())->toBeTrue();
    
    // Invalid - too long
    $result = Eff::runSafely($schema->decode(['foo', 'bar', 'baz']));
    expect($result->isLeft())->toBeTrue();
});

test('between constraint', function () {
    $schema = Schema::collection(Schema::string())->between(2, 4);
    
    // Valid - within range
    $result = Eff::runSafely($schema->decode(['foo', 'bar']));
    expect($result->isRight())->toBeTrue();
    
    $result = Eff::runSafely($schema->decode(['foo', 'bar', 'baz', 'qux']));
    expect($result->isRight())->toBeTrue();
    
    // Invalid - below range
    $result = Eff::runSafely($schema->decode(['foo']));
    expect($result->isLeft())->toBeTrue();
    
    // Invalid - above range
    $result = Eff::runSafely($schema->decode(['foo', 'bar', 'baz', 'qux', 'quux']));
    expect($result->isLeft())->toBeTrue();
});

test('fluent chaining', function () {
    $schema = Schema::collection(Schema::string())
        ->nonEmpty()
        ->max(5);
    
    // Valid
    $result = Eff::runSafely($schema->decode(['foo', 'bar']));
    expect($result->isRight())->toBeTrue();
    
    // Invalid - empty
    $result = Eff::runSafely($schema->decode([]));
    expect($result->isLeft())->toBeTrue();
    
    // Invalid - too many items
    $result = Eff::runSafely($schema->decode(['a', 'b', 'c', 'd', 'e', 'f']));
    expect($result->isLeft())->toBeTrue();
});

test('nested collections', function () {
    $schema = Schema::collection(
        Schema::collection(Schema::string())->nonEmpty()
    );
    
    // Valid nested array
    $result = Eff::runSafely($schema->decode([
        ['foo', 'bar'],
        ['baz']
    ]));
    expect($result->isRight())->toBeTrue();
    
    // Invalid - empty nested array
    $result = Eff::runSafely($schema->decode([
        ['foo', 'bar'],
        []
    ]));
    expect($result->isLeft())->toBeTrue();
});

test('encoding', function () {
    $schema = Schema::collection(Schema::string())->min(1);
    
    // Valid encoding
    $result = Eff::runSafely($schema->encode(['foo', 'bar']));
    expect($result->isRight())->toBeTrue();
    expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(['foo', 'bar']);
    
    // Invalid encoding - wrong type
    $result = Eff::runSafely($schema->encode('not an array'));
    expect($result->isLeft())->toBeTrue();
    
    // Invalid encoding - violates constraints
    $result = Eff::runSafely($schema->encode([]));
    expect($result->isLeft())->toBeTrue();
});

test('returns collection schema instance', function () {
    $schema = Schema::collection(Schema::string());
    
    expect($schema)->toBeInstanceOf(CollectionSchema::class);
    expect($schema->nonEmpty())->toBeInstanceOf(CollectionSchema::class);
    expect($schema->min(1))->toBeInstanceOf(CollectionSchema::class);
    expect($schema->max(10))->toBeInstanceOf(CollectionSchema::class);
});