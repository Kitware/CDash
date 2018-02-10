<?php

use CDash\Messaging\Filter\BuildLabelFilter;
use CDash\Messaging\Filter\Filter;
use CDash\Messaging\Collection\RecipientCollection;
use CDash\Messaging\Collection\TopicCollection;
use CDash\Messaging\Subscription\Subscription;
use CDash\Messaging\Topic\FilteredTopic;
use CDash\Messaging\Topic\TestFailureTopic;
use CDash\Messaging\Topic\UpdateTopic;
use CDash\Messaging\Topic\Topic;

/**
 * Class SubscriptionTest
 */
class SubscriptionTest extends \CDash\Test\CDashTestCase
{
    /*
     * Instantiating a subscription with a project id hydrates the subscription
     * with all of the topics associated with every user of that project.
     */
    /** @var  RecipientCollection $recipientCollection */
    private $recipientCollection;
    public function setUp()
    {
        parent::setUp();
        $this->recipientCollection = new RecipientCollection();
    }

    public function testSubscriptionSubscribesUpdateFailuresForUsers()
    {
        $sut = new Subscription();

        $topics = new TopicCollection();
        $sut->addTopicCollection($topics);

        $updateFailureTopic = new UpdateTopic();
        $sut->addTopic($updateFailureTopic);

        $update_subscriber = $this->getMockSubscriber(Topic::TOPIC_UPDATE);
        $failure_subscriber = $this->getMockSubscriber(Topic::TOPIC_TEST);

        $project_users = new RecipientCollection();
        $project_users->add($update_subscriber);
        $project_users->add($failure_subscriber);

        $subscribers = $sut->getSubscribers($project_users);

        $actual = $subscribers->current();
        $this->assertSame($update_subscriber, $actual);
    }

    public function testSubscriptionSubscribesFilteredForUsers()
    {
        $sut = new Subscription();

        $topics = new TopicCollection();
        $sut->addTopicCollection($topics);

        $filter = new BuildLabelFilter('label', 'build', 'Sundance');
        $filteredTopic = new FilteredTopic();
    }

    private function getMockSubscriber($subscription)
    {
        $subscriber = $this->getMockUserProject();
        $subscriber
            ->expects($this->once())
            ->method('GetSubscriptions')
            ->willReturn($subscription);
        return $subscriber;
    }
}
