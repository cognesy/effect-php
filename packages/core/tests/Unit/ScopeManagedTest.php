<?php

use EffectPHP\Core\Context;
use EffectPHP\Core\Managed;
use EffectPHP\Core\Runtimes\SyncRuntime;
use EffectPHP\Core\Scope;
use EffectPHP\Utils\Clock\Clock;
use EffectPHP\Utils\Clock\VirtualClock;

beforeEach(function () {
    $this->runtime = new SyncRuntime();
    $this->context = new Context();
    $this->context = $this->context->with(Clock::class, new VirtualClock());
});

test('Scope executes finalizers in LIFO order', function () {
    $scope = new Scope();
    $calls = [];

    $scope->add(function () use (&$calls) {
        $calls[] = 1;
    });
    $scope->add(function () use (&$calls) {
        $calls[] = 2;
    });
    $scope->add(function () use (&$calls) {
        $calls[] = 3;
    });

    $scope->close();

    expect($calls)->toEqual([3, 2, 1]);
});

test('Scope swallows exceptions in finalizers', function () {
    $scope = new Scope();
    $calls = [];

    $scope->add(function () use (&$calls) {
        $calls[] = 1;
        throw new RuntimeException('Error');
    });
    $scope->add(function () use (&$calls) {
        $calls[] = 2;
    });

    expect(fn() => $scope->close())->not->toThrow(Exception::class);
    expect($calls)->toEqual([2, 1]);
});

test('Managed acquires resource and registers release with Scope', function () {
    $acquired = null;
    $released = null;

    $managed = Managed::from(
        acquire: function () use (&$acquired) {
            $acquired = 'resource';
            return $acquired;
        },
        release: function ($resource) use (&$released) {
            $released = $resource;
        },
    );

    $effect = $managed->reserve()->map(function ($res) {
        return $res;
    });

    $scope = new Scope();
    $this->context = $this->context->with(Scope::class, $scope);

    $result = $this->runtime
        ->withContext($this->context)
        ->run($effect)
    ;

    expect($acquired)->toBe('resource');
    expect($result)->toBe('resource');
    // Runtime automatically closes scope, so resource should be released
    expect($released)->toBe('resource');
});

test('Managed handles acquisition failure', function () {
    $released = null;

    $managed = Managed::from(
        acquire: function () {
            throw new RuntimeException('Acquisition failed');
        },
        release: function ($resource) use (&$released) {
            $released = $resource;
        },
    );

    $effect = $managed->reserve();

    $scope = new Scope();
    $this->context = $this->context->with(Scope::class, $scope);

    expect(fn() => $this->runtime
        ->withContext($this->context)
        ->run($effect),
    )->toThrow(RuntimeException::class, 'Acquisition failed');
    expect($released)->toBeNull();
});