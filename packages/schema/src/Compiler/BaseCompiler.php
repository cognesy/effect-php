<?php

declare(strict_types=1);

namespace EffectPHP\Schema\Compiler;

use EffectPHP\Schema\AST\ASTNodeInterface;

abstract class BaseCompiler implements CompilerInterface
{
    protected array $cache = [];

    public function compile(ASTNodeInterface $ast): mixed
    {
        $key = $this->getCacheKey($ast);

        if (!isset($this->cache[$key])) {
            $this->cache[$key] = $this->doCompile($ast);
        }

        return $this->cache[$key];
    }

    abstract protected function doCompile(ASTNodeInterface $ast): mixed;

    abstract public function getTarget(): string;

    protected function getCacheKey(ASTNodeInterface $ast): string
    {
        return spl_object_hash($ast);
    }
}
