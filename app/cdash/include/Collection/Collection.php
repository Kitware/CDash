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

abstract class Collection implements CollectionInterface
{
    /** @var int $position */
    protected $position;
    /** @var array $collection */
    protected $collection;
    /** @var array $keys */
    protected $keys;

    /**
     * Collection constructor.
     * @param array $collection
     */
    public function __construct($collection = [])
    {
        $this->position = 0;
        $this->collection = $collection;
        $this->keys = array_keys($collection);
    }


    /**
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     * @since 5.0.0
     */
    public function current()
    {
        if ($this->valid()) {
            return $this->collection[$this->key()];
        }
        return null;
    }

    /**
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function next()
    {
        ++$this->position;
    }

    /**
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     * @since 5.0.0
     */
    public function key()
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
    public function valid()
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
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function rewind()
    {
        $this->position = 0;
    }

    /**
     * @param $item
     * @param null $name
     * @return $this|mixed
     * TODO: Watch this in a debugger when integration testing
     */
    public function addItem($item, $name = null)
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
     * Returns true if the collection has items, and false if not.
     * @return boolean
     */
    public function hasItems()
    {
        return count($this->collection) > 0;
    }

    /**
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     * @since 5.1.0
     */
    public function count()
    {
        return count($this->collection);
    }

    /**
     * @param $key
     * @return bool
     */
    public function has($key)
    {
        return in_array($key, array_keys($this->collection));
    }

    /**
     * @param $key
     * @return mixed
     */
    public function get($key)
    {
        if ($this->has($key)) {
            return $this->collection[$key];
        }
        return null;
    }

    /**
     * @param $key
     * @return mixed
     */
    public function remove($key)
    {
        $item = null;
        if ($this->has($key)) {
            $item = $this->collection[$key];
            unset($this->collection[$key], $this->keys[array_search($key, $this->keys)]);
        }
        return $item;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return array_values($this->collection);
    }

    /**
     * @param int $size
     * @return Collection
     */
    public function first($size = 1)
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
