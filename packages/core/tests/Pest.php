<?php

declare(strict_types=1);

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Either;
use EffectPHP\Core\Eff;
use EffectPHP\Core\Option;
use EffectPHP\Core\Runtime\RuntimeManager;
use Pest\Expectation;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

// uses(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOption', function () {
    return $this->toBeInstanceOf(Option::class);
});

expect()->extend('toBeEither', function () {
    return $this->toBeInstanceOf(Either::class);
});

expect()->extend('toBeEffect', function () {
    return $this->toBeInstanceOf(Effect::class);
});

expect()->extend('toRunSuccessfully', function () {
    $result = Eff::runSafely($this->value);
    
    expect($result->isRight())->toBeTrue();
    
    return $this;
});

expect()->extend('toFailWith', function (string $exceptionClass) {
    $result = Eff::runSafely($this->value);
    
    expect($result->isLeft())->toBeTrue();
    expect($result->fold(fn($e) => $e, fn($v) => null))->toBeInstanceOf($exceptionClass);
    
    return $this;
});

expect()->extend('toProduceValue', function (mixed $expectedValue) {
    $result = Eff::runSafely($this->value);
    
    expect($result->isRight())->toBeTrue();
    expect($result->fold(fn($e) => null, fn($v) => $v))->toBe($expectedValue);
    
    return $this;
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function runtime()
{
    return RuntimeManager::default();
}

function expectEffect($effect): Expectation
{
    return expect($effect)->toBeEffect();
}

function runEffect($effect): mixed
{
    return Eff::runSync($effect);
}

function runEffectSafely($effect): Either
{
    return Eff::runSafely($effect);
}