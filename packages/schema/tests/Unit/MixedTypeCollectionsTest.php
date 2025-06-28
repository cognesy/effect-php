<?php

declare(strict_types=1);

use EffectPHP\Core\Eff;
use EffectPHP\Schema\Schema;

// Test enums for mixed scenarios
enum Color: string {
    case RED = 'red';
    case GREEN = 'green';
    case BLUE = 'blue';
}

enum Size {
    case SMALL;
    case MEDIUM;
    case LARGE;
}

describe('Any and Mixed Collections', function () {
    test('any type collection', function () {
        $schema = Schema::collection(Schema::any());
        
        $mixedData = ['string', 42, true, null, ['nested', 'array']];
        $result = Eff::runSafely($schema->decode($mixedData));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe($mixedData);
    });

    test('mixed type collection', function () {
        $schema = Schema::collection(Schema::mixed())->max(10);
        
        $mixedData = ['text', 123, false, 3.14];
        $result = Eff::runSafely($schema->decode($mixedData));
        expect($result->isRight())->toBeTrue();
    });

    test('any collection with constraints', function () {
        $schema = Schema::collection(Schema::any())
            ->nonEmpty()
            ->between(2, 8);
        
        // Valid
        $result = Eff::runSafely($schema->decode([1, 'two', true]));
        expect($result->isRight())->toBeTrue();
        
        // Invalid - empty
        $result = Eff::runSafely($schema->decode([]));
        expect($result->isLeft())->toBeTrue();
        
        // Invalid - too few
        $result = Eff::runSafely($schema->decode([1]));
        expect($result->isLeft())->toBeTrue();
    });
});

describe('Union Collections', function () {
    test('string or number collection', function () {
        $schema = Schema::collection(
            Schema::union([Schema::string(), Schema::number()])
        )->min(1);
        
        $result = Eff::runSafely($schema->decode(['hello', 42, 'world', 3.14]));
        expect($result->isRight())->toBeTrue();
        
        // Invalid - contains boolean
        $result = Eff::runSafely($schema->decode(['hello', true]));
        expect($result->isLeft())->toBeTrue();
    });

    test('nullable value collection', function () {
        $schema = Schema::collection(
            Schema::nullOr(Schema::string())
        )->max(5);
        
        $result = Eff::runSafely($schema->decode(['hello', null, 'world', null]));
        expect($result->isRight())->toBeTrue();
        
        // Invalid - wrong type
        $result = Eff::runSafely($schema->decode(['hello', 42]));
        expect($result->isLeft())->toBeTrue();
    });

    test('enum or string collection', function () {
        // This tests a specific union case that could be order-sensitive
        $schema = Schema::collection(
            Schema::union([
                Schema::literal('custom'),
                Schema::enum(Color::class)
            ])
        );
        
        // Test enum values are decoded properly
        $result = Eff::runSafely($schema->decode(['red', 'custom', 'blue']));
        expect($result->isRight())->toBeTrue();
        
        $decoded = $result->fold(fn($e) => null, fn($v) => $v);
        expect($decoded[0])->toBe(Color::RED);
        expect($decoded[1])->toBe('custom');
        expect($decoded[2])->toBe(Color::BLUE);
    });

    test('complex union collection', function () {
        $schema = Schema::collection(
            Schema::union([
                Schema::number(),
                Schema::boolean(),
                Schema::literal('special')
            ])
        )->length(4);
        
        $result = Eff::runSafely($schema->decode([42, true, 'special', false]));
        expect($result->isRight())->toBeTrue();
        
        $decoded = $result->fold(fn($e) => null, fn($v) => $v);
        expect($decoded)->toBe([42, true, 'special', false]);
    });
});

describe('Object Collections', function () {
    test('simple object collection', function () {
        $itemSchema = Schema::object([
            'id' => Schema::number(),
            'name' => Schema::string()
        ], ['id', 'name']);
        
        $schema = Schema::collection($itemSchema)->min(1);
        
        $data = [
            ['id' => 1, 'name' => 'Item 1'],
            ['id' => 2, 'name' => 'Item 2']
        ];
        
        $result = Eff::runSafely($schema->decode($data));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe($data);
    });

    test('object collection with optional fields', function () {
        $userSchema = Schema::object([
            'id' => Schema::number(),
            'name' => Schema::string(),
            'email' => Schema::email(Schema::string())
        ], ['id', 'name']); // email is optional
        
        $schema = Schema::collection($userSchema)->max(10);
        
        $users = [
            ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
            ['id' => 2, 'name' => 'Jane'] // no email
        ];
        
        $result = Eff::runSafely($schema->decode($users));
        expect($result->isRight())->toBeTrue();
    });

    test('nested object collection', function () {
        $addressSchema = Schema::object([
            'street' => Schema::string(),
            'city' => Schema::string()
        ], ['street', 'city']);
        
        $personSchema = Schema::object([
            'name' => Schema::string(),
            'addresses' => Schema::collection($addressSchema)->nonEmpty()
        ], ['name', 'addresses']);
        
        $schema = Schema::collection($personSchema);
        
        $people = [
            [
                'name' => 'Alice',
                'addresses' => [
                    ['street' => '123 Main St', 'city' => 'Springfield'],
                    ['street' => '456 Oak Ave', 'city' => 'Riverside']
                ]
            ]
        ];
        
        $result = Eff::runSafely($schema->decode($people));
        expect($result->isRight())->toBeTrue();
    });
});

