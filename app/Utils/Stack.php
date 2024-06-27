<?php

namespace App\Utils;

class Stack
{
    /**
     * @var array<mixed>
     */
    private array $stack = [];

    public function size(): int
    {
        return count($this->stack);
    }

    public function push(mixed $e): self
    {
        $this->stack[] = $e;
        return $this;
    }

    public function pop(): self
    {
        array_pop($this->stack);
        return $this;
    }

    public function top(): mixed
    {
        return $this->stack[count($this->stack) - 1];
    }

    public function isEmpty(): bool
    {
        return count($this->stack) === 0;
    }

    public function at(int $index): mixed
    {
        if ($index < 0 || $index >= count($this->stack)) {
            return false;
        }
        return $this->stack[$index];
    }
}
