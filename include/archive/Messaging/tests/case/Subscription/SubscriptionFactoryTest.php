<?php

use CDash\Messaging\Collection\TopicCollection;
use CDash\Messaging\Subscription\SubscriptionFactory;
use CDash\Messaging\Subscription\UserSubscriptionFactory;
use CDash\Messaging\Subscription\UserSubscription;
use CDash\Messaging\Topic\BuildErrorTopic;
use CDash\Messaging\Topic\BuildWarningTopic;
use CDash\Messaging\Topic\FilteredTopic;
/**
 * Class SubscriptionFactoryTest
 */
class SubscriptionFactoryTest extends \CDash\Test\CDashTestCase
{
    /** @var  ActionableBuildInterface|PHPUnit_Framework_MockObject_MockObject */
    private $handler;

    public function setUp()
    {
        parent::setUp();
        $this->handler = $this->getMockBuilder(['ActionableBuildInterface'])
            ->enableArgumentCloning()
            ->getMock();
    }

    private function getUser2TopicRow($id, $userid, $type, $filter = null, $value = null)
    {
        return [
            'id' => $id,
            'userid' => $userid,
            'topictype' => $type,
            'filter' => $filter,
            'filtervalue' => $value
        ];
    }

    public function testCreateSubscription()
    {
        $row_1 = $this->getUser2TopicRow(1, 1, 'BuildWarning');
        $row_2 = $this->getUser2TopicRow(2, 2, 'BuildFailure');
        $row_3 = $this->getUser2TopicRow(3, 3, 'Filtered');
        $row_4 = $this->getUser2TopicRow(4, 3, 'BuildFailure');

        $user_project = $this->getMockUserProject();

        $user_1 = $this->getMockUser();
        $user_project_1 = $this->getMockUserProject();
        $user_topic_1 = $this->getMockUserTopic();
        $user_subscription_1 = new UserSubscription(
            $user_1,
            $user_project_1,
            $user_topic_1,
            new TopicCollection()
        );

        $user_2 = $this->getMockUser();
        $user_project_2 = $this->getMockUserProject();
        $user_topic_2 = $this->getMockUserTopic();
        $user_subscription_2 = new UserSubscription(
            $user_2,
            $user_project_2,
            $user_topic_2,
            new TopicCollection()
        );

        $user_3 = $this->getMockUser();
        $user_project_3 = $this->getMockUserProject();
        $user_topic_3 = $this->getMockUserTopic();
        $user_subscription_3 = new UserSubscription(
            $user_3,
            $user_project_3,
            $user_topic_3,
            new TopicCollection()
        );

        $user_subscription_factory = $this->getMockBuilder(UserSubscriptionFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['createUserSubscription'])
            ->getMock();

        $user_subscription_factory
            ->expects($this->at(0))
            ->method('createUserSubscription')
            ->willReturn($user_subscription_1);

        $user_subscription_factory
            ->expects($this->at(1))
            ->method('createUserSubscription')
            ->willReturn($user_subscription_2);

        $user_subscription_factory
            ->expects($this->at(2))
            ->method('createUserSubscription')
            ->willReturn($user_subscription_3);

        $this->handler
            ->expects($this->atLeastOnce())
            ->method('getType')
            ->willReturn(ActionableBuildInterface::TYPE_BUILD);

        $user_project
            ->expects($this->once())
            ->method('GetUsers')
            ->willReturn([1, 2, 3]);

        $user_topic_1
            ->expects($this->once())
            ->method('getUserTopicsOfType')
            ->with($this->equalTo(1), ActionableBuildInterface::TYPE_BUILD)
            ->willReturn([$row_1]);

        $user_topic_2
            ->expects($this->once())
            ->method('getUserTopicsOfType')
            ->with($this->equalTo(2), ActionableBuildInterface::TYPE_BUILD)
            ->willReturn([$row_2]);

        $user_topic_3
            ->expects($this->once())
            ->method('getUserTopicsOfType')
            ->with($this->equalTo(3), ActionableBuildInterface::TYPE_BUILD)
            ->willReturn([$row_3, $row_4]);

        $sut = new SubscriptionFactory($user_project, $user_subscription_factory);
        $subscription = $sut->createSubscription($this->handler);

        $collection = $subscription->getTopicCollection();

        /** @var UserSubscription $user_subscription */
        $user_subscription = $collection->current();
        $topic_collection = $user_subscription->getTopicCollection();
        $topic = $topic_collection->current();
        $this->assertEquals(1, $topic_collection->count());
        $this->assertInstanceOf(BuildWarningTopic::class, $topic);

        $collection->next();
        $user_subscription = $collection->current();
        $topic_collection = $user_subscription->getTopicCollection();
        $topic = $topic_collection->current();
        $this->assertEquals(1, $topic_collection->count());
        $this->assertInstanceOf(BuildErrorTopic::class, $topic);

        $collection->next();
        $user_subscription = $collection->current();
        $topic_collection = $user_subscription->getTopicCollection();
        $topic = $topic_collection->current();
        $this->assertEquals(2, $topic_collection->count());
        $this->assertInstanceOf(FilteredTopic::class, $topic);

        $topic_collection->next();
        $topic = $topic_collection->current();
        $this->assertInstanceOf(BuildErrorTopic::class, $topic);
    }
}
