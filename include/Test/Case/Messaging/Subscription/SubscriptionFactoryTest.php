<?php
use CDash\Messaging\Subscription\SubscriptionFactory;

class SubscriptionFactoryTest extends CDash\Test\CDashTestCase
{
    public function testCreate()
    {
        $sut = new SubscriptionFactory();
        $expected = \CDash\Messaging\Subscription\Subscription::class;
        $actual = $sut->create();
        $this->assertInstanceOf($expected, $actual);
    }
}
