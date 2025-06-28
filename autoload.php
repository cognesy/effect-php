<?php

declare(strict_types=1);

/**
 * Simple autoloader for EffectPHP monorepo
 */
spl_autoload_register(function (string $class): void {
    // Define namespace to directory mappings
    $namespaces = [
        'EffectPHP\\Core\\' => __DIR__ . '/packages/core/src/',
        'EffectPHP\\Schema\\' => __DIR__ . '/packages/schema/src/',
    ];
    
    // Check each namespace
    foreach ($namespaces as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) === 0) {
            // Get the relative class name
            $relativeClass = substr($class, $len);
            
            // Replace namespace separators with directory separators, append .php
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            
            // If the file exists, require it
            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    }
});