describe('Record Collections', function () {
    test('string-to-string record collection', function () {
        $recordSchema = Schema::record(Schema::string(), Schema::string());
        $schema = Schema::collection($recordSchema)->max(3);
        
        $data = [
            ['en' => 'Hello', 'es' => 'Hola'],
            ['en' => 'Goodbye', 'es' => 'Adiós']
        ];
        
        $result = Eff::runSafely($schema->decode($data));
        expect($result->isRight())->toBeTrue();
    });

    test('mixed record collection', function () {
        $configSchema = Schema::record(
            Schema::string(),
            Schema::union([Schema::string(), Schema::number(), Schema::boolean()])
        );
        
        $schema = Schema::collection($configSchema)->between(1, 5);
        
        $configs = [
            ['debug' => true, 'port' => 8080, 'host' => 'localhost'],
            ['enabled' => false, 'timeout' => 30]
        ];
        
        $result = Eff::runSafely($schema->decode($configs));
        expect($result->isRight())->toBeTrue();
    });
});

describe('Transformation Collections', function () {
    test('string to number transformation collection', function () {
        $stringToNumberSchema = Schema::transform(
            Schema::string(),
            Schema::number(),
            fn($str) => (float) $str,
            fn($num) => (string) $num
        );
        
        $schema = Schema::collection($stringToNumberSchema)->length(3);
        
        // Decode: strings become numbers
        $result = Eff::runSafely($schema->decode(['1.5', '2.7', '3.14']));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe([1.5, 2.7, 3.14]);
        
        // Encode: numbers become strings
        $result = Eff::runSafely($schema->encode([1.5, 2.7, 3.14]));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe(['1.5', '2.7', '3.14']);
    });

    test('date string transformation collection', function () {
        $dateSchema = Schema::transform(
            Schema::pattern(Schema::string(), '/^\d{4}-\d{2}-\d{2}$/'),
            Schema::any(), // Would be a Date object in real use
            fn($str) => new DateTime($str),
            fn($date) => $date->format('Y-m-d')
        );
        
        $schema = Schema::collection($dateSchema)->max(10);
        
        $result = Eff::runSafely($schema->decode(['2023-01-01', '2023-12-31']));
        expect($result->isRight())->toBeTrue();
        
        $decoded = $result->fold(fn($e) => null, fn($v) => $v);
        expect($decoded[0])->toBeInstanceOf(DateTime::class);
        expect($decoded[1])->toBeInstanceOf(DateTime::class);
    });
});

describe('Edge Cases and Error Handling', function () {
    test('empty collections with various schemas', function () {
        $schemas = [
            Schema::collection(Schema::string()),
            Schema::collection(Schema::any()),
            Schema::collection(Schema::union([Schema::string(), Schema::number()])),
            Schema::collection(Schema::object(['id' => Schema::number()], ['id']))
        ];
        
        foreach ($schemas as $schema) {
            $result = Eff::runSafely($schema->decode([]));
            expect($result->isRight())->toBeTrue();
            expect($result->fold(fn($e) => null, fn($v) => $v))->toBe([]);
        }
    });

    test('deeply nested mixed collections', function () {
        $deepSchema = Schema::collection(
            Schema::collection(
                Schema::collection(Schema::union([Schema::string(), Schema::number()]))
                    ->min(1)
            )->min(1)
        )->min(1);
        
        $deepData = [
            [
                ['hello', 42],
                ['world', 3.14]
            ],
            [
                ['test', 123]
            ]
        ];
        
        $result = Eff::runSafely($deepSchema->decode($deepData));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe($deepData);
    });

    test('large collection performance', function () {
        $schema = Schema::collection(Schema::number())->max(1000);
        
        $largeArray = range(1, 500);
        $result = Eff::runSafely($schema->decode($largeArray));
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe($largeArray);
    });
});