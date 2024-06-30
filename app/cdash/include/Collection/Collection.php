<?php
/**
 * =========================================================================
 *   Program:   CDash - Cross-Platform Dashboard System
 *   Module:    $Id$
 *   Language:  PHP
 *   Date:      $Date$
 *   Version:   $Revision$
 *   Copyright (c) Kitware, Inc. All rights reserved.
 *   See LICENSE or http://www.cdash.org/licensing/ for details.
 *   This software is distributed WITHOUT ANY WARRANTY; without even
 *   the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
 *   PURPOSE. See the above copyright notices for more information.
 * =========================================================================
 */
namespace CDash\Collection;

use Countable;
use Iterator;

abstract class Collection implements Iterator, Countable
{
    protected int $position = 0;
    /** @var array<mixed> $collection */
    protected array $collection = [];
    /** @var array<mixed> $keys */
    protected array $keys = [];

    public function __construct()
    {
    }

    /**
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @since 5.0.0
     */
    public function current(): mixed
    {
        if ($this->valid()) {
            return $this->collection[$this->key()];
        }
        return null;
    }

    /**
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @since 5.0.0
     */
    public function next(): void
    {
        ++$this->position;
    }

    /**
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     * @since 5.0.0
     */
    public function key(): mixed
    {
        if (isset($this->keys[$this->position])) {
            return $this->keys[$this->position];
        }
        return null;
    }

    /**
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     * @since 5.0.0
     */
    public function valid(): bool
    {
        $key = $this->key();
        // a call to key may result in null, e.g. !isset. To prevent endless loop in the event
        // that collection was set with a key equal to an empty string, we must check for the null
        // type here.
        if (is_null($key)) {
            return false;
        }

        return isset($this->collection[$key]);
    }

    /**
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @since 5.0.0
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    public function addItem($item, $name = null): self
    {
        $ptr = count($this->collection);
        $key = is_null($name) ? $ptr : $name;

        if (!in_array($key, $this->keys) && !in_array($key, array_keys($this->collection))) {
            $this->keys[$ptr] = $key;
        }
        $this->collection[$key] = $item;
        return $this;
    }

    /**
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int<0,max> The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     * @since 5.1.0
     */
    public function count(): int
    {
        return count($this->collection);
    }

    public function has(mixed $key): bool
    {
        return in_array($key, array_keys($this->collection));
    }

    public function get(mixed $key): mixed
    {
        if ($this->has($key)) {
            return $this->collection[$key];
        }
        return null;
    }

    public function toArray(): array
    {
        return array_values($this->collection);
    }

    public function first(int $size = 1): self
    {
        $self = new static;
        $collectionSize = $this->count();
        $size = $size <= $collectionSize ? $size : $collectionSize;
        for ($i = 0; $i < $size; $i++) {
            $key = $this->keys[$i];
            $item = $this->collection[$key];
            $self->addItem($item, $key);
        }
        return $self;
    }
}
