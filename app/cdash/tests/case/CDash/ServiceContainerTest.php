<?php

use CDash\ServiceContainer;
use CDash\Test\CDashTestCase;
use DI\Container;

class ServiceContainerTest extends CDashTestCase
{
    private static $di;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$di = ServiceContainer::getInstance();
    }

    public static function tearDownAfterClass(): void
    {
        ServiceContainer::setInstance(ServiceContainer::class, self::$di);
        parent::tearDownAfterClass();
    }

    public function setUp(): void
    {
        parent::setUp();
        ServiceContainer::setInstance(ServiceContainer::class, self::$di);
    }

    public function testGetInstance(): void
    {
        $instance = ServiceContainer::getInstance();
        $this->assertInstanceOf(ServiceContainer::class, $instance);
    }

    public function testSetInstance(): void
    {
        $mock_container = $this->getMockBuilder(ServiceContainer::class)
            ->disableOriginalConstructor()
            ->getMock();

        ServiceContainer::setInstance(ServiceContainer::class, $mock_container);
        $this->assertSame($mock_container, ServiceContainer::getInstance());
    }

    public function testCreate(): void
    {
        $mock_di = $this->getMockBuilder(Container::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['make'])
            ->getMock();

        $mock_di
            ->expects($this->once())
            ->method('make')
            ->with($this->equalTo('SomeClassName'));

        $container = ServiceContainer::getInstance();
        $container->setContainer($mock_di);
        $container->create('SomeClassName');
    }

    public function testGet(): void
    {
        $mock_di = $this->getMockBuilder(Container::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();

        $mock_di
            ->expects($this->once())
            ->method('get')
            ->with($this->equalTo('SomeClassName'));

        $container = ServiceContainer::getInstance();
        $container->setContainer($mock_di);
        $container->get('SomeClassName');
    }
}
