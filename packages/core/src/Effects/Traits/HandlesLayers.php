<?php

namespace EffectPHP\Core\Effects\Traits;

use EffectPHP\Core\Contracts\Effect;
use EffectPHP\Core\Effects\Extras\ProvideContextEffect;
use EffectPHP\Core\Layer\Context;
use EffectPHP\Core\Layer\Layer;

trait HandlesLayers {
    public function providedWith(Context $context): ProvideContextEffect {
        return new ProvideContextEffect($this, $context);
    }

    /**
     * @template RLayer of \EffectPHP\Core\Layer\Layer
     * @template R2
     * @template ELayer of \EffectPHP\Core\Layer\Layer
     * @psalm-return Effect<RLayer&R2, ELayer|\Throwable, mixed>
     */
    public function providedByLayer(Layer $layer): Effect {
        return $layer->build()->flatMap(fn($ctx) => $this->providedWith($ctx));
    }
}