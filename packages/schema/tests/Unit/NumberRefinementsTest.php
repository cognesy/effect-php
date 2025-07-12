<?php declare(strict_types=1);

use EffectPHP\Core\Run;
use EffectPHP\Schema\Schema;

describe('Integer Schema', function () {
    test('validates integers', function () {
        $schema = Schema::integer();
        
        // Valid integers
        $result = Run::syncResult($schema->decode(42));
        expect($result->isSuccess())->toBeTrue();
        expect($result->getValueOrNull())->toBe(42);
        
        $result = Run::syncResult($schema->decode(-10));
        expect($result->isSuccess())->toBeTrue();
        expect($result->getValueOrNull())->toBe(-10);
        
        $result = Run::syncResult($schema->decode(0));
        expect($result->isSuccess())->toBeTrue();
        expect($result->getValueOrNull())->toBe(0);
    });

    test('accepts integer-like floats', function () {
        $schema = Schema::integer();
        
        // Float that represents an integer
        $result = Run::syncResult($schema->decode(42.0));
        expect($result->isSuccess())->toBeTrue();
        expect($result->getValueOrNull())->toBe(42.0);
        
        $result = Run::syncResult($schema->decode(-10.0));
        expect($result->isSuccess())->toBeTrue();
    });

    test('rejects non-integer floats', function () {
        $schema = Schema::integer();
        
        $result = Run::syncResult($schema->decode(42.5));
        expect($result->isFailure())->toBeTrue();
        
        $result = Run::syncResult($schema->decode(3.14159));
        expect($result->isFailure())->toBeTrue();
    });

    test('accepts string integers', function () {
        $schema = Schema::integer();
        
        // String integers are coerced and validated
        $result = Run::syncResult($schema->decode('42'));
        expect($result->isSuccess())->toBeTrue();
        expect($result->getValueOrNull())->toBe(42.0);
        
        // String floats that are integers
        $result = Run::syncResult($schema->decode('42.0'));
        expect($result->isSuccess())->toBeTrue();
        
        // String floats that are not integers
        $result = Run::syncResult($schema->decode('42.5'));
        expect($result->isFailure())->toBeTrue();
    });
});

describe('Float Schema', function () {
    test('validates floats', function () {
        $schema = Schema::float();
        
        // Valid floats
        $result = Run::syncResult($schema->decode(3.14159));
        expect($result->isSuccess())->toBeTrue();
        expect($result->getValueOrNull())->toBe(3.14159);
        
        $result = Run::syncResult($schema->decode(-2.5));
        expect($result->isSuccess())->toBeTrue();
    });

    test('accepts integers as floats', function () {
        $schema = Schema::float();
        
        // Integers become floats
        $result = Run::syncResult($schema->decode(42));
        expect($result->isSuccess())->toBeTrue();
        expect($result->getValueOrNull())->toBe(42.0);
    });

    test('accepts string floats', function () {
        $schema = Schema::float();
        
        $result = Run::syncResult($schema->decode('3.14'));
        expect($result->isSuccess())->toBeTrue();
        expect($result->getValueOrNull())->toBe(3.14);
    });
});

describe('Positive Integer Schema', function () {
    test('validates positive integers', function () {
        $schema = Schema::positiveInteger();
        
        // Valid positive integers
        $result = Run::syncResult($schema->decode(1));
        expect($result->isSuccess())->toBeTrue();
        
        $result = Run::syncResult($schema->decode(42));
        expect($result->isSuccess())->toBeTrue();
        
        $result = Run::syncResult($schema->decode(100));
        expect($result->isSuccess())->toBeTrue();
    });

    test('rejects zero and negative integers', function () {
        $schema = Schema::positiveInteger();
        
        // Zero should be rejected
        $result = Run::syncResult($schema->decode(0));
        expect($result->isFailure())->toBeTrue();
        
        // Negative should be rejected
        $result = Run::syncResult($schema->decode(-1));
        expect($result->isFailure())->toBeTrue();
        
        $result = Run::syncResult($schema->decode(-42));
        expect($result->isFailure())->toBeTrue();
    });

    test('rejects positive floats', function () {
        $schema = Schema::positiveInteger();
        
        $result = Run::syncResult($schema->decode(1.5));
        expect($result->isFailure())->toBeTrue();
        
        $result = Run::syncResult($schema->decode(42.1));
        expect($result->isFailure())->toBeTrue();
    });
});

describe('Non-Negative Integer Schema', function () {
    test('validates non-negative integers', function () {
        $schema = Schema::nonNegativeInteger();
        
        // Zero is valid
        $result = Run::syncResult($schema->decode(0));
        expect($result->isSuccess())->toBeTrue();
        
        // Positive integers are valid
        $result = Run::syncResult($schema->decode(42));
        expect($result->isSuccess())->toBeTrue();
    });

    test('rejects negative integers', function () {
        $schema = Schema::nonNegativeInteger();
        
        $result = Run::syncResult($schema->decode(-1));
        expect($result->isFailure())->toBeTrue();
        
        $result = Run::syncResult($schema->decode(-42));
        expect($result->isFailure())->toBeTrue();
    });
});

