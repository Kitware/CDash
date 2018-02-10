<?php
use CDash\Collection\SubscriberCollection;
use CDash\Messaging\Subscription\Subscription;
use CDash\Messaging\Subscription\SubscriptionBuilder;
use CDash\Messaging\Subscription\SubscriptionCollection;
use CDash\Messaging\Subscription\SubscriptionFactory;
use CDash\Messaging\Topic\Topic;
use CDash\Messaging\Topic\TopicCollection;

class SubscriptionBuilderTest extends \CDash\Test\CDashTestCase
{
    public function testBuild()
    {
        /** @var ActionableBuildInterface|PHPUnit_Framework_MockObject_MockObject $handler */
        $handler = $this->getMockActionableBuild();

        /** @var Project|PHPUnit_Framework_MockObject_MockObject $project */
        $project = $this->getMockProject();

        /** @var SubscriptionFactory|PHPUnit_Framework_MockObject_MockObject $factory */
        $factory = $this->getMockBuilder(SubscriptionFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();

        /** @var Subscription $subscription */
        $subscription = new Subscription();

        /** @var EmailSubscriber|PHPUnit_Framework_MockObject_MockObject $subscriber */
        $subscriber = $this->getMockBuilder(\SubscriberInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['hasTopics', 'getTopics', 'getAddress'])
            ->getMock();

        $topic = new Topic();

        $subscriberCollection = new SubscriberCollection();
        $subscriberCollection->add($subscriber);

        $topicCollection = new TopicCollection();
        $topicCollection->add($topic);

        $project
            ->expects($this->once())
            ->method('GetProjectSubscribers')
            ->willReturn($subscriberCollection);

        $subscriber
            ->expects($this->once())
            ->method('hasTopics')
            ->with($this->equalTo($handler))
            ->willReturn(true);

        $factory
            ->expects($this->once())
            ->method('create')
            ->willReturn($subscription);

        $subscriber
            ->expects($this->once())
            ->method('getTopics')
            ->willReturn($topicCollection);

        $sut = new SubscriptionBuilder();
        $sut
            ->setActionableBuild($handler)
            ->setProject($project)
            ->setSubscriptionFactory($factory);

        $subscriptions = new SubscriptionCollection();
        $sut->build($subscriptions);

        $this->assertCount(1, $subscriptions);
        $this->assertSame($subscriber, $subscription->getSubscriber());
        $this->assertSame($topicCollection, $subscription->getTopicCollection());
    }
}
