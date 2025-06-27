<?php

declare(strict_types=1);

namespace EffectPHP\Core\Runtimes\Default\Handlers;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Contracts\Runtime;
use EffectPHP\Core\Effects\ScopeEffect;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Runtimes\Default\EffectHandler;
use EffectPHP\Core\Scope;

/**
 * Handler for ScopeEffect - manages resource scopes with automatic cleanup
 */
final class ScopeEffectHandler implements EffectHandler
{
    public function canHandle(Effect $effect): bool
    {
        return $effect instanceof ScopeEffect;
    }

    public function handle(Effect $effect, array &$stack, Context $context, Runtime $runtime): Effect
    {
        /** @var ScopeEffect $effect */
        
        // Create new scope instance
        $scope = new Scope();
        
        try {
            // Execute the scoped computation with the scope - simplified approach
            $result = $runtime->tryRun(($effect->scoped)($scope), $context);
            
            // Always close the scope after execution
            $runtime->unsafeRun($scope->close());
            
            // Return the original result
            return $result;
            
        } catch (\Throwable $e) {
            // Ensure cleanup happens even on exception
            try {
                $runtime->unsafeRun($scope->close());
            } catch (\Throwable $cleanupError) {
                // Log cleanup error but don't mask original exception
                error_log("Scope cleanup failed: " . $cleanupError->getMessage());
            }
            
            throw $e;
        }
    }
}