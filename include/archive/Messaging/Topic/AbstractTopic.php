<?php
namespace CDash\archive\Messaging\Topic;

use CDash\Messaging\Filter\Filter;

/**
 * Class Topic
 * @package CDash\Messaging\Topic
 */
abstract class AbstractTopic implements TopicDiscoveryInterface
{

    /** @var  Filter $Filter */
    protected $Filter;

    public function setFilter(Filter $filter) : void
    {
        $this->Filter = $filter;
    }
}
