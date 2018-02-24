<?php
namespace CDash\Messaging\Notification\Email\Decorator;

class LabeledDecorator extends Decorator
{
    private $decoratorFactory;

    public function addSubject($subject)
    {
        $factory = $this->getDecoratorFactory();
        $topics = $subject->getTopicCollection();
        $decorator = $factory::createFromCollection($topics, $this);
        $this->text = $decorator
            ->setMaxTopicItems($this->maxTopicItems)
            ->addSubject($subject);
    }

    protected function getDecoratorFactory()
    {
        if (!$this->decoratorFactory) {
            $this->decoratorFactory = new DecoratorFactory();
        }
        return $this->decoratorFactory;
    }
}