describe('Positive Number Schema', function () {
    test('validates positive numbers', function () {
        $schema = Schema::positiveNumber();
        
        // Positive integers
        $result = Run::syncResult($schema->decode(1));
        expect($result->isSuccess())->toBeTrue();
        
        $result = Run::syncResult($schema->decode(42));
        expect($result->isSuccess())->toBeTrue();
        
        // Positive floats
        $result = Run::syncResult($schema->decode(1.5));
        expect($result->isSuccess())->toBeTrue();
        
        $result = Run::syncResult($schema->decode(3.14159));
        expect($result->isSuccess())->toBeTrue();
    });

    test('rejects zero and negative numbers', function () {
        $schema = Schema::positiveNumber();
        
        // Zero should be rejected
        $result = Run::syncResult($schema->decode(0));
        expect($result->isFailure())->toBeTrue();
        
        // Negative numbers should be rejected
        $result = Run::syncResult($schema->decode(-1));
        expect($result->isFailure())->toBeTrue();
        
        $result = Run::syncResult($schema->decode(-3.14));
        expect($result->isFailure())->toBeTrue();
    });
});

describe('Non-Negative Number Schema', function () {
    test('validates non-negative numbers', function () {
        $schema = Schema::nonNegativeNumber();
        
        // Zero is valid
        $result = Run::syncResult($schema->decode(0));
        expect($result->isSuccess())->toBeTrue();
        
        // Positive numbers are valid
        $result = Run::syncResult($schema->decode(42));
        expect($result->isSuccess())->toBeTrue();
        
        $result = Run::syncResult($schema->decode(3.14));
        expect($result->isSuccess())->toBeTrue();
    });

    test('rejects negative numbers', function () {
        $schema = Schema::nonNegativeNumber();
        
        $result = Run::syncResult($schema->decode(-1));
        expect($result->isFailure())->toBeTrue();
        
        $result = Run::syncResult($schema->decode(-3.14));
        expect($result->isFailure())->toBeTrue();
    });
});

describe('Percentage Schema', function () {
    test('validates percentages', function () {
        $schema = Schema::percentage();
        
        // Valid percentages
        $values = [0, 25, 50, 75, 100, 0.5, 99.99];
        
        foreach ($values as $value) {
            $result = Run::syncResult($schema->decode($value));
            expect($result->isSuccess())->toBeTrue();
        }
    });

    test('rejects values outside 0-100 range', function () {
        $schema = Schema::percentage();
        
        // Below range
        $result = Run::syncResult($schema->decode(-1));
        expect($result->isFailure())->toBeTrue();
        
        $result = Run::syncResult($schema->decode(-0.1));
        expect($result->isFailure())->toBeTrue();
        
        // Above range
        $result = Run::syncResult($schema->decode(101));
        expect($result->isFailure())->toBeTrue();
        
        $result = Run::syncResult($schema->decode(100.1));
        expect($result->isFailure())->toBeTrue();
    });
});

describe('Finite Number Schema', function () {
    test('validates finite numbers', function () {
        $schema = Schema::finite();
        
        // Valid finite numbers
        $result = Run::syncResult($schema->decode(42));
        expect($result->isSuccess())->toBeTrue();
        
        $result = Run::syncResult($schema->decode(3.14159));
        expect($result->isSuccess())->toBeTrue();
        
        $result = Run::syncResult($schema->decode(-1000.5));
        expect($result->isSuccess())->toBeTrue();
        
        $result = Run::syncResult($schema->decode(0));
        expect($result->isSuccess())->toBeTrue();
    });

    test('rejects infinite and NaN values', function () {
        $schema = Schema::finite();
        
        // Use PHP constants for infinity and NaN
        $result = Run::syncResult($schema->decode(INF)); // Infinity
        expect($result->isFailure())->toBeTrue();
        
        $result = Run::syncResult($schema->decode(-INF)); // -Infinity  
        expect($result->isFailure())->toBeTrue();
        
        $result = Run::syncResult($schema->decode(NAN)); // NaN
        expect($result->isFailure())->toBeTrue();
    });
});

describe('Collection with Number Refinements', function () {
    test('integer collection', function () {
        $schema = Schema::collection(Schema::integer())->min(1);
        
        $result = Run::syncResult($schema->decode([1, 2, 3, 42]));
        expect($result->isSuccess())->toBeTrue();
        
        // Contains non-integer
        $result = Run::syncResult($schema->decode([1, 2.5, 3]));
        expect($result->isFailure())->toBeTrue();
    });

    test('positive integer collection', function () {
        $schema = Schema::collection(Schema::positiveInteger())->nonEmpty();
        
        $result = Run::syncResult($schema->decode([1, 2, 100]));
        expect($result->isSuccess())->toBeTrue();
        
        // Contains zero
        $result = Run::syncResult($schema->decode([1, 0, 3]));
        expect($result->isFailure())->toBeTrue();
        
        // Contains negative
        $result = Run::syncResult($schema->decode([1, -1, 3]));
        expect($result->isFailure())->toBeTrue();
    });

    test('percentage collection', function () {
        $schema = Schema::collection(Schema::percentage())->max(10);
        
        $result = Run::syncResult($schema->decode([0, 25.5, 50, 100]));
        expect($result->isSuccess())->toBeTrue();
        
        // Contains value outside range
        $result = Run::syncResult($schema->decode([50, 101]));
        expect($result->isFailure())->toBeTrue();
    });
});

describe('Encoding with Number Refinements', function () {
    test('integer encoding', function () {
        $schema = Schema::integer();
        
        $result = Run::syncResult($schema->encode(42));
        expect($result->isSuccess())->toBeTrue();
        expect($result->getValueOrNull())->toBe(42);
    });

    test('percentage encoding', function () {
        $schema = Schema::percentage();
        
        $result = Run::syncResult($schema->encode(75.5));
        expect($result->isSuccess())->toBeTrue();
        expect($result->getValueOrNull())->toBe(75.5);
        
        // Encoding also validates
        $result = Run::syncResult($schema->encode(150));
        expect($result->isFailure())->toBeTrue();
    });
});