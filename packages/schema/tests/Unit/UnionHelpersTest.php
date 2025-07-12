<?php declare(strict_types=1);

use EffectPHP\Schema\Codec;
use EffectPHP\Schema\Schema;

describe('Union Helper Methods', function () {
    
    describe('nullOr', function () {
        it('accepts null values', function () {
            $schema = Schema::nullOr(Schema::string());
            
            expect($schema->is(null))->toBeTrue();
        });

        it('accepts values of the wrapped type', function () {
            $schema = Schema::nullOr(Schema::string());
            
            expect($schema->is("hello"))->toBeTrue();
        });

        it('rejects values not of the wrapped type', function () {
            $schema = Schema::nullOr(Schema::string());
            
            expect($schema->is(123))->toBeFalse();
            expect($schema->is(true))->toBeFalse();
        });

        it('works with complex schemas', function () {
            $schema = Schema::nullOr(
                Schema::object(['name' => Schema::string()], ['name'])
            );
            
            expect($schema->is(null))->toBeTrue();
            expect($schema->is(['name' => 'John']))->toBeTrue();
            expect($schema->is(['name' => 123]))->toBeFalse();
        });
    });

    describe('nullishOr', function () {
        it('behaves same as nullOr in PHP', function () {
            $nullOrSchema = Schema::nullOr(Schema::number());
            $nullishOrSchema = Schema::nullishOr(Schema::number());
            
            $testValues = [null, 42, 3.14, "not a number", true];
            
            foreach ($testValues as $value) {
                expect($nullishOrSchema->is($value))->toBe($nullOrSchema->is($value));
            }
        });

        it('accepts null and the wrapped type', function () {
            $schema = Schema::nullishOr(Schema::boolean());
            
            expect($schema->is(null))->toBeTrue();
            expect($schema->is(true))->toBeTrue();
            expect($schema->is(false))->toBeTrue();
            expect($schema->is("not boolean"))->toBeFalse();
        });
    });

    describe('undefinedOr', function () {
        it('behaves same as nullOr in PHP', function () {
            $nullOrSchema = Schema::nullOr(Schema::string());
            $undefinedOrSchema = Schema::undefinedOr(Schema::string());
            
            $testValues = [null, "hello", 123, true, []];
            
            foreach ($testValues as $value) {
                expect($undefinedOrSchema->is($value))->toBe($nullOrSchema->is($value));
            }
        });

        it('accepts null and the wrapped type', function () {
            $schema = Schema::undefinedOr(Schema::number());
            
            expect($schema->is(null))->toBeTrue();
            expect($schema->is(42))->toBeTrue();
            expect($schema->is("not number"))->toBeFalse();
        });
    });

    describe('integration with helper methods', function () {
        it('works with decodeUnknownEither', function () {
            $schema = Schema::nullOr(Schema::string());
            $decoder = Codec::decodeUnknownResult($schema);
            
            $validResult = $decoder(null);
            expect($validResult->isSuccess())->toBeTrue();
            $value = $validResult->fold(fn($e) => "error", fn($v) => $v);
            expect($value)->toBeNull();
            
            $validResult2 = $decoder("hello");
            expect($validResult2->isSuccess())->toBeTrue();
            $value2 = $validResult2->fold(fn($e) => "error", fn($v) => $v);
            expect($value2)->toBe("hello");
            
            $invalidResult = $decoder(123);
            expect($invalidResult->isFailure())->toBeTrue();
        });

        it('works with encodeEither', function () {
            $schema = Schema::nullOr(Schema::string());
            $encoder = Codec::encodeResult($schema);
            
            $nullResult = $encoder(null);
            expect($nullResult->isSuccess())->toBeTrue();
            
            $stringResult = $encoder("test");
            expect($stringResult->isSuccess())->toBeTrue();
        });
    });

    describe('complex scenarios', function () {
        it('handles nested nullable structures', function () {
            $schema = Schema::object([
                'user' => Schema::nullOr(
                    Schema::object([
                        'name' => Schema::string(),
                        'email' => Schema::nullOr(Schema::string())
                    ], ['name'])
                )
            ], ['user']);
            
            // All user data
            expect($schema->is([
                'user' => [
                    'name' => 'John',
                    'email' => 'john@example.com'
                ]
            ]))->toBeTrue();
            
            // User with null email
            expect($schema->is([
                'user' => [
                    'name' => 'John',
                    'email' => null
                ]
            ]))->toBeTrue();
            
            // Null user
            expect($schema->is([
                'user' => null
            ]))->toBeTrue();
            
            // Invalid structure
            expect($schema->is([
                'user' => 'not an object'
            ]))->toBeFalse();
        });

        it('works with union of multiple nullable types', function () {
            $schema = Schema::union([
                Schema::nullOr(Schema::string()),
                Schema::nullOr(Schema::number()),
                Schema::boolean()
            ]);
            
            expect($schema->is(null))->toBeTrue();
            expect($schema->is("hello"))->toBeTrue();
            expect($schema->is(42))->toBeTrue();
            expect($schema->is(true))->toBeTrue();
            expect($schema->is([]))->toBeFalse();
        });
    });
});