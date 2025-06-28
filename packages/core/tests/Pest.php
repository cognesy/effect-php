<?php

declare(strict_types=1);

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Either;
use EffectPHP\Core\Eff;
use EffectPHP\Core\Run;
use EffectPHP\Core\Option;
use EffectPHP\Core\Result\Result;
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

expect()->extend('toRunSuccessfully', function () {
    $result = Run::syncResult($this->value);
    
    expect($result->isSuccess())->toBeTrue();
    
    return $this;
});

expect()->extend('toFailWith', function (string $exceptionClass) {
    $result = Run::syncResult($this->value);
    
    expect($result->isFailure())->toBeTrue();
    expect($result->getErrorOrNull())->toBeInstanceOf($exceptionClass);
    
    return $this;
});

expect()->extend('toProduceValue', function (mixed $expectedValue) {
    $result = Run::syncResult($this->value);
    
    expect($result->isSuccess())->toBeTrue();
    expect($result->getValueOrNull())->toBe($expectedValue);
    
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

function runEffect($effect): mixed
{
    return Run::sync($effect);
}

function runEffectSafely($effect): Result
{
    return Run::syncResult($effect);
}