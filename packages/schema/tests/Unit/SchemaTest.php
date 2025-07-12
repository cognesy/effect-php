<?php

use EffectPHP\Core\Run;
use EffectPHP\Schema\Schema;

it('can create and validate a string schema', function () {
    $schema = Schema::string();

    expect($schema->is('hello'))->toBeTrue();
    expect($schema->is(123))->toBeFalse();
    
    // Test using Effect directly
    $result = Run::syncResult($schema->decode('hello'));
    expect($result->isSuccess())->toBeTrue();
});

it('can create and validate a number schema', function () {
    $schema = Schema::number();

    expect($schema->is(123))->toBeTrue();
    expect($schema->is(123.45))->toBeTrue();
    expect($schema->is('hello'))->toBeFalse();
});

it('can create and validate a boolean schema', function () {
    $schema = Schema::boolean();

    expect($schema->is(true))->toBeTrue();
    expect($schema->is(false))->toBeTrue();
    expect($schema->is(1))->toBeFalse();
});

it('can create and validate a literal schema', function () {
    $schema = Schema::literal('hello');

    expect($schema->is('hello'))->toBeTrue();
    expect($schema->is('world'))->toBeFalse();
});

it('can create and validate an array schema', function () {
    $schema = Schema::array(Schema::string());

    expect($schema->is(['a', 'b', 'c']))->toBeTrue();
    expect($schema->is(['a', 'b', 123]))->toBeFalse();
});

it('can create and validate an object schema', function () {
    $schema = Schema::object([
        'name' => Schema::string(),
        'age' => Schema::number(),
    ], ['name']);

    $validData = ['name' => 'John', 'age' => 30];
    $invalidData = ['age' => 30];

    expect($schema->is($validData))->toBeTrue();
    expect($schema->is($invalidData))->toBeFalse();
});

it('can create and validate a union schema', function () {
    $schema = Schema::union([
        Schema::string(),
        Schema::number(),
    ]);

    expect($schema->is('hello'))->toBeTrue();
    expect($schema->is(123))->toBeTrue();
    expect($schema->is(true))->toBeFalse();
});

it('can create and validate a refinement schema', function () {
    $schema = Schema::string()->pipe(fn($s) => Schema::minLength($s, 3));

    expect($schema->is('abc'))->toBeTrue();
    expect($schema->is('ab'))->toBeFalse();
});
