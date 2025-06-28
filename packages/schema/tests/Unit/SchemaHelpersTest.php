<?php

declare(strict_types=1);

use EffectPHP\Schema\Schema;

it('decodes valid input synchronously with decodeUnknownSync', function () {
    $stringSchema = Schema::string();
    $validString = "Hello World";
    
    $decoder = Schema::decodeUnknownSync($stringSchema);
    $result = $decoder($validString);
    
    expect($result)->toBe($validString);
});

it('throws on invalid input with decodeUnknownSync', function () {
    $stringSchema = Schema::string();
    $invalidInput = 123;
    
    $decoder = Schema::decodeUnknownSync($stringSchema);
    
    try {
        $decoder($invalidInput);
        expect(false)->toBeTrue("Should have thrown exception");
    } catch (Throwable $e) {
        expect(true)->toBeTrue();
    }
});

it('returns Right for valid input with decodeUnknownEither', function () {
    $stringSchema = Schema::string();
    $validString = "Hello World";
    
    $decoder = Schema::decodeUnknownResult($stringSchema);
    $result = $decoder($validString);
    
    expect($result->isSuccess())->toBeTrue();
    $value = $result->getValueOrNull();
    expect($value)->toBe($validString);
});

it('returns Left for invalid input with decodeUnknownEither', function () {
    $stringSchema = Schema::string();
    $invalidInput = 123;
    
    $decoder = Schema::decodeUnknownResult($stringSchema);
    $result = $decoder($invalidInput);
    
    expect($result->isFailure())->toBeTrue();
});

it('encodes valid value synchronously with encodeSync', function () {
    $stringSchema = Schema::string();
    $validString = "Hello World";
    
    $encoder = Schema::encodeSync($stringSchema);
    $result = $encoder($validString);
    
    expect($result)->toBe($validString);
});

it('returns Right for valid encoding with encodeEither', function () {
    $stringSchema = Schema::string();
    $validString = "Hello World";
    
    $encoder = Schema::encodeResult($stringSchema);
    $result = $encoder($validString);
    
    expect($result->isSuccess())->toBeTrue();
    $value = $result->getValueOrNull();
    expect($value)->toBe($validString);
});

it('validates input correctly with is helper', function () {
    $stringSchema = Schema::string();
    $validString = "Hello World";
    $invalidInput = 123;
    
    $validator = Schema::is($stringSchema);
    
    expect($validator($validString))->toBeTrue();
    expect($validator($invalidInput))->toBeFalse();
});

it('returns value for valid input with asserts helper', function () {
    $stringSchema = Schema::string();
    $validString = "Hello World";
    
    $asserter = Schema::asserts($stringSchema);
    $result = $asserter($validString);
    
    expect($result)->toBe($validString);
});

it('throws for invalid input with asserts helper', function () {
    $stringSchema = Schema::string();
    $invalidInput = 123;
    
    $asserter = Schema::asserts($stringSchema);
    
    try {
        $asserter($invalidInput);
        expect(false)->toBeTrue("Should have thrown exception");
    } catch (Throwable $e) {
        expect(true)->toBeTrue();
    }
});

it('works with record schemas', function () {
    $recordSchema = Schema::record(Schema::string(), Schema::mixed());
    $testData = [
        'name' => 'John',
        'age' => 30,
        'active' => true
    ];

    $decoder = Schema::decodeUnknownResult($recordSchema);
    $result = $decoder($testData);
    
    expect($result->isSuccess())->toBeTrue();
    $decoded = $result->getValueOrNull();
    expect($decoded)->toBe($testData);
});

it('works with union schemas', function () {
    $unionSchema = Schema::union([Schema::string(), Schema::number()]);
    
    $decoder = Schema::decodeUnknownResult($unionSchema);
    
    $stringResult = $decoder("hello");
    expect($stringResult->isSuccess())->toBeTrue();
    
    $numberResult = $decoder(42);
    expect($numberResult->isSuccess())->toBeTrue();
    
    $invalidResult = $decoder(true);
    expect($invalidResult->isFailure())->toBeTrue();
});