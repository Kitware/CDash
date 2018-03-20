<?php
namespace CDash\Collection;

class CollectionCollection extends Collection
{
    /**
     * @param CollectionInterface $collection
     * @return $this
     */
    public function add(CollectionInterface $collection)
    {
        $this->addItem($collection, get_class($collection));
        return $this;
    }
}
