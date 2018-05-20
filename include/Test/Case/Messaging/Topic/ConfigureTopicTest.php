<?php

use CDash\Collection\ConfigureCollection;
use CDash\Messaging\Topic\ConfigureTopic;
use CDash\Messaging\Topic\Topic;
use CDash\Messaging\Topic\TopicInterface;
use CDash\Model\Build;
use CDash\Model\BuildConfigure;

class ConfigureTopicTest extends \CDash\Test\CDashTestCase
{
  /** @var Topic|PHPUnit_Framework_MockObject_MockObject */
  private $parent;

  public function setUp()
  {
    parent::setUp();
    $this->parent = $this->getMockForAbstractClass(Topic::class);
  }

  public function testSubscribesToBuild()
  {
    $sut = new ConfigureTopic();
    $build = new Build();
    $buildConfigure = new BuildConfigure();
    $build->SetBuildConfigure($buildConfigure);

    // SUT has no parent, BuildConfigure has no warnings or errors
    $this->assertFalse($sut->subscribesToBuild($build));

    $this->parent
      ->method('subscribesToBuild')
      ->willReturnOnConsecutiveCalls(false, true, true, true);

    // SUT has parent does not subscribe, BuildConfigure has no warnings or errors
    $sut = new ConfigureTopic($this->parent);
    $this->assertFalse($sut->subscribesToBuild($build));

    // SUT has parent who subscribes, BuildConfigure has no warnings or errors
    $sut = new ConfigureTopic($this->parent);
    $this->assertFalse($sut->subscribesToBuild($build));

    // SUT has parent who subscribes, BuildConfigure has warnings, no errors
    $buildConfigure->NumberOfWarnings = 1;
    $sut = new ConfigureTopic($this->parent);
    $this->assertTrue($sut->subscribesToBuild($build));

    // SUT has parent who subscribes, BuildConfigure errors, no warnings
    $buildConfigure->NumberOfWarnings = 0;
    $buildConfigure->NumberOfErrors = 1;
    $sut = new ConfigureTopic($this->parent);
    $this->assertTrue($sut->subscribesToBuild($build));

    $buildConfigure->NumberOfWarnings = -1;
    $buildConfigure->NumberOfErrors = 0;
    $sut = new ConfigureTopic();
    $this->assertFalse($sut->subscribesToBuild($build));

    $buildConfigure->NumberOfWarnings = 0;
    $buildConfigure->NumberOfErrors = -1;
    $sut = new ConfigureTopic();
    $this->assertFalse($sut->subscribesToBuild($build));
  }

  public function testSetTopicData()
  {
    $sut = new ConfigureTopic();
    $build = new Build();
    $buildConfigure = new BuildConfigure();
    $build->SetBuildConfigure($buildConfigure);

    $sut->setTopicData($build);
    $collection = $sut->getTopicCollection();
    $this->assertSame($buildConfigure, $collection->get(Topic::CONFIGURE));
  }

  public function testGetTopicCount()
  {
    $sut = new ConfigureTopic();
    $build = new Build();
    $buildConfigure = new BuildConfigure();
    $build->SetBuildConfigure($buildConfigure);

    $sut->setTopicData($build);
    $this->assertEquals(0, $sut->getTopicCount());

    $buildConfigure->Status = '0';
    $this->assertEquals(0, $sut->getTopicCount());

    $buildConfigure->Status = '127';
    $this->assertEquals(127, $sut->getTopicCount());
  }

  public function testGetTopicDescription()
  {
    $sut = new ConfigureTopic();
    $this->assertEquals('Configure Errors', $sut->getTopicDescription());
  }

  public function testGetTopicName()
  {
    $sut = new ConfigureTopic();
    $this->assertEquals(Topic::CONFIGURE, $sut->getTopicName());
  }

  public function testGetTopicCollection()
  {
    $sut = new ConfigureTopic();
    $collection = $sut->getTopicCollection();
    $this->assertInstanceOf(ConfigureCollection::class, $collection);
  }
}
