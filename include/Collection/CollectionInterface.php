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

    /**
     * @param $key
     * @return bool
     */
    public function has($key);

    /**
     * @param $key
     * @return mixed
     */
    public function get($key);

      /**
       * @param $key
       * @return mixed
       */
      public function remove($key);

      /**
       * @return array
       */
      public function toArray();
}
