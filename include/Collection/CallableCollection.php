<?php

namespace CDash\Collection;

class CallableCollection extends Collection
{
    public function add(callable $item)
    {
        // what if the callable is anonymous?
        $key = null;
        if (is_array($item)) {
            list($object, $method) = $item;
            $className = get_class($object);
            $key = "{$className}::{$method}";
        } elseif (is_string($item)) {
            $key = $item;
        }

        $this->addItem($item, $key);
    }
}
