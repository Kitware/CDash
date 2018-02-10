<?php
namespace CDash\archive\Messaging\Topic;

use ActionableBuildInterface;
use CDash\Messaging\Filter\Filter;

/**
 * Interface TopicDiscoveryInterface
 * @package Topic
 */
interface TopicDiscoveryInterface
{
    /**
     * @param ActionableBuildInterface|null $handler
     * @return bool
     */
    public function hasTopic(ActionableBuildInterface $handler = null) : boolean;

    /**
     * @param Filter $filter
     * @return void
     */
    public function setFilter(Filter $filter) : void;

    /**
     * @return int
     */
    public function getTopicMask() : int;
}
