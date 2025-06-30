<?php
declare(strict_types=1);

namespace EffectPHP\Core\Layer;

use EffectPHP\Core\Option;
use EffectPHP\Core\Exceptions\ServiceNotFoundException;

/**
 * Immutable, type-safe service container
 *
 * @template R Service union type
 */
final readonly class Context
{
    private function __construct(private array $services = []) {}

    public static function empty(): self {
        return new self();
    }

    /**
     * @template T
     * @param class-string<T> $tag
     * @param T $service
     * @return Context<R&T>
     */
    public function withService(string $tag, object $service): self {
        return new self([...$this->services, $tag => $service]);
    }

    /**
     * @template T
     * @param class-string<T> $tag
     * @return T
     * @throws ServiceNotFoundException
     */
    public function getService(string $tag): object {
        return $this->services[$tag] ?? throw new ServiceNotFoundException($tag);
    }

    /**
     * @param class-string $tag
     */
    public function hasService(string $tag): bool {
        return isset($this->services[$tag]);
    }

    /**
     * Merge contexts with conflict resolution
     *
     * @template R2
     * @param Context<R2> $other
     * @return Context<R&R2>
     */
    public function mergeWith(Context $other): self {
        return new self([...$this->services, ...$other->services]);
    }

    /**
     * Natural language service access
     *
     * @template T
     * @param class-string<T> $tag
     * @return Option<T>
     */
    public function findService(string $tag): Option {
        return isset($this->services[$tag])
            ? Option::some($this->services[$tag])
            : Option::none();
    }
}