<?php

declare(strict_types=1);

use EffectPHP\Core\Eff;
use EffectPHP\Schema\Schema;

describe('Integer Schema', function () {
    test('validates integers', function () {
        $schema = Schema::integer();
        
        // Valid integers
        $result = Eff::runSafely($schema->decode(42));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(42);
        
        $result = Eff::runSafely($schema->decode(-10));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(-10);
        
        $result = Eff::runSafely($schema->decode(0));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(0);
    });

    test('accepts integer-like floats', function () {
        $schema = Schema::integer();
        
        // Float that represents an integer
        $result = Eff::runSafely($schema->decode(42.0));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(42.0);
        
        $result = Eff::runSafely($schema->decode(-10.0));
        expect($result->isRight())->toBeTrue();
    });

    test('rejects non-integer floats', function () {
        $schema = Schema::integer();
        
        $result = Eff::runSafely($schema->decode(42.5));
        expect($result->isLeft())->toBeTrue();
        
        $result = Eff::runSafely($schema->decode(3.14159));
        expect($result->isLeft())->toBeTrue();
    });

    test('accepts string integers', function () {
        $schema = Schema::integer();
        
        // String integers are coerced and validated
        $result = Eff::runSafely($schema->decode('42'));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(42.0);
        
        // String floats that are integers
        $result = Eff::runSafely($schema->decode('42.0'));
        expect($result->isRight())->toBeTrue();
        
        // String floats that are not integers
        $result = Eff::runSafely($schema->decode('42.5'));
        expect($result->isLeft())->toBeTrue();
    });
});

describe('Float Schema', function () {
    test('validates floats', function () {
        $schema = Schema::float();
        
        // Valid floats
        $result = Eff::runSafely($schema->decode(3.14159));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(3.14159);
        
        $result = Eff::runSafely($schema->decode(-2.5));
        expect($result->isRight())->toBeTrue();
    });

    test('accepts integers as floats', function () {
        $schema = Schema::float();
        
        // Integers become floats
        $result = Eff::runSafely($schema->decode(42));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(42.0);
    });

    test('accepts string floats', function () {
        $schema = Schema::float();
        
        $result = Eff::runSafely($schema->decode('3.14'));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(3.14);
    });
});

describe('Positive Integer Schema', function () {
    test('validates positive integers', function () {
        $schema = Schema::positiveInteger();
        
        // Valid positive integers
        $result = Eff::runSafely($schema->decode(1));
        expect($result->isRight())->toBeTrue();
        
        $result = Eff::runSafely($schema->decode(42));
        expect($result->isRight())->toBeTrue();
        
        $result = Eff::runSafely($schema->decode(100));
        expect($result->isRight())->toBeTrue();
    });

    test('rejects zero and negative integers', function () {
        $schema = Schema::positiveInteger();
        
        // Zero should be rejected
        $result = Eff::runSafely($schema->decode(0));
        expect($result->isLeft())->toBeTrue();
        
        // Negative should be rejected
        $result = Eff::runSafely($schema->decode(-1));
        expect($result->isLeft())->toBeTrue();
        
        $result = Eff::runSafely($schema->decode(-42));
        expect($result->isLeft())->toBeTrue();
    });

    test('rejects positive floats', function () {
        $schema = Schema::positiveInteger();
        
        $result = Eff::runSafely($schema->decode(1.5));
        expect($result->isLeft())->toBeTrue();
        
        $result = Eff::runSafely($schema->decode(42.1));
        expect($result->isLeft())->toBeTrue();
    });
});

describe('Non-Negative Integer Schema', function () {
    test('validates non-negative integers', function () {
        $schema = Schema::nonNegativeInteger();
        
        // Zero is valid
        $result = Eff::runSafely($schema->decode(0));
        expect($result->isRight())->toBeTrue();
        
        // Positive integers are valid
        $result = Eff::runSafely($schema->decode(42));
        expect($result->isRight())->toBeTrue();
    });

    test('rejects negative integers', function () {
        $schema = Schema::nonNegativeInteger();
        
        $result = Eff::runSafely($schema->decode(-1));
        expect($result->isLeft())->toBeTrue();
        
        $result = Eff::runSafely($schema->decode(-42));
        expect($result->isLeft())->toBeTrue();
    });
});

describe('Positive Number Schema', function () {
    test('validates positive numbers', function () {
        $schema = Schema::positiveNumber();
        
        // Positive integers
        $result = Eff::runSafely($schema->decode(1));
        expect($result->isRight())->toBeTrue();
        
        $result = Eff::runSafely($schema->decode(42));
        expect($result->isRight())->toBeTrue();
        
        // Positive floats
        $result = Eff::runSafely($schema->decode(1.5));
        expect($result->isRight())->toBeTrue();
        
        $result = Eff::runSafely($schema->decode(3.14159));
        expect($result->isRight())->toBeTrue();
    });

    test('rejects zero and negative numbers', function () {
        $schema = Schema::positiveNumber();
        
        // Zero should be rejected
        $result = Eff::runSafely($schema->decode(0));
        expect($result->isLeft())->toBeTrue();
        
        // Negative numbers should be rejected
        $result = Eff::runSafely($schema->decode(-1));
        expect($result->isLeft())->toBeTrue();
        
        $result = Eff::runSafely($schema->decode(-3.14));
        expect($result->isLeft())->toBeTrue();
    });
});

