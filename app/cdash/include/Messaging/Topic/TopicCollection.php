<?php

namespace CDash\Messaging\Topic;

use CDash\Collection\Collection;

class TopicCollection extends Collection
{
    public function add(TopicInterface $item): void
    {
        parent::addItem($item, $item->getTopicName());
    }
}
