<?php declare(strict_types=1);

namespace EffectPHP\Schema\Schema;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Eff;
use EffectPHP\Schema\AST\LiteralType;
use EffectPHP\Schema\Parse\ParseError;
use EffectPHP\Schema\Parse\TypeIssue;

/**
 * Literal schema implementation using core Effects
 *
 * @template T
 * @extends BaseSchema<T, mixed>
 */
final class LiteralSchema extends BaseSchema
{
    private mixed $value;

    public function __construct(mixed $value, array $annotations = []) {
        $this->value = $value;
        parent::__construct(new LiteralType($value, $annotations));
    }

    /**
     * @param mixed $input
     * @return Effect<never, \Throwable, mixed>
     */
    public function decode(mixed $input): Effect {
        if ($input !== $this->value) {
            return Eff::fail(new ParseError([
                new TypeIssue($this->value, $input, [], "Expected literal value: " . json_encode($this->value)),
            ]));
        }

        return Eff::succeed($input);
    }

    /**
     * @param mixed $input
     * @return Effect<never, \Throwable, mixed>
     */
    public function encode(mixed $input): Effect {
        if ($input !== $this->value) {
            return Eff::fail(new ParseError([
                new TypeIssue($this->value, $input, [], "Expected literal value for encoding: " . json_encode($this->value)),
            ]));
        }

        return Eff::succeed($input);
    }
}
