<?php
namespace CDash\archive\archive\Messaging\Topic;

use CDash\Messaging\Collection\TopicCollection;

class Topic
{
    const TOPIC_FILTERED = 0;
    const TOPIC_UPDATE = 2;
    const TOPIC_CONFIGURE = 4;
    const TOPIC_WARNING = 8;
    const TOPIC_ERROR = 16;
    const TOPIC_TEST = 32;
    const TOPIC_DYNAMIC_ANALYSIS = 64;
    const TOPIC_FIXES = 128;
    CONST TOPIC_MISSING_SITES = 256;

    private $Topics;

    public function __construct(TopicCollection $topics)
    {
        $this->Topics = $topics;
    }

    public function addTopic(TopicDiscoveryInterface $topic)
    {
        $this->Topics->addTopic($topic);
    }
}
