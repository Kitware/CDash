<?php
namespace CDash\Messaging\Notification\Email\Decorator;

use CDash\Messaging\Topic\Topic;

class DecoratorFactory
{
    public static function createFromTopic(Topic $topic, DecoratorInterface $decorator)
    {
        switch ($topic->getTopicName()) {
            case 'TestFailure':
                return new TestFailureDecorator($decorator);
        }
    }

    public static function createFromDecorator(DecoratorInterface $decorator)
    {
    }

    public static function create($name)
    {
        switch ($name) {
            case 'TestFailure':
                return new TestFailureDecorator();
            case 'SummaryDecorator':
                return new SummaryDecorator();
            case 'PreambleDecorator':
                return new PreambleDecorator();
            case 'FooterDecorator':
                return new FooterDecorator();
        }
    }
}
