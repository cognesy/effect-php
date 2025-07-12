<?php declare(strict_types=1);

namespace EffectPHP\Schema\Schema;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Eff;
use EffectPHP\Schema\Contracts\SchemaInterface;

/**
 * Optional schema wrapper using core Effects
 *
 * @template A
 * @extends BaseSchema<A|null, mixed>
 */
final class OptionalSchema extends BaseSchema
{
    private SchemaInterface $inner;

    public function __construct(SchemaInterface $inner, array $annotations = []) {
        $this->inner = $inner;
        parent::__construct($inner->getAST()->withAnnotations(array_merge(['optional' => true], $annotations)));
    }

    /**
     * @param mixed $input
     * @return Effect<never, \Throwable, mixed>
     */
    public function decode(mixed $input): Effect {
        if ($input === null) {
            return Eff::succeed(null);
        }

        return $this->inner->decode($input);
    }

    /**
     * @param mixed $input
     * @return Effect<never, \Throwable, mixed>
     */
    public function encode(mixed $input): Effect {
        if ($input === null) {
            return Eff::succeed(null);
        }

        return $this->inner->encode($input);
    }
}