describe('Non-Negative Number Schema', function () {
    test('validates non-negative numbers', function () {
        $schema = Schema::nonNegativeNumber();
        
        // Zero is valid
        $result = Eff::runSafely($schema->decode(0));
        expect($result->isRight())->toBeTrue();
        
        // Positive numbers are valid
        $result = Eff::runSafely($schema->decode(42));
        expect($result->isRight())->toBeTrue();
        
        $result = Eff::runSafely($schema->decode(3.14));
        expect($result->isRight())->toBeTrue();
    });

    test('rejects negative numbers', function () {
        $schema = Schema::nonNegativeNumber();
        
        $result = Eff::runSafely($schema->decode(-1));
        expect($result->isLeft())->toBeTrue();
        
        $result = Eff::runSafely($schema->decode(-3.14));
        expect($result->isLeft())->toBeTrue();
    });
});

describe('Percentage Schema', function () {
    test('validates percentages', function () {
        $schema = Schema::percentage();
        
        // Valid percentages
        $values = [0, 25, 50, 75, 100, 0.5, 99.99];
        
        foreach ($values as $value) {
            $result = Eff::runSafely($schema->decode($value));
            expect($result->isRight())->toBeTrue();
        }
    });

    test('rejects values outside 0-100 range', function () {
        $schema = Schema::percentage();
        
        // Below range
        $result = Eff::runSafely($schema->decode(-1));
        expect($result->isLeft())->toBeTrue();
        
        $result = Eff::runSafely($schema->decode(-0.1));
        expect($result->isLeft())->toBeTrue();
        
        // Above range
        $result = Eff::runSafely($schema->decode(101));
        expect($result->isLeft())->toBeTrue();
        
        $result = Eff::runSafely($schema->decode(100.1));
        expect($result->isLeft())->toBeTrue();
    });
});

describe('Finite Number Schema', function () {
    test('validates finite numbers', function () {
        $schema = Schema::finite();
        
        // Valid finite numbers
        $result = Eff::runSafely($schema->decode(42));
        expect($result->isRight())->toBeTrue();
        
        $result = Eff::runSafely($schema->decode(3.14159));
        expect($result->isRight())->toBeTrue();
        
        $result = Eff::runSafely($schema->decode(-1000.5));
        expect($result->isRight())->toBeTrue();
        
        $result = Eff::runSafely($schema->decode(0));
        expect($result->isRight())->toBeTrue();
    });

    test('rejects infinite and NaN values', function () {
        $schema = Schema::finite();
        
        // Use PHP constants for infinity and NaN
        $result = Eff::runSafely($schema->decode(INF)); // Infinity
        expect($result->isLeft())->toBeTrue();
        
        $result = Eff::runSafely($schema->decode(-INF)); // -Infinity  
        expect($result->isLeft())->toBeTrue();
        
        $result = Eff::runSafely($schema->decode(NAN)); // NaN
        expect($result->isLeft())->toBeTrue();
    });
});

describe('Collection with Number Refinements', function () {
    test('integer collection', function () {
        $schema = Schema::collection(Schema::integer())->min(1);
        
        $result = Eff::runSafely($schema->decode([1, 2, 3, 42]));
        expect($result->isRight())->toBeTrue();
        
        // Contains non-integer
        $result = Eff::runSafely($schema->decode([1, 2.5, 3]));
        expect($result->isLeft())->toBeTrue();
    });

    test('positive integer collection', function () {
        $schema = Schema::collection(Schema::positiveInteger())->nonEmpty();
        
        $result = Eff::runSafely($schema->decode([1, 2, 100]));
        expect($result->isRight())->toBeTrue();
        
        // Contains zero
        $result = Eff::runSafely($schema->decode([1, 0, 3]));
        expect($result->isLeft())->toBeTrue();
        
        // Contains negative
        $result = Eff::runSafely($schema->decode([1, -1, 3]));
        expect($result->isLeft())->toBeTrue();
    });

    test('percentage collection', function () {
        $schema = Schema::collection(Schema::percentage())->max(10);
        
        $result = Eff::runSafely($schema->decode([0, 25.5, 50, 100]));
        expect($result->isRight())->toBeTrue();
        
        // Contains value outside range
        $result = Eff::runSafely($schema->decode([50, 101]));
        expect($result->isLeft())->toBeTrue();
    });
});

describe('Encoding with Number Refinements', function () {
    test('integer encoding', function () {
        $schema = Schema::integer();
        
        $result = Eff::runSafely($schema->encode(42));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(42);
    });

    test('percentage encoding', function () {
        $schema = Schema::percentage();
        
        $result = Eff::runSafely($schema->encode(75.5));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(75.5);
        
        // Encoding also validates
        $result = Eff::runSafely($schema->encode(150));
        expect($result->isLeft())->toBeTrue();
    });
});