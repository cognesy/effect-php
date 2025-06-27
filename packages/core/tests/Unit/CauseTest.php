<?php

declare(strict_types=1);

use EffectPHP\Core\Cause\Cause;
use EffectPHP\Core\Cause\Fail;
use EffectPHP\Core\Cause\Interrupt;
use EffectPHP\Core\Cause\Parallel;
use EffectPHP\Core\Cause\Sequential;
use EffectPHP\Core\Exceptions\InterruptedException;
use EffectPHP\Core\Exceptions\CompositeException;

describe('Cause', function () {
    
    describe('Fail', function () {
        it('creates fail cause from exception', function () {
            $exception = new \RuntimeException('Test error');
            $cause = Cause::fail($exception);
            
            expect($cause)->toBeInstanceOf(Fail::class);
            expect($cause->toException())->toBe($exception);
            expect($cause->contains(\RuntimeException::class))->toBeTrue();
            expect($cause->contains(\LogicException::class))->toBeFalse();
        });
        
        it('produces readable error message', function () {
            $exception = new \RuntimeException('Test error');
            $cause = Cause::fail($exception);
            $pretty = $cause->prettyPrint();
            
            expect($pretty)->toContain('💥 Failure: Test error')
                ->and($pretty)->toContain('at ');
        });
        
        it('maps error with transformation', function () {
            $original = new \RuntimeException('Original');
            $cause = Cause::fail($original);
            
            $mapped = $cause->map(fn($e) => new \LogicException('Mapped: ' . $e->getMessage()));
            
            expect($mapped->toException())->toBeInstanceOf(\LogicException::class)
                ->and($mapped->toException()->getMessage())->toBe('Mapped: Original');
        });
    });
    
    describe('Interrupt', function () {
        it('creates interrupt cause', function () {
            $cause = Cause::interrupt();
            
            expect($cause)->toBeInstanceOf(Interrupt::class)
                ->and($cause->toException())->toBeInstanceOf(InterruptedException::class)
                ->and($cause->contains(InterruptedException::class))->toBeTrue();
        });
        
        it('produces readable interrupt message', function () {
            $cause = Cause::interrupt();
            $pretty = $cause->prettyPrint();
            
            expect($pretty)->toBe('🛑 Interrupted');
        });
        
        it('preserves interrupt through mapping', function () {
            $cause = Cause::interrupt();
            $mapped = $cause->map(fn($e) => new \LogicException('Should not be called'));
            
            expect($mapped)->toBeInstanceOf(Interrupt::class);
        });
    });
    
    describe('Parallel', function () {
        it('combines multiple causes in parallel', function () {
            $cause1 = Cause::fail(new \RuntimeException('Error 1'));
            $cause2 = Cause::fail(new \LogicException('Error 2'));
            
            $parallel = Cause::parallel([$cause1, $cause2]);
            
            expect($parallel)->toBeInstanceOf(Parallel::class);
            expect($parallel->contains(\RuntimeException::class))->toBeTrue();
            expect($parallel->contains(\LogicException::class))->toBeTrue();
            expect($parallel->contains(\InvalidArgumentException::class))->toBeFalse();
        });
        
        it('produces composite exception', function () {
            $cause1 = Cause::fail(new \RuntimeException('Error 1'));
            $cause2 = Cause::fail(new \LogicException('Error 2'));
            
            $parallel = Cause::parallel([$cause1, $cause2]);
            $exception = $parallel->toException();
            
            expect($exception)->toBeInstanceOf(CompositeException::class)
                ->and($exception->getMessage())->toContain('Parallel failures')
                ->and($exception->getMessage())->toContain('Error 1')
                ->and($exception->getMessage())->toContain('Error 2');
        });
        
        it('produces structured pretty print', function () {
            $cause1 = Cause::fail(new \RuntimeException('Error 1'));
            $cause2 = Cause::fail(new \LogicException('Error 2'));
            
            $parallel = Cause::parallel([$cause1, $cause2]);
            $pretty = $parallel->prettyPrint();
            
            expect($pretty)->toContain('🔀 Parallel Failures')
                ->and($pretty)->toContain('└─')
                ->and($pretty)->toContain('Error 1')
                ->and($pretty)->toContain('Error 2');
        });
        
        it('maps all contained causes', function () {
            $cause1 = Cause::fail(new \RuntimeException('Error 1'));
            $cause2 = Cause::fail(new \RuntimeException('Error 2'));
            
            $parallel = Cause::parallel([$cause1, $cause2]);
            $mapped = $parallel->map(fn($e) => new \LogicException('Mapped: ' . $e->getMessage()));
            
            expect($mapped)->toBeInstanceOf(Parallel::class);
            expect($mapped->contains(\LogicException::class))->toBeTrue();
            expect($mapped->contains(\RuntimeException::class))->toBeFalse();
        });
    });
    
    describe('Sequential', function () {
        it('combines multiple causes sequentially', function () {
            $cause1 = Cause::fail(new \RuntimeException('Error 1'));
            $cause2 = Cause::fail(new \LogicException('Error 2'));
            
            $sequential = Cause::sequential([$cause1, $cause2]);
            
            expect($sequential)->toBeInstanceOf(Sequential::class);
            expect($sequential->contains(\RuntimeException::class))->toBeTrue();
            expect($sequential->contains(\LogicException::class))->toBeTrue();
        });
        
        it('returns last exception as primary', function () {
            $cause1 = Cause::fail(new \RuntimeException('Error 1'));
            $cause2 = Cause::fail(new \LogicException('Error 2'));
            
            $sequential = Cause::sequential([$cause1, $cause2]);
            $exception = $sequential->toException();
            
            expect($exception)->toBeInstanceOf(\LogicException::class)
                ->and($exception->getMessage())->toBe('Error 2');
        });
        
        it('produces sequential pretty print', function () {
            $cause1 = Cause::fail(new \RuntimeException('Error 1'));
            $cause2 = Cause::fail(new \LogicException('Error 2'));
            
            $sequential = Cause::sequential([$cause1, $cause2]);
            $pretty = $sequential->prettyPrint();
            
            expect($pretty)->toContain('⏭️ Sequential Failures')
                ->and($pretty)->toContain('▶')
                ->and($pretty)->toContain('Error 1')
                ->and($pretty)->toContain('Error 2');
        });
    });
    
    describe('composition', function () {
        it('composes causes with and operator', function () {
            $cause1 = Cause::fail(new \RuntimeException('Error 1'));
            $cause2 = Cause::fail(new \LogicException('Error 2'));
            
            $composed = $cause1->and($cause2);
            
            expect($composed)->toBeInstanceOf(Parallel::class);
            expect($composed->contains(\RuntimeException::class))->toBeTrue();
            expect($composed->contains(\LogicException::class))->toBeTrue();
        });
        
        it('handles complex nested compositions', function () {
            $error1 = Cause::fail(new \RuntimeException('Error 1'));
            $error2 = Cause::fail(new \LogicException('Error 2'));
            $error3 = Cause::fail(new \InvalidArgumentException('Error 3'));
            
            $parallel = $error1->and($error2);
            $sequential = Cause::sequential([$parallel, $error3]);
            
            expect($sequential->contains(\RuntimeException::class))->toBeTrue()
                ->and($sequential->contains(\LogicException::class))->toBeTrue()
                ->and($sequential->contains(\InvalidArgumentException::class))->toBeTrue();
        });
    });
    
    describe('error type checking', function () {
        it('correctly identifies error types in nested structures', function () {
            $runtime = Cause::fail(new \RuntimeException('Runtime'));
            $logic = Cause::fail(new \LogicException('Logic'));
            $interrupt = Cause::interrupt();
            
            $parallel = Cause::parallel([$runtime, $logic]);
            $sequential = Cause::sequential([$parallel, $interrupt]);
            
            expect($sequential->contains(\RuntimeException::class))->toBeTrue()
                ->and($sequential->contains(\LogicException::class))->toBeTrue()
                ->and($sequential->contains(InterruptedException::class))->toBeTrue()
                ->and($sequential->contains(\InvalidArgumentException::class))->toBeFalse();
        });
    });
});