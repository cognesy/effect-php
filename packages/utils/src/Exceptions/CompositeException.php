<?php declare(strict_types=1);

namespace EffectPHP\Utils\Exceptions;

use RuntimeException;
use Throwable;

class CompositeException extends RuntimeException
{
    /** @param non-empty-list<Throwable> $errors */
    public function __construct(
        private array $errors,
    ) {
        $message = "Multiple exceptions occurred:\n"
            . implode("\n", array_map(fn($e) => $e->getMessage(), $errors));
        parent::__construct($message, 0, $errors[0] ?? null);
    }

    public static function of(Throwable ...$errors): self {
        return new self($errors);
    }

    /** @return array<Throwable> */
    public function errors(): array {
        return $this->errors;
    }
}