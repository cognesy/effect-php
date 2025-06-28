<?php

declare(strict_types=1);

use EffectPHP\Core\Eff;
use EffectPHP\Schema\Schema;

// Test enums for mixed type scenarios
enum MixedStatus: string {
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}

enum MixedPriority {
    case LOW;
    case HIGH;
}

describe('Any Schema', function () {
    test('accepts any value', function () {
        $schema = Schema::any();
        
        $values = ['string', 42, true, null, ['array'], (object)['key' => 'value']];
        
        foreach ($values as $value) {
            $result = Eff::runSafely($schema->decode($value));
            expect($result->isRight())->toBeTrue();
            expect($result->fold(fn($e) => null, fn($v) => $v))->toBe($value);
        }
    });
});

describe('Mixed Schema', function () {
    test('accepts basic types', function () {
        $schema = Schema::mixed();
        
        // String
        $result = Eff::runSafely($schema->decode('hello'));
        expect($result->isRight())->toBeTrue();
        
        // Number
        $result = Eff::runSafely($schema->decode(42));
        expect($result->isRight())->toBeTrue();
        
        // Boolean
        $result = Eff::runSafely($schema->decode(true));
        expect($result->isRight())->toBeTrue();
    });
});

describe('Union Schema', function () {
    test('string or number union', function () {
        $schema = Schema::union([Schema::string(), Schema::number()]);
        
        // Valid string
        $result = Eff::runSafely($schema->decode('hello'));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe('hello');
        
        // Valid number
        $result = Eff::runSafely($schema->decode(42));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(42);
        
        // Invalid boolean
        $result = Eff::runSafely($schema->decode(true));
        expect($result->isLeft())->toBeTrue();
    });

    test('nullable string', function () {
        $schema = Schema::nullOr(Schema::string());
        
        // Valid string
        $result = Eff::runSafely($schema->decode('hello'));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe('hello');
        
        // Valid null
        $result = Eff::runSafely($schema->decode(null));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(null);
        
        // Invalid number
        $result = Eff::runSafely($schema->decode(42));
        expect($result->isLeft())->toBeTrue();
    });

    test('multiple literal union', function () {
        $schema = Schema::union([
            Schema::literal('yes'),
            Schema::literal('no'),
            Schema::literal('maybe')
        ]);
        
        $result = Eff::runSafely($schema->decode('yes'));
        expect($result->isRight())->toBeTrue();
        
        $result = Eff::runSafely($schema->decode('no'));
        expect($result->isRight())->toBeTrue();
        
        $result = Eff::runSafely($schema->decode('definitely'));
        expect($result->isLeft())->toBeTrue();
    });
});

describe('Object Schema', function () {
    test('simple object', function () {
        $schema = Schema::object([
            'name' => Schema::string(),
            'age' => Schema::number()
        ], ['name', 'age']);
        
        $data = ['name' => 'John', 'age' => 30];
        $result = Eff::runSafely($schema->decode($data));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe($data);
    });

    test('object with optional fields', function () {
        $schema = Schema::object([
            'name' => Schema::string(),
            'email' => Schema::string()
        ], ['name']); // email is optional
        
        // With optional field
        $result = Eff::runSafely($schema->decode(['name' => 'John', 'email' => 'john@example.com']));
        expect($result->isRight())->toBeTrue();
        
        // Without optional field
        $result = Eff::runSafely($schema->decode(['name' => 'John']));
        expect($result->isRight())->toBeTrue();
        
        // Missing required field
        $result = Eff::runSafely($schema->decode(['email' => 'john@example.com']));
        expect($result->isLeft())->toBeTrue();
    });

    test('nested object', function () {
        $addressSchema = Schema::object([
            'street' => Schema::string(),
            'city' => Schema::string()
        ], ['street', 'city']);
        
        $personSchema = Schema::object([
            'name' => Schema::string(),
            'address' => $addressSchema
        ], ['name', 'address']);
        
        $data = [
            'name' => 'John',
            'address' => [
                'street' => '123 Main St',
                'city' => 'Springfield'
            ]
        ];
        
        $result = Eff::runSafely($personSchema->decode($data));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe($data);
    });
});

