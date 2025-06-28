
## Phase 5: Data Structures & Integration (Months 9-12)
### Production-Ready Collections & Ecosystem Integration

### Module 6: High-Performance Data Structures

#### 6.1 Immutable Collections
```php
<?php declare(strict_types=1);

namespace EffectPHP\Data;

/**
 * High-performance immutable array implementation.
 * 
 * @template A
 * @psalm-immutable
 */
final readonly class Chunk
{
    /**
     * @param array<A> $data
     */
    private function __construct(
        private array $data,
        private int $offset = 0,
        private ?int $length = null
    ) {}
    
    /**
     * @return Chunk<never>
     */
    public static function empty(): self
    {
        return new self([]);
    }
    
    /**
     * @template A
     * @param array<A> $data
     * @return Chunk<A>
     */
    public static function from(array $data): self
    {
        return new self(\array_values($data));
    }
    
    /**
     * @template A
     * @param A ...$items
     * @return Chunk<A>
     */
    public static function of(mixed ...$items): self
    {
        return new self($items);
    }
    
    /**
     * @param int $start
     * @param int $count
     * @return Chunk<int>
     */
    public static function range(int $start, int $count): self
    {
        return new self(\range($start, $start + $count - 1));
    }
    
    /**
     * @template B
     * @param callable(A): B $f
     * @return Chunk<B>
     */
    public function map(callable $f): self
    {
        return new self(\array_map($f, $this->toArray()));
    }
    
    /**
     * @param callable(A): bool $predicate
     * @return Chunk<A>
     */
    public function filter(callable $predicate): self
    {
        return new self(\array_values(\array_filter($this->toArray(), $predicate)));
    }
    
    /**
     * @template B
     * @param callable(A): Chunk<B> $f
     * @return Chunk<B>
     */
    public function flatMap(callable $f): self
    {
        $result = [];
        foreach ($this->toArray() as $item) {
            $chunk = $f($item);
            $result = \array_merge($result, $chunk->toArray());
        }
        return new self($result);
    }
    
    /**
     * @template B
     * @param B $initial
     * @param callable(B, A): B $f
     * @return B
     */
    public function fold(mixed $initial, callable $f): mixed
    {
        return \array_reduce($this->toArray(), $f, $initial);
    }
    
    /**
     * @param int $n
     * @return Chunk<A>
     */
    public function take(int $n): self
    {
        if ($n <= 0) {
            return self::empty();
        }
        
        return new self(
            $this->data,
            $this->offset,
            \min($n, $this->size())
        );
    }
    
    /**
     * @param int $n
     * @return Chunk<A>
     */
    public function drop(int $n): self
    {
        if ($n <= 0) {
            return $this;
        }
        
        $newOffset = $this->offset + $n;
        $remainingLength = $this->size() - $n;
        
        if ($remainingLength <= 0) {
            return self::empty();
        }
        
        return new self($this->data, $newOffset, $remainingLength);
    }
    
    /**
     * @param Chunk<A> $other
     * @return Chunk<A>
     */
    public function append(self $other): self
    {
        return new self(\array_merge($this->toArray(), $other->toArray()));
    }
    
    /**
     * @param Chunk<A> $other
     * @return Chunk<A>
     */
    public function prepend(self $other): self
    {
        return new self(\array_merge($other->toArray(), $this->toArray()));
    }
    
    /**
     * @param int $index
     * @return Option<A>
     */
    public function get(int $index): Option
    {
        $array = $this->toArray();
        return isset($array[$index]) ? Option::some($array[$index]) : Option::none();
    }
    
    /**
     * @return Option<A>
     */
    public function head(): Option
    {
        return $this->get(0);
    }
    
    /**
     * @return Chunk<A>
     */
    public function tail(): self
    {
        return $this->drop(1);
    }
    
    public function size(): int
    {
        return $this->length ?? (\count($this->data) - $this->offset);
    }
    
    public function isEmpty(): bool
    {
        return $this->size() === 0;
    }
    
    /**
     * @return array<A>
     */
    public function toArray(): array
    {
        if ($this->offset === 0 && $this->length === null) {
            return $this->data;
        }
        
        return \array_slice(
            $this->data,
            $this->offset,
            $this->length
        );
    }
    
    /**
     * @return \Generator<A>
     */
    public function getIterator(): \Generator
    {
        foreach ($this->toArray() as $item) {
            yield $item;
        }
    }
}

/**
 * Immutable hash map implementation.
 * 
 * @template K
 * @template V
 * @psalm-immutable
 */
final readonly class HashMap
{
    private const BUCKET_SIZE = 16;
    
    /**
     * @param array<string, array{K, V}> $buckets
     */
    private function __construct(
        private array $buckets = [],
        private int $size = 0
    ) {}
    
    /**
     * @return HashMap<never, never>
     */
    public static function empty(): self
    {
        return new self();
    }
    
    /**
     * @template K, V
     * @param array<K, V> $data
     * @return HashMap<K, V>
     */
    public static function from(array $data): self
    {
        $map = self::empty();
        foreach ($data as $key => $value) {
            $map = $map->put($key, $value);
        }
        return $map;
    }
    
    /**
     * @param K $key
     * @return Option<V>
     */
    public function get(mixed $key): Option
    {
        $hash = $this->hash($key);
        $bucket = $this->buckets[$hash] ?? [];
        
        foreach ($bucket as [$k, $v]) {
            if ($this->equals($key, $k)) {
                return Option::some($v);
            }
        }
        
        return Option::none();
    }
    
    /**
     * @param K $key
     * @param V $value
     * @return HashMap<K, V>
     */
    public function put(mixed $key, mixed $value): self
    {
        $hash = $this->hash($key);
        $buckets = $this->buckets;
        $bucket = $buckets[$hash] ?? [];
        $newSize = $this->size;
        
        // Check if key already exists
        $found = false;
        foreach ($bucket as $index => [$k, $v]) {
            if ($this->equals($key, $k)) {
                $bucket[$index] = [$key, $value];
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $bucket[] = [$key, $value];
            $newSize++;
        }
        
        $buckets[$hash] = $bucket;
        
        return new self($buckets, $newSize);
    }
    
    /**
     * @param K $key
     * @return HashMap<K, V>
     */
    public function remove(mixed $key): self
    {
        $hash = $this->hash($key);
        $buckets = $this->buckets;
        $bucket = $buckets[$hash] ?? [];
        $newSize = $this->size;
        
        foreach ($bucket as $index => [$k, $v]) {
            if ($this->equals($key, $k)) {
                unset($bucket[$index]);
                $buckets[$hash] = \array_values($bucket);
                $newSize--;
                break;
            }
        }
        
        return new self($buckets, $newSize);
    }
    
    /**
     * @param K $key
     * @return bool
     */
    public function has(mixed $key): bool
    {
        return !$this->get($key)->isEmpty();
    }
    
    /**
     * @return Chunk<K>
     */
    public function keys(): Chunk
    {
        $keys = [];
        foreach ($this->buckets as $bucket) {
            foreach ($bucket as [$k, $v]) {
                $keys[] = $k;
            }
        }
        return Chunk::from($keys);
    }
    
    /**
     * @return Chunk<V>
     */
    public function values(): Chunk
    {
        $values = [];
        foreach ($this->buckets as $bucket) {
            foreach ($bucket as [$k, $v]) {
                $values[] = $v;
            }
        }
        return Chunk::from($values);
    }
    
    /**
     * @template K2, V2
     * @param callable(K, V): array{K2, V2} $f
     * @return HashMap<K2, V2>
     */
    public function map(callable $f): self
    {
        $result = self::empty();
        
        foreach ($this->buckets as $bucket) {
            foreach ($bucket as [$k, $v]) {
                [$newK, $newV] = $f($k, $v);
                $result = $result->put($newK, $newV);
            }
        }
        
        return $result;
    }
    
    public function size(): int
    {
        return $this->size;
    }
    
    public function isEmpty(): bool
    {
        return $this->size === 0;
    }
    
    private function hash(mixed $key): string
    {
        return match(gettype($key)) {
            'integer', 'double' => (string)$key,
            'string' => $key,
            'boolean' => $key ? '1' : '0',
            'object' => \spl_object_hash($key),
            default => \md5(\serialize($key))
        };
    }
    
    private function equals(mixed $a, mixed $b): bool
    {
        if ($a instanceof Equal && $b instanceof Equal) {
            return $a->equals($b);
        }
        
        return $a === $b;
    }
}
```
