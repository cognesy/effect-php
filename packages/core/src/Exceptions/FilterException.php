<?php

namespace EffectPHP\Core\Exceptions;

use Exception;

/**
 * Exception thrown when FilterEffect predicate fails
 */
final class FilterException extends Exception
{
    public function __construct(
        string $message = "Filter predicate failed",
        public readonly mixed $rejectedValue = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function withValue(mixed $value, ?string $message = null): self
    {
        return new self(
            $message ?? "Filter rejected value: " . self::formatValue($value),
            $value
        );
    }

    private static function formatValue(mixed $value): string
    {
        return match(true) {
            is_scalar($value) => (string) $value,
            is_array($value) => 'array(' . count($value) . ')',
            is_object($value) => get_class($value),
            is_resource($value) => 'resource',
            default => gettype($value)
        };
    }
}