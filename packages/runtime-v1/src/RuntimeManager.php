<?php

declare(strict_types=1);

namespace EffectPHP\RuntimeV1;

use EffectPHP\Core\Layer\Context;

/**
 * Runtime management and factory for different execution environments
 *
 * Provides centralized access to runtime instances and allows registration
 * of custom runtimes for different execution contexts (sync, async, etc.)
 */
final class RuntimeManager
{
    private static ?Runtime $defaultRuntime = null;
    private static array $runtimes = [];

    /**
     * Get the current default runtime
     */
    public static function default(): Runtime {
        return self::$defaultRuntime ??= new DefaultRuntime();
    }

    /**
     * Set a custom default runtime
     */
    public static function setDefault(Runtime $runtime): void {
        self::$defaultRuntime = $runtime;
    }

    /**
     * Register a named runtime for specific use cases
     */
    public static function register(string $name, Runtime $runtime): void {
        self::$runtimes[$name] = $runtime;
    }

    /**
     * Get a registered runtime by name
     */
    public static function get(string $name): ?Runtime {
        return self::$runtimes[$name] ?? null;
    }

    /**
     * Create a new default runtime with custom context
     */
    public static function createWith(Context $context): Runtime {
        return new DefaultRuntime($context);
    }

    /**
     * Create managed runtime from layer (EffectTS ManagedRuntime pattern)
     *
     * Equivalent to EffectTS ManagedRuntime.make(layer)
     * Creates a runtime with services provided by the layer
     */
    public static function make(mixed $layer): Runtime {
        // TODO: Implement layer -> context conversion when Layer system is complete
        // For now, create runtime with empty context
        // In future: $context = $layer->build(); return new DefaultRuntime($context);
        return new DefaultRuntime();
    }

    /**
     * Auto-detect runtime based on environment
     *
     * This can be extended to detect Swoole, ReactPHP, AmpPHP, etc.
     * and return appropriate runtime implementations
     */
    public static function autoDetect(): Runtime {
        // Future: Add detection for async environments
        // if (extension_loaded('swoole')) return new SwooleRuntime();
        // if (class_exists('React\EventLoop\Loop')) return new ReactPHPRuntime();
        // if (class_exists('Amp\Loop')) return new AmpPHPRuntime();

        return self::default();
    }

    /**
     * Reset to clean state (useful for testing)
     */
    public static function reset(): void {
        self::$defaultRuntime = null;
        self::$runtimes = [];
    }
}