<?php

declare(strict_types=1);

namespace EffectPHP\Core\Layer;

// Ensure required classes are loaded
use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Eff;

/**
 * Declarative service construction with superior composition
 *
 * @template RIn Input requirements
 * @template E of \Throwable Construction errors
 * @template ROut Output services
 */
final readonly class Layer
{
    private function __construct(private Effect $builder) {}

    /**
     * Create layer from effect that builds service
     *
     * @template RIn
     * @template E of \Throwable
     * @template T
     *
     * @param Effect<RIn, E, T> $effect
     * @param class-string<T> $tag
     *
     * @psalm-return self<mixed, \Throwable, mixed>
     */
    public static function fromEffect(Effect $effect, string $tag): self
    {
        return new self(
            $effect->map(fn($service) => Context::empty()->withService($tag, $service))
        );
    }

    /**
     * Create layer from factory
     *
     * @template T
     * @param callable(): T $factory
     * @param class-string<T> $tag
     * @return Layer<never, \Throwable, Context<T>>
     */
    public static function fromFactory(callable $factory, string $tag): self
    {
        return self::fromEffect(Eff::sync($factory), $tag);
    }

    /**
     * Create layer from value with zero cost
     *
     * @template T
     *
     * @param T $service
     * @param class-string<T> $tag
     *
     * @psalm-return self<mixed, \Throwable, Context<T>>
     */
    public static function fromValue(object $service, string $tag): self
    {
        return self::fromEffect(Eff::succeed($service), $tag);
    }

    public function build(): Effect
    {
        return $this->builder;
    }

    /**
     * Combine layers in parallel (for independent layers)
     *
     * @template RIn2
     * @template E2 of \Throwable
     * @template ROut2
     *
     * @param Layer<RIn2, E2, ROut2> $other
     *
     * @psalm-return self<mixed, \Throwable, mixed>
     */
    public function combineWith(Layer $other): self
    {
        return new self(
            Eff::allInParallel([$this->build(), $other->build()])
                ->map(fn($contexts) => $contexts[0]->mergeWith($contexts[1]))
        );
    }

    /**
     * Combine layers sequentially (for dependent layers)
     *
     * The other layer will have access to services from this layer
     *
     * @template RIn2
     * @template E2 of \Throwable
     * @template ROut2
     *
     * @param Layer<RIn2, E2, ROut2> $other
     *
     * @psalm-return self<mixed, \Throwable, mixed>
     */
    public function andThen(Layer $other): self
    {
        return new self(
            $this->build()->flatMap(fn($firstContext) =>
                $other->build()->providedWith($firstContext)
                    ->map(fn($secondContext) => $firstContext->mergeWith($secondContext))
            )
        );
    }

    /**
     * Provide layer to effect
     *
     * @template R
     * @template E2 of \Throwable
     * @template A
     *
     * @param \EffectPHP\Core\Contracts\Effect<R&ROut, E2, A> $effect
     *
     * @psalm-return Effect<RIn&R2, E|\Throwable, mixed>
     */
    public function provideTo(Effect $effect): Effect
    {
        return $this->build()->flatMap(fn($context) => $effect->providedWith($context));
    }
}