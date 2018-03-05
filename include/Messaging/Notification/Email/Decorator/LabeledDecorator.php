<?php
namespace CDash\Messaging\Notification\Email\Decorator;

use CDash\Messaging\Topic\Topic;

class LabeledDecorator extends Decorator
{
    private $decoratorFactory;

    /**
     * @param Topic $topic
     * @return string|void
     */
    public function setTopic(Topic $topic)
    {
        $factory = $this->getDecoratorFactory();
        $topics = $topic->getTopicCollection();
        $decorator = $factory::createFromCollection($topics, $this);
    }

    protected function getDecoratorFactory()
    {
        if (!$this->decoratorFactory) {
            $this->decoratorFactory = new DecoratorFactory();
        }
        return $this->decoratorFactory;
    }
}
