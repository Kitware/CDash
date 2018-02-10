<?php
namespace CDash\archive\Messaging\Collection;

use CDash\Messaging\Topic\TopicDiscoveryInterface;

/**
 * Class TopicCollection
 * @package CDash\Messaging\Collection
 */
class TopicCollection extends Collection
{
    public function addTopic(TopicDiscoveryInterface $topic) : TopicCollection
    {
        parent::add($topic);
        return $this;
    }

    public function add($item, $label = null)
    {
        $this->addTopic($item);
    }
}
