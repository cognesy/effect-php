<?php declare(strict_types=1);

namespace EffectPHP\Core;

use EffectPHP\Utils\ContinuationStack;

final readonly class RuntimeState
{
    public function __construct(
        public Context $context,
        public ContinuationStack $stack,
        public Scope $scope,
        public mixed $value = null,
    ) {}

    public static function empty(): self {
        return new self(Context::empty(), new ContinuationStack(), new Scope());
    }

    public function with(
        Context $context = null,
        ContinuationStack $stack = null,
        Scope $scope = null,
        mixed $value = null,
    ): self {
        return new self(
            $context ?? $this->context,
            $stack ?? $this->stack,
            $scope ?? $this->scope,
            $value ?? $this->value,
        );
    }

    public function withContext(Context $context): self {
        return new self($context, $this->stack, $this->scope, $this->value);
    }

    public function withStack(ContinuationStack $stack): self {
        return new self($this->context, $stack, $this->scope, $this->value);
    }

    public function withScope(Scope $scope): self {
        return new self($this->context, $this->stack, $scope, $this->value);
    }

    public function withValue(mixed $value): self {
        return new self($this->context, $this->stack, $this->scope, $value);
    }
}