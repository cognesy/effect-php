<?php

declare(strict_types=1);

use EffectPHP\Core\Eff;
use EffectPHP\Schema\Schema;

// Test enums
enum Status: string {
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING = 'pending';
}

enum Priority {
    case LOW;
    case MEDIUM;
    case HIGH;
}

test('backed enum validation', function () {
    $schema = Schema::enum(Status::class);
    
    // Valid backed enum value
    $result = Eff::runSafely($schema->decode('active'));
    expect($result->isRight())->toBeTrue();
    expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(Status::ACTIVE);
    
    // Valid backed enum value
    $result = Eff::runSafely($schema->decode('pending'));
    expect($result->isRight())->toBeTrue();
    expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(Status::PENDING);
    
    // Invalid value
    $result = Eff::runSafely($schema->decode('invalid'));
    expect($result->isLeft())->toBeTrue();
});

test('unit enum validation', function () {
    $schema = Schema::enum(Priority::class);
    
    // Valid unit enum name
    $result = Eff::runSafely($schema->decode('LOW'));
    expect($result->isRight())->toBeTrue();
    expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(Priority::LOW);
    
    // Valid unit enum name
    $result = Eff::runSafely($schema->decode('HIGH'));
    expect($result->isRight())->toBeTrue();
    expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(Priority::HIGH);
    
    // Invalid name
    $result = Eff::runSafely($schema->decode('INVALID'));
    expect($result->isLeft())->toBeTrue();
});

test('backed enum encoding', function () {
    $schema = Schema::enum(Status::class);
    
    // Encode backed enum to value
    $result = Eff::runSafely($schema->encode(Status::ACTIVE));
    expect($result->isRight())->toBeTrue();
    expect($result->fold(fn($e) => null, fn($v) => $v))->toBe('active');
    
    // Invalid input
    $result = Eff::runSafely($schema->encode('not an enum'));
    expect($result->isLeft())->toBeTrue();
});

test('unit enum encoding', function () {
    $schema = Schema::enum(Priority::class);
    
    // Encode unit enum to name
    $result = Eff::runSafely($schema->encode(Priority::HIGH));
    expect($result->isRight())->toBeTrue();
    expect($result->fold(fn($e) => null, fn($v) => $v))->toBe('HIGH');
    
    // Invalid input
    $result = Eff::runSafely($schema->encode('not an enum'));
    expect($result->isLeft())->toBeTrue();
});

test('enum collection', function () {
    $schema = Schema::collection(Schema::enum(Status::class));
    
    // Valid enum collection
    $result = Eff::runSafely($schema->decode(['active', 'pending', 'inactive']));
    expect($result->isRight())->toBeTrue();
    expect($result->fold(fn($e) => null, fn($v) => $v))->toBe([Status::ACTIVE, Status::PENDING, Status::INACTIVE]);
    
    // Invalid enum in collection
    $result = Eff::runSafely($schema->decode(['active', 'invalid']));
    expect($result->isLeft())->toBeTrue();
});

test('collection of enum shorthand', function () {
    $schema = Schema::collectionOf(Status::class);
    
    // Valid enum collection using shorthand
    $result = Eff::runSafely($schema->decode(['active', 'pending']));
    expect($result->isRight())->toBeTrue();
    expect($result->fold(fn($e) => null, fn($v) => $v))->toBe([Status::ACTIVE, Status::PENDING]);
});

test('enum collection with constraints', function () {
    $schema = Schema::collectionOf(Status::class)->nonEmpty()->max(3);
    
    // Valid
    $result = Eff::runSafely($schema->decode(['active', 'pending']));
    expect($result->isRight())->toBeTrue();
    
    // Invalid - empty
    $result = Eff::runSafely($schema->decode([]));
    expect($result->isLeft())->toBeTrue();
    
    // Invalid - too many
    $result = Eff::runSafely($schema->decode(['active', 'pending', 'inactive', 'active']));
    expect($result->isLeft())->toBeTrue();
});

test('invalid enum class', function () {
    expect(fn() => Schema::enum(\stdClass::class))
        ->toThrow(\InvalidArgumentException::class, 'Class stdClass is not an enum');
});

test('non existent class', function () {
    expect(fn() => Schema::collectionOf('NonExistentClass'))
        ->toThrow(\InvalidArgumentException::class);
});