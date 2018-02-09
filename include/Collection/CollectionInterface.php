<?php
namespace CDash\Collection;

interface CollectionInterface extends \Iterator, \Countable
{
    /**
     * Add an item to the collection.
     * @param $item
     * @return mixed
     */
    public function addItem($item, $name = null);

    /**
     * Returns true if the collection has items, and false if not.
     * @return boolean
     */
    public function hasItems();
}
