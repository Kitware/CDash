<?php

namespace App\Utils;

use InvalidArgumentException;

/**
 * @template T
 */
class Stack
{
    /**
     * @var array<T>
     */
    private array $stack = [];

    public function size(): int
    {
        return count($this->stack);
    }

    /**
     * @param T $e
     *
     * @return self<T>
     */
    public function push(mixed $e): self
    {
        $this->stack[] = $e;
        return $this;
    }

    /**
     * @return self<T>
     */
    public function pop(): self
    {
        array_pop($this->stack);
        return $this;
    }

    /**
     * @return T
     */
    public function top(): mixed
    {
        return $this->stack[count($this->stack) - 1];
    }

    public function isEmpty(): bool
    {
        return count($this->stack) === 0;
    }

    /**
     * @return T
     */
    public function at(int $index): mixed
    {
        if ($index < 0 || $index >= count($this->stack)) {
            throw new InvalidArgumentException("Invalid stack index: $index");
        }
        return $this->stack[$index];
    }
}
