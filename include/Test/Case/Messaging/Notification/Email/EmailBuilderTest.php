<?php
use CDash\Messaging\Notification\Email\EmailBuilder;
use CDash\Messaging\Notification\Email\EmailMessage;
use CDash\Messaging\Notification\Email\EmailNotificationFactory;
use CDash\Messaging\Notification\NotificationCollection;
use CDash\Messaging\Notification\NotificationInterface;
use CDash\Messaging\Subscription\Subscription;
use CDash\Messaging\Subscription\SubscriptionCollection;

class EmailBuilderTest extends \CDash\Test\CDashTestCase
{
    public function testCreateNotifications()
    {
        $subscriptionCollection = new SubscriptionCollection();

        $s1 = new Subscription();
        $s2 = new Subscription();

        $subscriptionCollection
            ->add($s1)
            ->add($s2);

        $notificationCollection = new NotificationCollection();
        $notificationFactory = new EmailNotificationFactory();

        $sut = new EmailBuilder($notificationFactory, $notificationCollection);
        $sut->setSubscriptions($subscriptionCollection);

        $sut->createNotifications();

        $this->assertInstanceOf(NotificationInterface::class, $s1->getNotification());
        $this->assertInstanceOf(NotificationInterface::class, $s2->getNotification());
    }

    public function testSetBody()
    {
    }
}
