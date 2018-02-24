<?php
namespace CDash\Collection;

class CollectionCollection extends Collection
{
    public function add(CollectionInterface $collection)
    {
        $name = strtolower(substr(get_class($collection), 0, -strlen('Collection')));
        $this->addItem($collection, $name);
    }
}
