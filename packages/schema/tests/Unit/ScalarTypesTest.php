<?php

declare(strict_types=1);

use EffectPHP\Core\Eff;
use EffectPHP\Schema\Schema;

describe('String Schema', function () {
    test('basic string validation', function () {
        $schema = Schema::string();
        
        $result = Run::syncResult($schema->decode('hello world'));
        expect($result->isSuccess())->toBeTrue();
        expect($result->getValueOrNull())->toBe('hello world');
        
        // Invalid - not a string
        $result = Run::syncResult($schema->decode(123));
        expect($result->isFailure())->toBeTrue();
    });

    test('string with length constraints', function () {
        $schema = Schema::minLength(Schema::maxLength(Schema::string(), 10), 3);
        
        // Valid length
        $result = Run::syncResult($schema->decode('hello'));
        expect($result->isSuccess())->toBeTrue();
        
        // Too short
        $result = Run::syncResult($schema->decode('hi'));
        expect($result->isFailure())->toBeTrue();
        
        // Too long
        $result = Run::syncResult($schema->decode('this is too long'));
        expect($result->isFailure())->toBeTrue();
    });

    test('email validation', function () {
        $schema = Schema::email(Schema::string());
        
        $result = Run::syncResult($schema->decode('test@example.com'));
        expect($result->isSuccess())->toBeTrue();
        
        $result = Run::syncResult($schema->decode('invalid-email'));
        expect($result->isFailure())->toBeTrue();
    });

    test('pattern validation', function () {
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        $schema = Schema::pattern(Schema::string(), $uuidPattern);
        
        $result = Run::syncResult($schema->decode('550e8400-e29b-41d4-a716-446655440000'));
        expect($result->isSuccess())->toBeTrue();
        
        $result = Run::syncResult($schema->decode('invalid-uuid'));
        expect($result->isFailure())->toBeTrue();
    });

    test('non empty string', function () {
        $schema = Schema::nonEmptyString();
        
        $result = Run::syncResult($schema->decode('hello'));
        expect($result->isSuccess())->toBeTrue();
        
        $result = Run::syncResult($schema->decode(''));
        expect($result->isFailure())->toBeTrue();
    });
});

describe('Number Schema', function () {
    test('basic number validation', function () {
        $schema = Schema::number();
        
        // Integer
        $result = Run::syncResult($schema->decode(42));
        expect($result->isSuccess())->toBeTrue();
        expect($result->getValueOrNull())->toBe(42);
        
        // Float
        $result = Run::syncResult($schema->decode(3.14));
        expect($result->isSuccess())->toBeTrue();
        expect($result->getValueOrNull())->toBe(3.14);
        
        // String numbers are coerced to numbers (as floats)
        $result = Run::syncResult($schema->decode('123'));
        expect($result->isRight())->toBeTrue();
        expect($result->getValueOrNull())->toBe(123.0);
        
        // Invalid - non-numeric string
        $result = Run::syncResult($schema->decode('not a number'));
        expect($result->isFailure())->toBeTrue();
    });

    test('number with range constraints', function () {
        $schema = Schema::min(Schema::max(Schema::number(), 100), 0);
        
        // Valid range
        $result = Run::syncResult($schema->decode(50));
        expect($result->isRight())->toBeTrue();
        
        // Below minimum
        $result = Run::syncResult($schema->decode(-5));
        expect($result->isFailure())->toBeTrue();
        
        // Above maximum
        $result = Run::syncResult($schema->decode(150));
        expect($result->isFailure())->toBeTrue();
    });

    test('boundary values', function () {
        $schema = Schema::min(Schema::max(Schema::number(), 10), 0);
        
        // Boundary values should be valid
        $result = Run::syncResult($schema->decode(0));
        expect($result->isRight())->toBeTrue();
        
        $result = Run::syncResult($schema->decode(10));
        expect($result->isRight())->toBeTrue();
    });
});

