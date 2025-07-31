<?php

namespace CDash\Messaging\Topic;

use CDash\Model\Build;

/**
 * Class FixedTopic
 */
class FixedTopic extends Topic
{
    /** @var array */
    private $fixes = [];

    /** @var bool */
    private $decoratedSubscribes;

    public function subscribesToBuild(Build $build): bool
    {
        $this->decoratedSubscribes = $this->topic->subscribesToBuild($build);
        return $this->decoratedSubscribes
            || $this->topic->hasFixes();
    }

    /**
     * @return Topic|void
     */
    public function setTopicData(Build $build)
    {
        if ($this->topic->hasFixes()) {
            $type = $this->topic->getTopicName();
            $this->fixes[$type] = $this->topic->getFixes();
        }

        if ($this->decoratedSubscribes) {
            $this->topic->setTopicData($build);
        }
    }

    public function getTemplate(): array
    {
        $templates = [];
        if ($this->topic->hasFixes()) {
            $templates[] = 'fix';
        }

        if ($this->decoratedSubscribes) {
            $templates[] = $this->topic->getTemplate();
        }
        return $templates;
    }

    public function getFixed(): array
    {
        return $this->fixes;
    }
}
