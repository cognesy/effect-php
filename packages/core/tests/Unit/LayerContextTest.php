<?php declare(strict_types=1);

namespace EffectPHP\Core\Tests\Unit;

use EffectPHP\Core\Context;
use EffectPHP\Core\Layer;
use EffectPHP\Utils\Clock\VirtualClock;
use RuntimeException;
use stdClass;

beforeEach(function () {
    $this->clock = new VirtualClock();
    $this->context = new Context();
});

it('creates a context with no services', function () {
    expect($this->context->has(stdClass::class))->toBeFalse();
});

it('adds a service to context', function () {
    $service = new stdClass();
    $newContext = $this->context->with(stdClass::class, $service);

    expect($newContext->has(stdClass::class))->toBeTrue();
    expect($newContext->get(stdClass::class))->toBe($service);
    expect($this->context->has(stdClass::class))->toBeFalse(); // Original unchanged
});

it('throws when getting non-existent service', function () {
    expect(fn() => $this->context->get(stdClass::class))
        ->toThrow(RuntimeException::class, 'Service stdClass not provided');
});

it('merges contexts with right bias', function () {
    $service1 = new stdClass();
    $service1->value = 'left';
    $service2 = new stdClass();
    $service2->value = 'right';

    $left = $this->context->with(stdClass::class, $service1);
    $right = $this->context->with(stdClass::class, $service2);
    $merged = $left->merge($right);

    expect($merged->get(stdClass::class)->value)->toBe('right');
});

it('creates layer with succeed', function () {
    $service = new stdClass();
    $layer = Layer::provides(stdClass::class, $service);
    $newContext = $layer->applyTo($this->context);

    expect($newContext->get(stdClass::class))->toBe($service);
});

it('creates layer with factory', function () {
    $layer = Layer::providesFrom(stdClass::class, fn(Context $ctx) => new stdClass());
    $newContext = $layer->applyTo($this->context);

    expect($newContext->has(stdClass::class))->toBeTrue();
    expect($newContext->get(stdClass::class))->toBeInstanceOf(stdClass::class);
});

it('composes layers sequentially', function () {
    $service1 = new stdClass();
    $service1->value = 'first';
    $service2 = new stdClass();
    $service2->value = 'second';

    $layer1 = Layer::provides(stdClass::class, $service1);
    $layer2 = Layer::provides(stdClass::class, $service2);
    $composed = $layer2->dependsOn($layer1);
    $newContext = $composed->applyTo($this->context);

    expect($newContext->get(stdClass::class)->value)->toBe('second');
});

it('merges layers with right bias', function () {
    $service1 = new stdClass();
    $service1->value = 'left';
    $service2 = new stdClass();
    $service2->value = 'right';

    $layer1 = Layer::provides(stdClass::class, $service1);
    $layer2 = Layer::provides(stdClass::class, $service2);
    $merged = $layer1->merge($layer2);
    $newContext = $merged->applyTo($this->context);

    expect($newContext->get(stdClass::class)->value)->toBe('right');
});
