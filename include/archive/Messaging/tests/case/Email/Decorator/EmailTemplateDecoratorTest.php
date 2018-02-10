<?php

use CDash\Messaging\Email\Decorator\BuildFailureErrorsEmailDecorator;
use CDash\Messaging\Email\EmailMessage;
use CDash\Messaging\Collection\BuildCollection;
use CDash\Messaging\Collection\DecoratorCollection;
use CDash\Config\Config;

class EmailTemplateDecoratorTest extends PHPUnit_Framework_TestCase
{
    /** @var  Project|PHPUnit_Framework_MockObject_MockObject $project */
    private $project;

    /** @var  BuildGroup|PHPUnit_Framework_MockObject_MockObject $buildGroup */
    private $buildGroup;

    /** @var  BuildCollection|PHPUnit_Framework_MockObject_MockObject $buildCollection */
    private $buildCollection;

    /** @var  DecoratorCollection|PHPUnit_Framework_MockObject_MockObject $decorCollection */
    private $decorCollection;

    /** @var  EmailMessage|PHPUnit_Framework_MockObject_MockObject */
    private $emailMessage;

    public function setUp()
    {
        parent::setUp();
        $this->project = $this->getMock('\Project',[], [], '', false);
        $this->buildGroup = $this->getMock('\BuildGroup', [], [], '', false);
        $this->buildCollection = new BuildCollection();
        $this->decorCollection = new DecoratorCollection();
        $this->emailMessage = new EmailMessage(
            $this->project,
            $this->buildGroup,
            $this->buildCollection,
            $this->decorCollection
        );
    }

    public function testHasTopic()
    {
        $mockBuild1 = $this->getMock('\Build', ['GetFailures', 'GetName'], [], '', false);
        $mockBuild2 = $this->getMock('\Build', ['GetFailures', 'GetName'], [], '', false);

        $mockBuild1
            ->expects($this->atLeastOnce())
            ->method('GetName')
            ->will($this->returnValue('BuildOne'));

        $mockBuild2
            ->expects($this->atLeastOnce())
            ->method('GetName')
            ->will($this->returnValue('BuildTwo'));

        $mockBuild1Error1 = $this->getMock('\BuildFailure', [], [], '', false);


        $mockBuild1Error1->Type = \Build::TYPE_ERROR;
        $mockBuild2Error2 = $this->getMock('\BuildFailure', [], [], '', false);

        $mockBuild2Error2->Type = \Build::TYPE_ERROR;

        $this->buildCollection
            ->add($mockBuild1)
            ->add($mockBuild2);

        $sut = new BuildFailureErrorsEmailDecorator($this->emailMessage);

        // Not having set up our method expectations, they should return null, and therefore
        // BuildFailureErrorsEmailDecorator::hasTopic should report false
        $this->assertFalse($sut->hasTopic());

        $mockBuild1
            ->expects($this->once())
            ->method('GetFailures')
            ->will($this->returnValue([$mockBuild1Error1]));

        $mockBuild2
            ->expects($this->once())
            ->method('GetFailures')
            ->will($this->returnValue([$mockBuild2Error2]));

        // Now that we've instructed our mock Builds to return some BuildFailures
        // should any of those BuildFailures be of type Build::TYPE_ERROR, of
        // which there are 2, this should now report true
        $this->assertTrue($sut->hasTopic());

        $topic = $sut->getTopicCollection();

        $this->assertCount(2, $topic);
        $this->assertSame($mockBuild1Error1, $topic->current());

        $topic->next();
        $this->assertSame($mockBuild2Error2, $topic->current());
    }

    public function testBody()
    {
        $base_url = Config::get('CDASH_BASE_URL');
        Config::set('CDASH_BASE_URL', 'http://host.tld/cdash');

        $mockBuild1 = $this->getMock('\Build', ['GetFailures', 'GetName'], [], '', false);
        $mockBuild2 = $this->getMock('\Build', ['GetFailures', 'GetName'], [], '', false);

        $mockBuild1Error1 = $this->getMock('\BuildFailure', [], [], '', false);
        $mockBuild1Error1->Type = \Build::TYPE_ERROR;
        $mockBuild1Error1->BuildId = '1010';
        $mockBuild1Error1->SourceFile = '[SOURCEFILE 1]';
        $mockBuild1Error1->StdOutput = "[STDOUT LINE 1]\n[STDOUT LINE 2]";
        $mockBuild1Error1->StdError = "[STDERR LINE 1]\n[STDERR LINE 2]";

        $mockBuild2Error2 = $this->getMock('\BuildFailure', [], [], '', false);
        $mockBuild2Error2->Type = \Build::TYPE_ERROR;
        $mockBuild2Error2->BuildId = '1011';
        $mockBuild2Error2->SourceFile = '[SOURCEFILE 2]';
        $mockBuild2Error2->StdOutput = "[STDOUT LINE 3]\n[STDOUT LINE 4]";
        $mockBuild2Error2->StdError = "[STDERR LINE 3]\n[STDERR LINE 4]";

        $mockBuild1
            ->expects($this->atLeastOnce())
            ->method('GetName')
            ->will($this->returnValue('BuildOne'));

        $mockBuild2
            ->expects($this->atLeastOnce())
            ->method('GetName')
            ->will($this->returnValue('BuildTwo'));

        $mockBuild1
            ->expects($this->once())
            ->method('GetFailures')
            ->will($this->returnValue([$mockBuild1Error1]));

        $mockBuild2
            ->expects($this->once())
            ->method('GetFailures')
            ->will($this->returnValue([$mockBuild2Error2]));

        $this->buildCollection
            ->add($mockBuild1)
            ->add($mockBuild2);

        $sut = new BuildFailureErrorsEmailDecorator($this->emailMessage);

        $expected = [
            '*Errors*',
            '[SOURCEFILE 1] (http://host.tld/cdash/viewBuildError.php?type=0&buildid=1010)',
            '[STDOUT LINE 1]',
            '[STDOUT LINE 2]',
            '[STDERR LINE 1]',
            '[STDERR LINE 2]',
            '[SOURCEFILE 2] (http://host.tld/cdash/viewBuildError.php?type=0&buildid=1011)',
            '[STDOUT LINE 3]',
            '[STDOUT LINE 4]',
            '[STDERR LINE 3]',
            '[STDERR LINE 4]',
        ];

        $this->assertTrue($sut->hasTopic());

        $body = $sut->body();

        $lines = explode("\n", $body);

        $this->assertEquals(count($expected), count($lines));

        foreach ($lines as $no => $line) {
            $this->assertEquals($expected[$no], $line);
        }

        Config::set('CDASH_BASE_URL', $base_url);
    }
}
