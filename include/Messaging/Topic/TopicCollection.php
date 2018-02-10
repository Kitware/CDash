<?php
namespace CDash\Messaging\Topic;

use CDash\Collection\Collection;

class TopicCollection extends Collection
{
    public function add(Topic $item)
    {
        parent::addItem($item);
    }
}