describe('Boolean Schema', function () {
    test('basic boolean validation', function () {
        $schema = Schema::boolean();
        
        $result = Run::syncResult($schema->decode(true));
        expect($result->isRight())->toBeTrue();
        expect($result->getValueOrNull())->toBe(true);
        
        $result = Run::syncResult($schema->decode(false));
        expect($result->isRight())->toBeTrue();
        expect($result->getValueOrNull())->toBe(false);
        
        // Invalid - string
        $result = Run::syncResult($schema->decode('true'));
        expect($result->isFailure())->toBeTrue();
        
        // Invalid - number
        $result = Run::syncResult($schema->decode(1));
        expect($result->isFailure())->toBeTrue();
    });
});

describe('Literal Schema', function () {
    test('string literal', function () {
        $schema = Schema::literal('active');
        
        $result = Run::syncResult($schema->decode('active'));
        expect($result->isRight())->toBeTrue();
        expect($result->getValueOrNull())->toBe('active');
        
        $result = Run::syncResult($schema->decode('inactive'));
        expect($result->isFailure())->toBeTrue();
    });

    test('number literal', function () {
        $schema = Schema::literal(42);
        
        $result = Run::syncResult($schema->decode(42));
        expect($result->isRight())->toBeTrue();
        
        $result = Run::syncResult($schema->decode(43));
        expect($result->isFailure())->toBeTrue();
    });

    test('null literal', function () {
        $schema = Schema::literal(null);
        
        $result = Run::syncResult($schema->decode(null));
        expect($result->isRight())->toBeTrue();
        expect($result->getValueOrNull())->toBe(null);
        
        $result = Run::syncResult($schema->decode('null'));
        expect($result->isFailure())->toBeTrue();
    });

    test('boolean literal', function () {
        $schema = Schema::literal(true);
        
        $result = Run::syncResult($schema->decode(true));
        expect($result->isRight())->toBeTrue();
        
        $result = Run::syncResult($schema->decode(false));
        expect($result->isFailure())->toBeTrue();
    });
});

describe('Refinement Schema', function () {
    test('custom validation rule', function () {
        $evenNumberSchema = Schema::refine(
            Schema::number(),
            fn($value) => is_numeric($value) && $value % 2 === 0,
            'even number'
        );
        
        $result = Run::syncResult($evenNumberSchema->decode(4));
        expect($result->isRight())->toBeTrue();
        
        $result = Run::syncResult($evenNumberSchema->decode(3));
        expect($result->isFailure())->toBeTrue();
    });

    test('positive number refinement', function () {
        $positiveSchema = Schema::refine(
            Schema::number(),
            fn($value) => is_numeric($value) && $value > 0,
            'positive'
        );
        
        $result = Run::syncResult($positiveSchema->decode(5));
        expect($result->isRight())->toBeTrue();
        
        $result = Run::syncResult($positiveSchema->decode(-1));
        expect($result->isLeft())->toBeTrue();
        
        $result = Run::syncResult($positiveSchema->decode(0));
        expect($result->isLeft())->toBeTrue();
    });
});

describe('Encoding and Decoding', function () {
    test('string encoding', function () {
        $schema = Schema::string();
        
        $result = Run::syncResult($schema->encode('hello'));
        expect($result->isRight())->toBeTrue();
        expect($result->getValueOrNull())->toBe('hello');
    });

    test('number encoding', function () {
        $schema = Schema::number();
        
        $result = Run::syncResult($schema->encode(42));
        expect($result->isRight())->toBeTrue();
        expect($result->getValueOrNull())->toBe(42);
    });

    test('refined schema encoding', function () {
        $emailSchema = Schema::email(Schema::string());
        
        $result = Run::syncResult($emailSchema->encode('test@example.com'));
        expect($result->isRight())->toBeTrue();
        expect($result->getValueOrNull())->toBe('test@example.com');
    });
});