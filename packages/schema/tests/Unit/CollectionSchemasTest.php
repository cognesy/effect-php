<?php

declare(strict_types=1);

use EffectPHP\Schema\Schema;

describe('Collection Schema Types', function () {
    
    describe('Tuple Schema', function () {
        it('validates tuples with correct types and length', function () {
            $tupleSchema = Schema::tuple(
                Schema::string(),
                Schema::number(),
                Schema::boolean()
            );
            
            $validData = ["Alice", 25, true];
            
            expect($tupleSchema->is($validData))->toBeTrue();
        });

        it('rejects tuples with wrong length', function () {
            $tupleSchema = Schema::tuple(Schema::string(), Schema::number());
            
            $tooShort = ["Alice"];
            $tooLong = ["Alice", 25, true];
            
            expect($tupleSchema->is($tooShort))->toBeFalse();
            expect($tupleSchema->is($tooLong))->toBeFalse();
        });

        it('rejects tuples with wrong types', function () {
            $tupleSchema = Schema::tuple(Schema::string(), Schema::number());
            
            $wrongTypes = [123, "not a number"];
            
            expect($tupleSchema->is($wrongTypes))->toBeFalse();
        });

        it('rejects non-arrays', function () {
            $tupleSchema = Schema::tuple(Schema::string());
            
            expect($tupleSchema->is("not an array"))->toBeFalse();
            expect($tupleSchema->is(123))->toBeFalse();
        });

        it('rejects associative arrays', function () {
            $tupleSchema = Schema::tuple(Schema::string(), Schema::number());
            
            $assocArray = ['name' => 'Alice', 'age' => 25];
            
            expect($tupleSchema->is($assocArray))->toBeFalse();
        });
    });

    describe('NonEmptyArray Schema', function () {
        it('validates non-empty arrays', function () {
            $nonEmptySchema = Schema::nonEmptyArray(Schema::string());
            
            $validData = ["apple", "banana"];
            
            expect($nonEmptySchema->is($validData))->toBeTrue();
        });

        it('rejects empty arrays', function () {
            $nonEmptySchema = Schema::nonEmptyArray(Schema::string());
            
            $emptyArray = [];
            
            expect($nonEmptySchema->is($emptyArray))->toBeFalse();
        });

        it('validates single-element arrays', function () {
            $nonEmptySchema = Schema::nonEmptyArray(Schema::number());
            
            $singleElement = [42];
            
            expect($nonEmptySchema->is($singleElement))->toBeTrue();
        });

        it('rejects arrays with wrong item types', function () {
            $nonEmptySchema = Schema::nonEmptyArray(Schema::string());
            
            $wrongTypes = [123, 456];
            
            expect($nonEmptySchema->is($wrongTypes))->toBeFalse();
        });

        it('rejects non-arrays', function () {
            $nonEmptySchema = Schema::nonEmptyArray(Schema::string());
            
            expect($nonEmptySchema->is("not an array"))->toBeFalse();
            expect($nonEmptySchema->is(123))->toBeFalse();
        });
    });

    describe('Record Schema', function () {
        it('validates record types with mixed values', function () {
            $recordSchema = Schema::record(Schema::string(), Schema::mixed());
            
            $validData = [
                'name' => 'John',
                'age' => 30,
                'active' => true
            ];
            
            expect($recordSchema->is($validData))->toBeTrue();
        });

        it('validates empty records', function () {
            $recordSchema = Schema::record(Schema::string(), Schema::number());
            
            $emptyRecord = [];
            
            expect($recordSchema->is($emptyRecord))->toBeTrue();
        });

        it('rejects sequential arrays', function () {
            $recordSchema = Schema::record(Schema::string(), Schema::number());
            
            $sequentialArray = [1, 2, 3];
            
            expect($recordSchema->is($sequentialArray))->toBeFalse();
        });

        it('rejects non-arrays', function () {
            $recordSchema = Schema::record(Schema::string(), Schema::number());
            
            expect($recordSchema->is("not an array"))->toBeFalse();
            expect($recordSchema->is(123))->toBeFalse();
        });
    });

    describe('Integration with helper methods', function () {
        it('works with decodeUnknownEither for tuple', function () {
            $tupleSchema = Schema::tuple(Schema::string(), Schema::number());
            $decoder = Schema::decodeUnknownEither($tupleSchema);
            
            $result = $decoder(["hello", 42]);
            
            expect($result->isRight())->toBeTrue();
            $value = $result->fold(fn($e) => null, fn($v) => $v);
            expect($value)->toBe(["hello", 42]);
        });

        it('works with decodeUnknownEither for nonEmptyArray', function () {
            $nonEmptySchema = Schema::nonEmptyArray(Schema::string());
            $decoder = Schema::decodeUnknownEither($nonEmptySchema);
            
            $result = $decoder(["a", "b", "c"]);
            
            expect($result->isRight())->toBeTrue();
            $value = $result->fold(fn($e) => null, fn($v) => $v);
            expect($value)->toBe(["a", "b", "c"]);
        });

        it('fails gracefully with Either for invalid data', function () {
            $tupleSchema = Schema::tuple(Schema::string());
            $decoder = Schema::decodeUnknownEither($tupleSchema);
            
            $result = $decoder(["too", "many", "elements"]);
            
            expect($result->isLeft())->toBeTrue();
        });
    });
});