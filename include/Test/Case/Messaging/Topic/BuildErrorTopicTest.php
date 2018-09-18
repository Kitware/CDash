<?php

use CDash\Collection\BuildErrorCollection;
use CDash\Messaging\Topic\BuildErrorTopic;
use CDash\Messaging\Topic\Topic;
use CDash\Model\Build;
use CDash\Model\BuildError;

class BuildErrorTopicTest extends \CDash\Test\CDashTestCase
{
    public function testSubscribesToBuildWithErrorDiff()
    {
        $build = new Build();

        $sut = new BuildErrorTopic();
        $sut->setType(Build::TYPE_ERROR);

        $this->assertFalse($sut->subscribesToBuild($build));
        $build = $this->getMockBuild();

        $build->expects($this->any())
            ->method('GetDiffWithPreviousBuild')
            ->willReturnOnConsecutiveCalls(
                ['BuildError' => ['new' => 1]],
                ['BuildError' => ['new' => 0]]
            );

        $this->assertTrue($sut->subscribesToBuild($build));
        $this->assertFalse($sut->subscribesToBuild($build));
    }

    public function testSubscribesToBuildWithWarningDiff()
    {
        $build = new Build();

        $sut = new BuildErrorTopic();
        $sut->setType(Build::TYPE_WARN);

        $this->assertFalse($sut->subscribesToBuild($build));
        $build = $this->getMockBuild();

        $build->expects($this->any())
            ->method('GetDiffWithPreviousBuild')
            ->willReturnOnConsecutiveCalls(
                ['BuildWarning' => ['new' => 1]],
                ['BuildWarning' => ['new' => 0]]
            );

        $this->assertTrue($sut->subscribesToBuild($build));
        $this->assertFalse($sut->subscribesToBuild($build));
    }

    public function testGetTopicCollection()
    {
        $sut = new BuildErrorTopic();
        $collection = $sut->getTopicCollection();
        $this->assertInstanceOf(BuildErrorCollection::class, $collection);
    }

    public function testSetTopicData()
    {
        $sut = new BuildErrorTopic();
        $sut->setType(Build::TYPE_ERROR);

        $build = new Build();
        $buildErrorError = new BuildError();
        $buildErrorWarning = new BuildError();
        $buildErrorError->Type = Build::TYPE_ERROR;
        $buildErrorWarning->Type = Build::TYPE_WARN;

        $build->AddError($buildErrorError);
        $build->AddError($buildErrorWarning);

        $sut->setTopicData($build);
        $collection = $sut->getTopicCollection();

        $this->assertSame($buildErrorError, $collection->current());

        $sut = new BuildErrorTopic();
        $sut->setType(Build::TYPE_WARN);

        $sut->setTopicData($build);
        $collection = $sut->getTopicCollection();

        $this->assertSame($buildErrorWarning, $collection->current());
    }

    public function testItemHasTopicSubject()
    {
        $sut = new BuildErrorTopic();
        $sut->setType(Build::TYPE_ERROR);

        $build = new Build();
        $error = new BuildError();
        $build->AddError($error);

        $this->assertFalse($sut->itemHasTopicSubject($build, $error));

        $error->Type = Build::TYPE_ERROR;
        $this->assertTrue($sut->itemHasTopicSubject($build, $error));

        $sut->setType(Build::TYPE_WARN);
        $this->assertFalse($sut->itemHasTopicSubject($build, $error));

        $error->Type = Build::TYPE_WARN;
        $this->assertTrue($sut->itemHasTopicSubject($build, $error));
    }

    public function testGetTopicCount()
    {
        $sut = new BuildErrorTopic();
        $sut->setType(Build::TYPE_ERROR);

        $build = new Build();
        $e1 = new BuildError();
        $e1->Type = Build::TYPE_WARN;
        $e2 = new BuildError();
        $e2->Type = Build::TYPE_ERROR;
        $e3 = new BuildError();
        $e3->Type = Build::TYPE_ERROR;

        $build->AddError($e1);
        $build->AddError($e2);
        $build->AddError($e3);

        $sut->setTopicData($build);
        $this->assertEquals(2, $sut->getTopicCount());
    }

    public function testGetTopicName()
    {
        $sut = new BuildErrorTopic();
        $sut->setType(Build::TYPE_ERROR);
        $this->assertEquals('BuildError', $sut->getTopicName());

        $sut->setType(Build::TYPE_WARN);
        $this->assertEquals('BuildWarning', $sut->getTopicName());
    }

    public function testGetTopicDescription()
    {
        $sut = new BuildErrorTopic();
        $sut->setType(Build::TYPE_ERROR);
        $this->assertEquals('Errors', $sut->getTopicDescription());

        $sut->setType(Build::TYPE_WARN);
        $this->assertEquals('Warnings', $sut->getTopicDescription());
    }
}
