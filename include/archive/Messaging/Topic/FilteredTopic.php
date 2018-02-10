<?php
namespace CDash\archive\Messaging\Topic;

use ActionableBuildInterface;
use CDash\Messaging\Filter\Filter;

/**
 * Class FilteredTopic
 * @package CDash\Messaging\Topic
 */
class FilteredTopic extends AbstractTopic implements TopicDiscoveryInterface
{
    /** @var Filter $Filter */
    private $Filter;

    public function __construct(Filter $filter)
    {
        $this->Filter = $filter;
    }

    /**
     * @param ActionableBuildInterface|null $handler
     * @return bool
     */
    public function hasTopic(ActionableBuildInterface $handler = null): boolean
    {
        return $this->Filter->meetsCriteria($handler);
    }

    /**
     * @return int
     */
    public function getTopicMask(): int
    {
        return Topic::TOPIC_FILTERED;
    }
}
