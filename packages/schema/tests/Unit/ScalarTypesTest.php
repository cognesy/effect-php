<?php

declare(strict_types=1);

use EffectPHP\Core\Eff;
use EffectPHP\Schema\Schema;

describe('String Schema', function () {
    test('basic string validation', function () {
        $schema = Schema::string();
        
        $result = Eff::runSafely($schema->decode('hello world'));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe('hello world');
        
        // Invalid - not a string
        $result = Eff::runSafely($schema->decode(123));
        expect($result->isLeft())->toBeTrue();
    });

    test('string with length constraints', function () {
        $schema = Schema::minLength(Schema::maxLength(Schema::string(), 10), 3);
        
        // Valid length
        $result = Eff::runSafely($schema->decode('hello'));
        expect($result->isRight())->toBeTrue();
        
        // Too short
        $result = Eff::runSafely($schema->decode('hi'));
        expect($result->isLeft())->toBeTrue();
        
        // Too long
        $result = Eff::runSafely($schema->decode('this is too long'));
        expect($result->isLeft())->toBeTrue();
    });

    test('email validation', function () {
        $schema = Schema::email(Schema::string());
        
        $result = Eff::runSafely($schema->decode('test@example.com'));
        expect($result->isRight())->toBeTrue();
        
        $result = Eff::runSafely($schema->decode('invalid-email'));
        expect($result->isLeft())->toBeTrue();
    });

    test('pattern validation', function () {
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        $schema = Schema::pattern(Schema::string(), $uuidPattern);
        
        $result = Eff::runSafely($schema->decode('550e8400-e29b-41d4-a716-446655440000'));
        expect($result->isRight())->toBeTrue();
        
        $result = Eff::runSafely($schema->decode('invalid-uuid'));
        expect($result->isLeft())->toBeTrue();
    });

    test('non empty string', function () {
        $schema = Schema::nonEmptyString();
        
        $result = Eff::runSafely($schema->decode('hello'));
        expect($result->isRight())->toBeTrue();
        
        $result = Eff::runSafely($schema->decode(''));
        expect($result->isLeft())->toBeTrue();
    });
});

describe('Number Schema', function () {
    test('basic number validation', function () {
        $schema = Schema::number();
        
        // Integer
        $result = Eff::runSafely($schema->decode(42));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(42);
        
        // Float
        $result = Eff::runSafely($schema->decode(3.14));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(3.14);
        
        // String numbers are coerced to numbers (as floats)
        $result = Eff::runSafely($schema->decode('123'));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(123.0);
        
        // Invalid - non-numeric string
        $result = Eff::runSafely($schema->decode('not a number'));
        expect($result->isLeft())->toBeTrue();
    });

    test('number with range constraints', function () {
        $schema = Schema::min(Schema::max(Schema::number(), 100), 0);
        
        // Valid range
        $result = Eff::runSafely($schema->decode(50));
        expect($result->isRight())->toBeTrue();
        
        // Below minimum
        $result = Eff::runSafely($schema->decode(-5));
        expect($result->isLeft())->toBeTrue();
        
        // Above maximum
        $result = Eff::runSafely($schema->decode(150));
        expect($result->isLeft())->toBeTrue();
    });

    test('boundary values', function () {
        $schema = Schema::min(Schema::max(Schema::number(), 10), 0);
        
        // Boundary values should be valid
        $result = Eff::runSafely($schema->decode(0));
        expect($result->isRight())->toBeTrue();
        
        $result = Eff::runSafely($schema->decode(10));
        expect($result->isRight())->toBeTrue();
    });
});

describe('Boolean Schema', function () {
    test('basic boolean validation', function () {
        $schema = Schema::boolean();
        
        $result = Eff::runSafely($schema->decode(true));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(true);
        
        $result = Eff::runSafely($schema->decode(false));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(false);
        
        // Invalid - string
        $result = Eff::runSafely($schema->decode('true'));
        expect($result->isLeft())->toBeTrue();
        
        // Invalid - number
        $result = Eff::runSafely($schema->decode(1));
        expect($result->isLeft())->toBeTrue();
    });
});

describe('Literal Schema', function () {
    test('string literal', function () {
        $schema = Schema::literal('active');
        
        $result = Eff::runSafely($schema->decode('active'));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe('active');
        
        $result = Eff::runSafely($schema->decode('inactive'));
        expect($result->isLeft())->toBeTrue();
    });

    test('number literal', function () {
        $schema = Schema::literal(42);
        
        $result = Eff::runSafely($schema->decode(42));
        expect($result->isRight())->toBeTrue();
        
        $result = Eff::runSafely($schema->decode(43));
        expect($result->isLeft())->toBeTrue();
    });

    test('null literal', function () {
        $schema = Schema::literal(null);
        
        $result = Eff::runSafely($schema->decode(null));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(null);
        
        $result = Eff::runSafely($schema->decode('null'));
        expect($result->isLeft())->toBeTrue();
    });

    test('boolean literal', function () {
        $schema = Schema::literal(true);
        
        $result = Eff::runSafely($schema->decode(true));
        expect($result->isRight())->toBeTrue();
        
        $result = Eff::runSafely($schema->decode(false));
        expect($result->isLeft())->toBeTrue();
    });
});

describe('Refinement Schema', function () {
    test('custom validation rule', function () {
        $evenNumberSchema = Schema::refine(
            Schema::number(),
            fn($value) => is_numeric($value) && $value % 2 === 0,
            'even number'
        );
        
        $result = Eff::runSafely($evenNumberSchema->decode(4));
        expect($result->isRight())->toBeTrue();
        
        $result = Eff::runSafely($evenNumberSchema->decode(3));
        expect($result->isLeft())->toBeTrue();
    });

    test('positive number refinement', function () {
        $positiveSchema = Schema::refine(
            Schema::number(),
            fn($value) => is_numeric($value) && $value > 0,
            'positive'
        );
        
        $result = Eff::runSafely($positiveSchema->decode(5));
        expect($result->isRight())->toBeTrue();
        
        $result = Eff::runSafely($positiveSchema->decode(-1));
        expect($result->isLeft())->toBeTrue();
        
        $result = Eff::runSafely($positiveSchema->decode(0));
        expect($result->isLeft())->toBeTrue();
    });
});

describe('Encoding and Decoding', function () {
    test('string encoding', function () {
        $schema = Schema::string();
        
        $result = Eff::runSafely($schema->encode('hello'));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe('hello');
    });

    test('number encoding', function () {
        $schema = Schema::number();
        
        $result = Eff::runSafely($schema->encode(42));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(42);
    });

    test('refined schema encoding', function () {
        $emailSchema = Schema::email(Schema::string());
        
        $result = Eff::runSafely($emailSchema->encode('test@example.com'));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe('test@example.com');
    });
});