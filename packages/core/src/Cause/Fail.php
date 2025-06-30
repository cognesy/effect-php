<?php
declare(strict_types=1);

namespace EffectPHP\Core\Cause;

use Throwable;

/**
 * @template E of Throwable
 * @extends Cause<E>
 */
final readonly class Fail extends Cause
{
    public function __construct(public Throwable $error) {}

    /**
     * @psalm-return self<Throwable>
     */
    public function map(callable $mapper): self {
        return new Fail($mapper($this->error));
    }

    public function toException(): Throwable {
        return $this->error;
    }

    public function prettyPrint(): string {
        return "💥 Failure: {$this->error->getMessage()}\n" .
            "   at {$this->error->getFile()}:{$this->error->getLine()}";
    }

    public function contains(string $errorType): bool {
        return $this->error instanceof $errorType;
    }
}
