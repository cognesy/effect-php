<?php declare(strict_types=1);

namespace EffectPHP\Runtime\React;

use EffectPHP\Core\Context;
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\EffectHandler;
use EffectPHP\Core\Effects\SleepEffect;
use EffectPHP\Utils\ContinuationStack;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

/**
 * A non-blocking sleep handler for ReactPHP.
 *
 * This handler uses the ReactPHP event loop's timer to pause execution
 * without blocking the entire process, allowing other asynchronous
 * operations to continue.
 */
final class ReactSleepHandler implements EffectHandler
{
    /**
     * @param Effect $node
     * @return bool
     */
    public function supports(Effect $node): bool {
        return $node instanceof SleepEffect;
    }

    /**
     * Handles the SleepEffect by creating a promise that resolves after a delay.
     *
     * @param Effect $node The effect node to handle, which must be a SleepEffect.
     * @param callable $next The next step in the execution chain (unused here).
     * @param Context $ctx The current execution context.
     * @return PromiseInterface<null> A promise that resolves with null after the specified duration.
     */
    public function handle(Effect $node, ContinuationStack $stack, Context $ctx): PromiseInterface {
        /** @var SleepEffect $node */
        $deferred = new Deferred();

        // Convert milliseconds from the effect to seconds for the timer.
        $durationInSeconds = $node->milliseconds / 1000.0;

        // Add a timer to the global event loop. When it fires, resolve the promise.
        Loop::addTimer($durationInSeconds, function () use ($deferred) {
            $deferred->resolve(null);
        });

        // Return the promise immediately. The runtime will wait for it to resolve.
        return $deferred->promise();
    }
}