describe('Record Schema', function () {
    test('string to string record', function () {
        $schema = Schema::record(Schema::string(), Schema::string());
        
        $data = ['en' => 'Hello', 'es' => 'Hola', 'fr' => 'Bonjour'];
        $result = Eff::runSafely($schema->decode($data));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe($data);
    });

    test('string to number record', function () {
        $schema = Schema::record(Schema::string(), Schema::number());
        
        $data = ['apple' => 5, 'banana' => 3, 'orange' => 8];
        $result = Eff::runSafely($schema->decode($data));
        expect($result->isRight())->toBeTrue();
        
        // Invalid value type
        $result = Eff::runSafely($schema->decode(['apple' => 'five']));
        expect($result->isLeft())->toBeTrue();
    });
});

describe('Transformation Schema', function () {
    test('string to number transformation', function () {
        $schema = Schema::transform(
            Schema::string(),
            Schema::number(),
            fn($str) => (float) $str,
            fn($num) => (string) $num
        );
        
        // Decode: string becomes number
        $result = Eff::runSafely($schema->decode('42.5'));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(42.5);
        
        // Encode: number becomes string
        $result = Eff::runSafely($schema->encode(42.5));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe('42.5');
    });

});

describe('Tuple Schema', function () {
    test('fixed tuple', function () {
        $schema = Schema::tuple(Schema::string(), Schema::number(), Schema::boolean());
        
        $result = Eff::runSafely($schema->decode(['hello', 42, true]));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(['hello', 42, true]);
        
        // Wrong length
        $result = Eff::runSafely($schema->decode(['hello', 42]));
        expect($result->isLeft())->toBeTrue();
        
        // Wrong type
        $result = Eff::runSafely($schema->decode(['hello', 'not a number', true]));
        expect($result->isLeft())->toBeTrue();
    });

    test('coordinate tuple', function () {
        $coordinateSchema = Schema::tuple(Schema::number(), Schema::number());
        
        $result = Eff::runSafely($coordinateSchema->decode([10.5, 20.3]));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe([10.5, 20.3]);
    });
});

describe('Complex Mixed Types', function () {
    test('union with object and literal', function () {
        $userSchema = Schema::object(['name' => Schema::string()], ['name']);
        $schema = Schema::union([
            Schema::literal('anonymous'),
            $userSchema
        ]);
        
        // Literal case
        $result = Eff::runSafely($schema->decode('anonymous'));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe('anonymous');
        
        // Object case
        $result = Eff::runSafely($schema->decode(['name' => 'John']));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(['name' => 'John']);
    });

    test('object with union field', function () {
        $schema = Schema::object([
            'id' => Schema::union([Schema::string(), Schema::number()]),
            'active' => Schema::boolean()
        ], ['id', 'active']);
        
        // String ID
        $result = Eff::runSafely($schema->decode(['id' => 'abc123', 'active' => true]));
        expect($result->isRight())->toBeTrue();
        
        // Number ID
        $result = Eff::runSafely($schema->decode(['id' => 123, 'active' => false]));
        expect($result->isRight())->toBeTrue();
        
        // Invalid ID type
        $result = Eff::runSafely($schema->decode(['id' => true, 'active' => true]));
        expect($result->isLeft())->toBeTrue();
    });

    test('nullable nested object', function () {
        $profileSchema = Schema::object([
            'bio' => Schema::string(),
            'website' => Schema::string()
        ], ['bio']);
        
        $userSchema = Schema::object([
            'name' => Schema::string(),
            'profile' => Schema::nullOr($profileSchema)
        ], ['name']);
        
        // With profile
        $result = Eff::runSafely($userSchema->decode([
            'name' => 'John',
            'profile' => ['bio' => 'Software developer', 'website' => 'john.dev']
        ]));
        expect($result->isRight())->toBeTrue();
        
        // Without profile (null)
        $result = Eff::runSafely($userSchema->decode([
            'name' => 'John',
            'profile' => null
        ]));
        expect($result->isRight())->toBeTrue();
        
        // Missing profile field (should default to null handling)
        $result = Eff::runSafely($userSchema->decode(['name' => 'John']));
        expect($result->isRight())->toBeTrue();
    });
});