<?php

declare(strict_types=1);

namespace EffectPHP\Core\Effects;

use EffectPHP\Core\Contracts\Effect;

/**
 * Effect that never completes - represents permanent suspension
 * 
 * This effect is a singleton that represents an infinite suspension without consuming resources.
 * It should be used for effects that intentionally never produce a value.
 * 
 * RUNTIME HANDLING REQUIREMENTS:
 * 
 * 1. RESOURCE EFFICIENCY: Runtimes MUST recognize NeverEffect and handle it as a permanent 
 *    suspension without active polling, timeouts, or resource consumption.
 * 
 * 2. CONCURRENCY SEMANTICS: In concurrent operations:
 *    - race(never, effect) -> effect completes (never loses)
 *    - parallel(never, effect) -> never completes (waits forever)
 *    - timeoutAfter(never, duration) -> timeout fires after duration
 * 
 * 3. COMPOSITION BEHAVIOR: When chained with other effects:
 *    - never.flatMap(f) -> never (transformation never executes)
 *    - never.map(f) -> never (mapping never executes) 
 *    - never.catchError(f) -> never (no error to catch)
 * 
 * 4. IMPLEMENTATION STRATEGY: Runtimes should:
 *    - Detect NeverEffect instances early in execution
 *    - Mark the fiber/execution context as "permanently suspended"
 *    - Only resume if explicitly interrupted or cancelled
 *    - Use minimal memory (singleton pattern recommended)
 * 
 * 5. CANCELLATION: NeverEffect should be cancellable like any other effect,
 *    allowing cleanup and resource deallocation when containing scopes end.
 * 
 * @template R
 * @template E of \Throwable  
 * @template A
 * @extends BaseEffect<R, E, A>
 */
final class NeverEffect extends BaseEffect
{
    private static ?self $instance = null;
    
    private function __construct() {}
    
    /**
     * Get singleton instance to minimize memory usage
     */
    public static function instance(): self
    {
        return self::$instance ??= new self();
    }
    
    /**
     * Never effect composed with anything is still never
     *
     * @psalm-return self<R, E, A>
     */
    public function flatMap(callable $chain): self
    {
        return $this;
    }
    
    /**
     * Never effect mapped is still never
     *
     * @psalm-return self<R, E, A>
     */
    public function map(callable $mapper): self
    {
        return $this;
    }
    
    /**
     * Never effect has no errors to catch
     *
     * @psalm-return self<R, E, A>
     */
    public function catchError(string|callable $errorType, callable $handler): self
    {
        return $this;
    }
    
    /**
     * Never effect with fallback is still never (never doesn't fail)
     *
     * @psalm-return self<R, E, A>
     */
    public function orElse(Effect $fallback): self
    {
        return $this;
    }
}