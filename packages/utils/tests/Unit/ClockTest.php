<?php declare(strict_types=1);

namespace EffectPHP\Utils\Tests\Unit;

use EffectPHP\Utils\Clock\Clock;
use EffectPHP\Utils\Clock\SystemClock;
use EffectPHP\Utils\Clock\VirtualClock;

describe('Clock Interface Compliance', function () {
    it('SystemClock implements Clock interface correctly', function () {
        $clock = new SystemClock();

        expect($clock)->toBeInstanceOf(Clock::class);
        expect($clock->currentTimeMillis())->toBeInt();
        expect($clock->nanoTime())->toBeInt();
        expect($clock->nanoTime())->toBeGreaterThan(0);
    });

    it('TestClock implements Clock interface correctly', function () {
        $clock = new VirtualClock(1000);

        expect($clock)->toBeInstanceOf(Clock::class);
        expect($clock->currentTimeMillis())->toBe(1000);
        expect($clock->nanoTime())->toBeInt();
    });
});
