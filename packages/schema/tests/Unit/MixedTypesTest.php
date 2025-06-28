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
            $result = Run::syncResult($schema->decode($value));
            expect($result->isSuccess())->toBeTrue();
            expect($result->fold(fn($e) => null, fn($v) => $v))->toBe($value);
        }
    });
});

describe('Mixed Schema', function () {
    test('accepts basic types', function () {
        $schema = Schema::mixed();
        
        // String
        $result = Run::syncResult($schema->decode('hello'));
        expect($result->isSuccess())->toBeTrue();
        
        // Number
        $result = Run::syncResult($schema->decode(42));
        expect($result->isSuccess())->toBeTrue();
        
        // Boolean
        $result = Run::syncResult($schema->decode(true));
        expect($result->isSuccess())->toBeTrue();
    });
});

describe('Union Schema', function () {
    test('string or number union', function () {
        $schema = Schema::union([Schema::string(), Schema::number()]);
        
        // Valid string
        $result = Run::syncResult($schema->decode('hello'));
        expect($result->isSuccess())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe('hello');
        
        // Valid number
        $result = Run::syncResult($schema->decode(42));
        expect($result->isSuccess())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(42);
        
        // Invalid boolean
        $result = Run::syncResult($schema->decode(true));
        expect($result->isFailure())->toBeTrue();
    });

    test('nullable string', function () {
        $schema = Schema::nullOr(Schema::string());
        
        // Valid string
        $result = Run::syncResult($schema->decode('hello'));
        expect($result->isSuccess())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe('hello');
        
        // Valid null
        $result = Run::syncResult($schema->decode(null));
        expect($result->isSuccess())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(null);
        
        // Invalid number
        $result = Run::syncResult($schema->decode(42));
        expect($result->isFailure())->toBeTrue();
    });

    test('multiple literal union', function () {
        $schema = Schema::union([
            Schema::literal('yes'),
            Schema::literal('no'),
            Schema::literal('maybe')
        ]);
        
        $result = Run::syncResult($schema->decode('yes'));
        expect($result->isSuccess())->toBeTrue();
        
        $result = Run::syncResult($schema->decode('no'));
        expect($result->isSuccess())->toBeTrue();
        
        $result = Run::syncResult($schema->decode('definitely'));
        expect($result->isFailure())->toBeTrue();
    });
});

describe('Object Schema', function () {
    test('simple object', function () {
        $schema = Schema::object([
            'name' => Schema::string(),
            'age' => Schema::number()
        ], ['name', 'age']);
        
        $data = ['name' => 'John', 'age' => 30];
        $result = Run::syncResult($schema->decode($data));
        expect($result->isSuccess())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe($data);
    });

    test('object with optional fields', function () {
        $schema = Schema::object([
            'name' => Schema::string(),
            'email' => Schema::string()
        ], ['name']); // email is optional
        
        // With optional field
        $result = Run::syncResult($schema->decode(['name' => 'John', 'email' => 'john@example.com']));
        expect($result->isRight())->toBeTrue();
        
        // Without optional field
        $result = Run::syncResult($schema->decode(['name' => 'John']));
        expect($result->isRight())->toBeTrue();
        
        // Missing required field
        $result = Run::syncResult($schema->decode(['email' => 'john@example.com']));
        expect($result->isFailure())->toBeTrue();
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
        
        $result = Run::syncResult($personSchema->decode($data));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe($data);
    });
});

describe('Record Schema', function () {
    test('string to string record', function () {
        $schema = Schema::record(Schema::string(), Schema::string());
        
        $data = ['en' => 'Hello', 'es' => 'Hola', 'fr' => 'Bonjour'];
        $result = Run::syncResult($schema->decode($data));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe($data);
    });

    test('string to number record', function () {
        $schema = Schema::record(Schema::string(), Schema::number());
        
        $data = ['apple' => 5, 'banana' => 3, 'orange' => 8];
        $result = Run::syncResult($schema->decode($data));
        expect($result->isRight())->toBeTrue();
        
        // Invalid value type
        $result = Run::syncResult($schema->decode(['apple' => 'five']));
        expect($result->isFailure())->toBeTrue();
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
        $result = Run::syncResult($schema->decode('42.5'));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(42.5);
        
        // Encode: number becomes string
        $result = Run::syncResult($schema->encode(42.5));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe('42.5');
    });

});

describe('Tuple Schema', function () {
    test('fixed tuple', function () {
        $schema = Schema::tuple(Schema::string(), Schema::number(), Schema::boolean());
        
        $result = Run::syncResult($schema->decode(['hello', 42, true]));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(['hello', 42, true]);
        
        // Wrong length
        $result = Run::syncResult($schema->decode(['hello', 42]));
        expect($result->isFailure())->toBeTrue();
        
        // Wrong type
        $result = Run::syncResult($schema->decode(['hello', 'not a number', true]));
        expect($result->isFailure())->toBeTrue();
    });

    test('coordinate tuple', function () {
        $coordinateSchema = Schema::tuple(Schema::number(), Schema::number());
        
        $result = Run::syncResult($coordinateSchema->decode([10.5, 20.3]));
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
        $result = Run::syncResult($schema->decode('anonymous'));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe('anonymous');
        
        // Object case
        $result = Run::syncResult($schema->decode(['name' => 'John']));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(['name' => 'John']);
    });

    test('object with union field', function () {
        $schema = Schema::object([
            'id' => Schema::union([Schema::string(), Schema::number()]),
            'active' => Schema::boolean()
        ], ['id', 'active']);
        
        // String ID
        $result = Run::syncResult($schema->decode(['id' => 'abc123', 'active' => true]));
        expect($result->isRight())->toBeTrue();
        
        // Number ID
        $result = Run::syncResult($schema->decode(['id' => 123, 'active' => false]));
        expect($result->isRight())->toBeTrue();
        
        // Invalid ID type
        $result = Run::syncResult($schema->decode(['id' => true, 'active' => true]));
        expect($result->isFailure())->toBeTrue();
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
        $result = Run::syncResult($userSchema->decode([
            'name' => 'John',
            'profile' => ['bio' => 'Software developer', 'website' => 'john.dev']
        ]));
        expect($result->isRight())->toBeTrue();
        
        // Without profile (null)
        $result = Run::syncResult($userSchema->decode([
            'name' => 'John',
            'profile' => null
        ]));
        expect($result->isRight())->toBeTrue();
        
        // Missing profile field (should default to null handling)
        $result = Run::syncResult($userSchema->decode(['name' => 'John']));
        expect($result->isRight())->toBeTrue();
    });
});