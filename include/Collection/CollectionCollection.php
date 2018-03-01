<?php
namespace CDash\Collection;

class CollectionCollection extends Collection
{
    public function add(CollectionInterface $collection)
    {
        $this->addItem($collection, get_class($collection));
    }
}
