<?php
namespace CDash\Messaging\Topic;

use CDash\Model\Build;

/**
 * Class FixedTopic
 * @package CDash\Messaging\Topic
 */
class FixedTopic extends Topic
{
    /** @var array $fixes */
    private $fixes = [];

    /** @var bool $decoratedSubscribes */
    private $decoratedSubscribes;

    /**
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        $this->decoratedSubscribes = $this->topic->subscribesToBuild($build);
        return $this->decoratedSubscribes
            || $this->topic->hasFixes();
    }

    /**
     * @param Build $build
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

    public function getFixed()
    {
        return $this->fixes;
    }
}
