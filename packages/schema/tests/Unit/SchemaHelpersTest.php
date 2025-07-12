<?php declare(strict_types=1);

use EffectPHP\Schema\Schema;

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
