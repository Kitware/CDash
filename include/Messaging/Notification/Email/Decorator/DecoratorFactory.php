<?php
namespace CDash\Messaging\Notification\Email\Decorator;

use CDash\Collection\CollectionInterface;
use CDash\Messaging\Topic\Topic;

class DecoratorFactory
{
    public static function createFromTopic(Topic $topic, DecoratorInterface $decorator)
    {
        switch ($topic->getTopicName()) {
            case 'TestFailure':
                return new TestFailureDecorator($decorator);
            case 'Configure':
                return new ConfigureDecorator($decorator);
            case 'Labeled':
                return new LabeledDecorator($decorator);
            case 'BuildWarning':
            case 'BuildError':
                return new BuildErrorDecorator($decorator);
            case 'DynamicAnalysis':
                return new DynamicAnalysisDecorator($decorator);
        }
    }

    public static function createFromCollection(CollectionInterface $collection,
                                                DecoratorInterface $decorator)
    {
        $namespace = explode('\\', get_class($collection));
        $class_name = array_pop($namespace);
        switch ($class_name) {
            case 'TestCollection':
                return new TestFailureDecorator($decorator);
        }
    }
}
