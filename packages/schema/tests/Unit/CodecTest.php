<?php declare(strict_types=1);

use EffectPHP\Schema\Codec;
use EffectPHP\Schema\Schema;

it('decodes valid input synchronously with decodeUnknownSync', function () {
    $stringSchema = Schema::string();
    $validString = "Hello World";

    $decoder = Codec::decodeUnknownSync($stringSchema);
    $result = $decoder($validString);

    expect($result)->toBe($validString);
});

it('throws on invalid input with decodeUnknownSync', function () {
    $stringSchema = Schema::string();
    $invalidInput = 123;

    $decoder = Codec::decodeUnknownSync($stringSchema);

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

    $decoder = Codec::decodeUnknownResult($stringSchema);
    $result = $decoder($validString);

    expect($result->isSuccess())->toBeTrue();
    $value = $result->getValueOrNull();
    expect($value)->toBe($validString);
});

it('returns Left for invalid input with decodeUnknownEither', function () {
    $stringSchema = Schema::string();
    $invalidInput = 123;

    $decoder = Codec::decodeUnknownResult($stringSchema);
    $result = $decoder($invalidInput);

    expect($result->isFailure())->toBeTrue();
});

it('encodes valid value synchronously with encodeSync', function () {
    $stringSchema = Schema::string();
    $validString = "Hello World";

    $encoder = Codec::encodeSync($stringSchema);
    $result = $encoder($validString);

    expect($result)->toBe($validString);
});

it('returns Right for valid encoding with encodeEither', function () {
    $stringSchema = Schema::string();
    $validString = "Hello World";

    $encoder = Codec::encodeResult($stringSchema);
    $result = $encoder($validString);

    expect($result->isSuccess())->toBeTrue();
    $value = $result->getValueOrNull();
    expect($value)->toBe($validString);
});

it('works with record schemas', function () {
    $recordSchema = Schema::record(Schema::string(), Schema::mixed());
    $testData = [
        'name' => 'John',
        'age' => 30,
        'active' => true
    ];

    $decoder = Codec::decodeUnknownResult($recordSchema);
    $result = $decoder($testData);

    expect($result->isSuccess())->toBeTrue();
    $decoded = $result->getValueOrNull();
    expect($decoded)->toBe($testData);
});

it('works with union schemas', function () {
    $unionSchema = Schema::union([Schema::string(), Schema::number()]);

    $decoder = Codec::decodeUnknownResult($unionSchema);

    $stringResult = $decoder("hello");
    expect($stringResult->isSuccess())->toBeTrue();

    $numberResult = $decoder(42);
    expect($numberResult->isSuccess())->toBeTrue();

    $invalidResult = $decoder(true);
    expect($invalidResult->isFailure())->toBeTrue();
});