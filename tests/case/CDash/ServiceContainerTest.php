<?php
use CDash\ServiceContainer;

class ServiceContainerTest extends PHPUnit_Framework_TestCase
{
    private static $di;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        self::$di = ServiceContainer::getInstance();
    }

    public static function tearDownAfterClass()
    {
        ServiceContainer::setInstance(ServiceContainer::class, self::$di);
        parent::tearDownAfterClass();
    }

    public function setUp()
    {
        parent::setUp();
        ServiceContainer::setInstance(ServiceContainer::class, self::$di);
    }

    public function testGetInstance()
    {
        $instance = ServiceContainer::getInstance();
        $this->assertInstanceOf(ServiceContainer::class, $instance);
    }

    public function testSetInstance()
    {
        $mock_container = $this->getMockBuilder(ServiceContainer::class)
            ->disableOriginalConstructor()
            ->getMock();

        ServiceContainer::setInstance(ServiceContainer::class, $mock_container);
        $this->assertSame($mock_container, ServiceContainer::getInstance());
    }

    public function testCreate()
    {
        $mock_di = $this->getMockBuilder(DI\Container::class)
            ->disableOriginalConstructor()
            ->setMethods(['make'])
            ->getMock();

        $mock_di
            ->expects($this->once())
            ->method('make')
            ->with($this->equalTo('SomeClassName'));

        $container = ServiceContainer::getInstance();
        $container->setContainer($mock_di);
        $container->create('SomeClassName');
    }
}
