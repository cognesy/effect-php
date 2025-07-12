<?php declare(strict_types=1);

use EffectPHP\Core\Context;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Effects\AsyncEffect;
use EffectPHP\Core\Effects\BindEffect;
use EffectPHP\Core\Effects\FailEffect;
use EffectPHP\Core\Effects\ProvideEffect;
use EffectPHP\Core\Effects\PureEffect;
use EffectPHP\Core\Effects\ServiceEffect;
use EffectPHP\Core\Effects\SleepEffect;
use EffectPHP\Core\Effects\SuspendEffect;
use EffectPHP\Core\Fx;
use EffectPHP\Core\Layer;
use EffectPHP\Core\Runtimes\SyncRuntime;
use EffectPHP\Utils\Clock\Clock;
use EffectPHP\Utils\Clock\VirtualClock;
use EffectPHP\Utils\Exceptions\TimeoutException;

beforeEach(function () {
    $this->clock = new VirtualClock();
    $this->context = (new Context())->with(Clock::class, $this->clock);
    $this->runtime = new SyncRuntime();
});

test('PureEffect returns its value', function () {
    $effect = Fx::value(42);
    expect($effect)->toBeInstanceOf(PureEffect::class);
    $result = $this->runtime->withContext($this->context)->run($effect);
    expect($result)->toBe(42);
});

test('Unit effect returns null', function () {
    $effect = Fx::unit();
    expect($effect)->toBeInstanceOf(PureEffect::class);
    $result = $this->runtime->withContext($this->context)->run($effect);
    expect($result)->toBeNull();
});

test('FailEffect throws its error', function () {
    $error = new TimeoutException('Test timeout');
    $effect = Fx::fail($error);
    expect($effect)->toBeInstanceOf(FailEffect::class);
    expect(fn() => $this->runtime->withContext($this->context)->run($effect))->toThrow($error);
});

test('ServiceEffect retrieves service from context', function () {
    $service = new stdClass();
    $context = $this->context->with('TestService', $service);
    $effect = Fx::service('TestService');
    expect($effect)->toBeInstanceOf(ServiceEffect::class);
    $result = $this->runtime->withContext($context)->run($effect);
    expect($result)->toBe($service);
});

test('ServiceEffect throws when service is missing', function () {
    $effect = Fx::service('MissingService');
    expect(fn() => $this->runtime->withContext($this->context)->run($effect))
        ->toThrow(RuntimeException::class, 'Service MissingService not provided');
});

test('SuspendEffect executes its thunk', function () {
    $effect = Fx::suspend(fn() => 123);
    expect($effect)->toBeInstanceOf(SuspendEffect::class);
    $result = $this->runtime->withContext($this->context)->run($effect);
    expect($result)->toBe(123);
});

test('SleepEffect advances virtual clock', function () {
    $effect = Fx::sleep(1000);
    expect($effect)->toBeInstanceOf(SleepEffect::class);
    $startTime = $this->clock->currentTimeMillis();
    $this->runtime->withContext($this->context)->run($effect);
    $endTime = $this->clock->currentTimeMillis();
    expect($endTime - $startTime)->toBe(1000);
});

test('AsyncEffect executes synchronously in SyncRuntime', function () {
    $called = false;
    $effect = Fx::async(function () use (&$called) {
        $called = true;
        return 'async-result';
    });
    expect($effect)->toBeInstanceOf(AsyncEffect::class);
    $result = $this->runtime->withContext($this->context)->run($effect);
    expect($called)->toBeTrue();
    expect($result)->toBe('async-result');
});

test('BindEffect chains computations', function () {
    $effect = Fx::value(10)->flatMap(fn($x) => Fx::value($x * 2));
    expect($effect)->toBeInstanceOf(BindEffect::class);
    $result = $this->runtime->withContext($this->context)->run($effect);
    expect($result)->toBe(20);
});

test('ProvideEffect applies layer and restores context', function () {
    $service = new stdClass();
    $layer = Layer::provides('TestService', $service);
    $effect = Fx::service('TestService')->provide($layer);
    expect($effect)->toBeInstanceOf(ProvideEffect::class);
    $result = $this->runtime->withContext($this->context)->run($effect);
    expect($result)->toBe($service);
    expect($this->context->has('TestService'))->toBeFalse();
});

test('Map combinator transforms value', function () {
    $effect = Fx::value(5)->map(fn($x) => $x + 1);
    $result = $this->runtime->withContext($this->context)->run($effect);
    expect($result)->toBe(6);
});

test('Then combinator chains effects', function () {
    $effect = Fx::value(1)->then(Fx::value(2));
    $result = $this->runtime->withContext($this->context)->run($effect);
    expect($result)->toBe(2);
});

test('No handler throws RuntimeException', function () {
    $badEffect = new class implements Effect {
        use EffectPHP\Core\Traits\Combinators;
    };
    $runtime = $this->runtime->withContext($this->context);
    try {
        $runtime->run($badEffect);
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('No handler for: ' . get_class($badEffect));
    }
});

test('RunAll executes multiple programs', function () {
    $programs = [
        Fx::value(1),
        Fx::value(2),
        Fx::value(3),
    ];
    $results = $this->runtime->withContext($this->context)->runAll(...$programs);
    expect($results)->toBe([1, 2, 3]);
});