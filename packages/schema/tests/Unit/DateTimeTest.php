<?php

declare(strict_types=1);

use EffectPHP\Core\Eff;
use EffectPHP\Schema\Schema;

describe('Date Schema', function () {
    test('decodes valid date string', function () {
        $schema = Schema::date();
        $result = Eff::runSafely($schema->decode('2023-12-25'));
        
        expect($result->isRight())->toBeTrue();
        $decoded = $result->fold(fn($e) => null, fn($v) => $v);
        expect($decoded)->toBeInstanceOf(DateTime::class);
        expect($decoded->format('Y-m-d'))->toBe('2023-12-25');
    });

    test('fails on invalid date string format', function () {
        $schema = Schema::date();
        $result = Eff::runSafely($schema->decode('25-12-2023'));
        
        expect($result->isLeft())->toBeTrue();
    });

    test('fails on non-string input', function () {
        $schema = Schema::date();
        $result = Eff::runSafely($schema->decode(123));
        
        expect($result->isLeft())->toBeTrue();
    });

    test('fails on invalid date values', function () {
        $schema = Schema::date();
        $result = Eff::runSafely($schema->decode('2023-13-32'));
        
        expect($result->isLeft())->toBeTrue();
    });

    test('encodes DateTime to date string', function () {
        $schema = Schema::date();
        $date = new DateTime('2023-12-25 15:30:00');
        $result = Eff::runSafely($schema->encode($date));
        
        expect($result->isRight())->toBeTrue();
        expect($result->fold(fn($e) => null, fn($v) => $v))->toBe('2023-12-25');
    });

    test('fails encoding non-DateTime input', function () {
        $schema = Schema::date();
        $result = Eff::runSafely($schema->encode('2023-12-25'));
        
        expect($result->isLeft())->toBeTrue();
    });
});

describe('DateTime Schema', function () {
    test('decodes ISO datetime string', function () {
        $schema = Schema::datetime();
        $result = Eff::runSafely($schema->decode('2023-12-25T15:30:00+00:00'));
        
        expect($result->isRight())->toBeTrue();
        $decoded = $result->fold(fn($e) => null, fn($v) => $v);
        expect($decoded)->toBeInstanceOf(DateTime::class);
    });

    test('decodes simple datetime format', function () {
        $schema = Schema::datetime();
        $result = Eff::runSafely($schema->decode('2023-12-25 15:30:00'));
        
        expect($result->isRight())->toBeTrue();
        $decoded = $result->fold(fn($e) => null, fn($v) => $v);
        expect($decoded)->toBeInstanceOf(DateTime::class);
        expect($decoded->format('Y-m-d H:i:s'))->toBe('2023-12-25 15:30:00');
    });

    test('decodes T-separated datetime format', function () {
        $schema = Schema::datetime();
        $result = Eff::runSafely($schema->decode('2023-12-25T15:30:00'));
        
        expect($result->isRight())->toBeTrue();
        $decoded = $result->fold(fn($e) => null, fn($v) => $v);
        expect($decoded)->toBeInstanceOf(DateTime::class);
    });

    test('decodes UTC datetime format', function () {
        $schema = Schema::datetime();
        $result = Eff::runSafely($schema->decode('2023-12-25T15:30:00Z'));
        
        expect($result->isRight())->toBeTrue();
        $decoded = $result->fold(fn($e) => null, fn($v) => $v);
        expect($decoded)->toBeInstanceOf(DateTime::class);
    });

    test('fails on non-string input', function () {
        $schema = Schema::datetime();
        $result = Eff::runSafely($schema->decode(123));
        
        expect($result->isLeft())->toBeTrue();
    });

    test('fails on invalid datetime string', function () {
        $schema = Schema::datetime();
        $result = Eff::runSafely($schema->decode('invalid-datetime'));
        
        expect($result->isLeft())->toBeTrue();
    });

    test('encodes DateTime to ISO string', function () {
        $schema = Schema::datetime();
        $date = new DateTime('2023-12-25 15:30:00');
        $result = Eff::runSafely($schema->encode($date));
        
        expect($result->isRight())->toBeTrue();
        $encoded = $result->fold(fn($e) => null, fn($v) => $v);
        expect($encoded)->toContain('2023-12-25T15:30:00');
    });

    test('fails encoding non-DateTime input', function () {
        $schema = Schema::datetime();
        $result = Eff::runSafely($schema->encode('2023-12-25T15:30:00'));
        
        expect($result->isLeft())->toBeTrue();
    });
});

describe('Date/DateTime Schema Composition', function () {
    test('works in object schema', function () {
        $schema = Schema::object([
            'created_date' => Schema::date(),
            'updated_at' => Schema::datetime(),
            'name' => Schema::string(),
        ], ['created_date', 'updated_at', 'name']);

        $input = [
            'created_date' => '2023-12-25',
            'updated_at' => '2023-12-25T15:30:00+00:00',
            'name' => 'Test Event'
        ];

        $result = Eff::runSafely($schema->decode($input));
        expect($result->isRight())->toBeTrue();
        
        $decoded = $result->fold(fn($e) => null, fn($v) => $v);
        expect($decoded['created_date'])->toBeInstanceOf(DateTime::class);
        expect($decoded['updated_at'])->toBeInstanceOf(DateTime::class);
        expect($decoded['name'])->toBe('Test Event');
    });

    test('works in array schema', function () {
        $schema = Schema::array(Schema::date());
        $input = ['2023-12-25', '2023-12-26', '2023-12-27'];
        
        $result = Eff::runSafely($schema->decode($input));
        expect($result->isRight())->toBeTrue();
        
        $decoded = $result->fold(fn($e) => null, fn($v) => $v);
        expect($decoded)->toHaveCount(3);
        expect($decoded[0])->toBeInstanceOf(DateTime::class);
        expect($decoded[0]->format('Y-m-d'))->toBe('2023-12-25');
    });
});