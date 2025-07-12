<?php declare(strict_types=1);

use EffectPHP\Core\Run;
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
    $result = Run::syncResult($schema->decode('active'));
    expect($result->isSuccess())->toBeTrue();
    expect($result->getValueOrNull())->toBe(Status::ACTIVE);
    
    // Valid backed enum value
    $result = Run::syncResult($schema->decode('pending'));
    expect($result->isSuccess())->toBeTrue();
    expect($result->getValueOrNull())->toBe(Status::PENDING);
    
    // Invalid value
    $result = Run::syncResult($schema->decode('invalid'));
    expect($result->isFailure())->toBeTrue();
});

test('unit enum validation', function () {
    $schema = Schema::enum(Priority::class);
    
    // Valid unit enum name
    $result = Run::syncResult($schema->decode('LOW'));
    expect($result->isSuccess())->toBeTrue();
    expect($result->getValueOrNull())->toBe(Priority::LOW);
    
    // Valid unit enum name
    $result = Run::syncResult($schema->decode('HIGH'));
    expect($result->isSuccess())->toBeTrue();
    expect($result->getValueOrNull())->toBe(Priority::HIGH);
    
    // Invalid name
    $result = Run::syncResult($schema->decode('INVALID'));
    expect($result->isFailure())->toBeTrue();
});

test('backed enum encoding', function () {
    $schema = Schema::enum(Status::class);
    
    // Encode backed enum to value
    $result = Run::syncResult($schema->encode(Status::ACTIVE));
    expect($result->isSuccess())->toBeTrue();
    expect($result->getValueOrNull())->toBe('active');
    
    // Invalid input
    $result = Run::syncResult($schema->encode('not an enum'));
    expect($result->isFailure())->toBeTrue();
});

test('unit enum encoding', function () {
    $schema = Schema::enum(Priority::class);
    
    // Encode unit enum to name
    $result = Run::syncResult($schema->encode(Priority::HIGH));
    expect($result->isSuccess())->toBeTrue();
    expect($result->getValueOrNull())->toBe('HIGH');
    
    // Invalid input
    $result = Run::syncResult($schema->encode('not an enum'));
    expect($result->isFailure())->toBeTrue();
});

test('enum collection', function () {
    $schema = Schema::collection(Schema::enum(Status::class));
    
    // Valid enum collection
    $result = Run::syncResult($schema->decode(['active', 'pending', 'inactive']));
    expect($result->isSuccess())->toBeTrue();
    expect($result->getValueOrNull())->toBe([Status::ACTIVE, Status::PENDING, Status::INACTIVE]);
    
    // Invalid enum in collection
    $result = Run::syncResult($schema->decode(['active', 'invalid']));
    expect($result->isFailure())->toBeTrue();
});

test('collection of enum shorthand', function () {
    $schema = Schema::collectionOf(Status::class);
    
    // Valid enum collection using shorthand
    $result = Run::syncResult($schema->decode(['active', 'pending']));
    expect($result->isSuccess())->toBeTrue();
    expect($result->getValueOrNull())->toBe([Status::ACTIVE, Status::PENDING]);
});

test('enum collection with constraints', function () {
    $schema = Schema::collectionOf(Status::class)->nonEmpty()->max(3);
    
    // Valid
    $result = Run::syncResult($schema->decode(['active', 'pending']));
    expect($result->isSuccess())->toBeTrue();
    
    // Invalid - empty
    $result = Run::syncResult($schema->decode([]));
    expect($result->isFailure())->toBeTrue();
    
    // Invalid - too many
    $result = Run::syncResult($schema->decode(['active', 'pending', 'inactive', 'active']));
    expect($result->isFailure())->toBeTrue();
});

test('invalid enum class', function () {
    expect(fn() => Schema::enum(\stdClass::class))
        ->toThrow(\InvalidArgumentException::class, 'Class stdClass is not an enum');
});

test('non existent class', function () {
    expect(fn() => Schema::collectionOf('NonExistentClass'))
        ->toThrow(\InvalidArgumentException::class);
});