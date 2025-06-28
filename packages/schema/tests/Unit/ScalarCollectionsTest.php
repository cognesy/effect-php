<?php

declare(strict_types=1);

use EffectPHP\Core\Eff;
use EffectPHP\Schema\Schema;

describe('String Collections', function () {
    test('basic string collection', function () {
        $schema = Schema::collection(Schema::string());
        
        $result = Eff::runSafely($schema->decode(['hello', 'world', 'test']));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(['hello', 'world', 'test']);
    });

    test('string collection with refinements', function () {
        $schema = Schema::collection(
            Schema::minLength(Schema::string(), 3)
        )->nonEmpty();
        
        // Valid - all strings meet min length
        $result = Eff::runSafely($schema->decode(['hello', 'world']));
        expect($result->isRight())->toBeTrue();
        
        // Invalid - string too short
        $result = Eff::runSafely($schema->decode(['hello', 'hi']));
        expect($result->isLeft())->toBeTrue();
    });

    test('email collection', function () {
        $schema = Schema::collection(
            Schema::email(Schema::string())
        )->max(3);
        
        $emails = ['test@example.com', 'user@domain.org'];
        $result = Eff::runSafely($schema->decode($emails));
        expect($result->isRight())->toBeTrue();
        
        // Invalid email
        $result = Eff::runSafely($schema->decode(['test@example.com', 'invalid-email']));
        expect($result->isLeft())->toBeTrue();
    });
});

describe('Number Collections', function () {
    test('basic number collection', function () {
        $schema = Schema::collection(Schema::number());
        
        $result = Eff::runSafely($schema->decode([1, 2.5, 42, 3.14159]));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe([1, 2.5, 42, 3.14159]);
    });

    test('number collection with range constraints', function () {
        $schema = Schema::collection(
            Schema::min(Schema::max(Schema::number(), 100), 0)
        )->between(2, 5);
        
        // Valid - all numbers in range [0, 100]
        $result = Eff::runSafely($schema->decode([10, 50, 99]));
        expect($result->isRight())->toBeTrue();
        
        // Invalid - number out of range
        $result = Eff::runSafely($schema->decode([10, 150]));
        expect($result->isLeft())->toBeTrue();
        
        // Invalid - too few items
        $result = Eff::runSafely($schema->decode([50]));
        expect($result->isLeft())->toBeTrue();
    });

    test('integer-like number collection', function () {
        $schema = Schema::collection(Schema::integer())->length(3);
        
        // Valid integers
        $result = Eff::runSafely($schema->decode([1, 42, 999]));
        expect($result->isRight())->toBeTrue();
        
        // Invalid - contains float
        $result = Eff::runSafely($schema->decode([1, 2.5, 3]));
        expect($result->isLeft())->toBeTrue();
    });
});

describe('Boolean Collections', function () {
    test('basic boolean collection', function () {
        $schema = Schema::collection(Schema::boolean());
        
        $result = Eff::runSafely($schema->decode([true, false, true, false]));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe([true, false, true, false]);
    });

    test('boolean collection with constraints', function () {
        $schema = Schema::collection(Schema::boolean())
            ->min(2)
            ->max(4);
        
        // Valid
        $result = Eff::runSafely($schema->decode([true, false, true]));
        expect($result->isRight())->toBeTrue();
        
        // Invalid - wrong type
        $result = Eff::runSafely($schema->decode([true, 'false']));
        expect($result->isLeft())->toBeTrue();
    });
});

describe('Literal Collections', function () {
    test('literal value collection', function () {
        $schema = Schema::collection(Schema::literal('active'));
        
        $result = Eff::runSafely($schema->decode(['active', 'active', 'active']));
        expect($result->isRight())->toBeTrue();
        
        // Invalid - wrong literal
        $result = Eff::runSafely($schema->decode(['active', 'inactive']));
        expect($result->isLeft())->toBeTrue();
    });

    test('mixed literal collection', function () {
        $schema = Schema::collection(
            Schema::union([
                Schema::literal('yes'),
                Schema::literal('no'),
                Schema::literal(null)
            ])
        )->nonEmpty();
        
        $result = Eff::runSafely($schema->decode(['yes', 'no', null, 'yes']));
        expect($result->isRight())->toBeTrue();
        
        // Invalid literal
        $result = Eff::runSafely($schema->decode(['yes', 'maybe']));
        expect($result->isLeft())->toBeTrue();
    });
});

describe('Complex Scalar Combinations', function () {
    test('collection of refined strings with patterns', function () {
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        $schema = Schema::collection(
            Schema::pattern(Schema::string(), $uuidPattern)
        )->between(1, 10);
        
        $validUuids = [
            '550e8400-e29b-41d4-a716-446655440000',
            '6ba7b810-9dad-11d1-80b4-00c04fd430c8'
        ];
        
        $result = Eff::runSafely($schema->decode($validUuids));
        expect($result->isRight())->toBeTrue();
        
        // Invalid UUID format
        $result = Eff::runSafely($schema->decode(['550e8400-e29b-41d4-a716-446655440000', 'invalid-uuid']));
        expect($result->isLeft())->toBeTrue();
    });

    test('collection of constrained numbers with encoding', function () {
        $percentageSchema = Schema::min(Schema::max(Schema::number(), 100), 0);
        $schema = Schema::collection($percentageSchema)->max(5);
        
        // Test decoding
        $result = Eff::runSafely($schema->decode([0, 25.5, 50, 75.75, 100]));
        expect($result->isRight())->toBeTrue();
        
        // Test encoding
        $result = Eff::runSafely($schema->encode([10, 20, 30]));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe([10, 20, 30]);
    });

    test('nested scalar collections', function () {
        // Collection of string collections (matrix-like)
        $schema = Schema::collection(
            Schema::collection(Schema::string())->length(2)
        )->min(1);
        
        $matrix = [
            ['a', 'b'],
            ['c', 'd'],
            ['e', 'f']
        ];
        
        $result = Eff::runSafely($schema->decode($matrix));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe($matrix);
        
        // Invalid - inner array wrong length
        $result = Eff::runSafely($schema->decode([['a', 'b'], ['c']]));
        expect($result->isLeft())->toBeTrue();
    });
});