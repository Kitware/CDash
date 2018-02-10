<?php

use CDash\Messaging\Email\Decorator\BuildFailureErrorsEmailDecorator;
use CDash\Config\Config;

class BuildFailureErrorsEmailDecoratorTest extends PHPUnit_Framework_TestCase
{
    public function testGetBodyTemplate()
    {
        $sut = new BuildFailureErrorsEmailDecorator();
        $expected = "*Errors*\n%s";
        $actual = $sut->getBodyTemplate();
        $this->assertEquals($expected, $actual);
    }

    public function testGetTemplateTopicItems()
    {
        $expected = ['one','build','failure', 'too', 'many'];
        /** @var \Build|PHPUnit_Framework_MockObject_MockObject $build */
        $build = $this->getMock('\Build', ['GetFailures'], [], '', false);
        $build
            ->expects($this->once())
            ->method('GetFailures')
            ->with($this->equalTo(['type' => \Build::TYPE_ERROR]))
            ->will($this->returnValue($expected));
        $sut = new BuildFailureErrorsEmailDecorator();
        $actual = $sut->getTemplateTopicItems($build, '');
        $this->assertSame($expected, $actual);
    }

    public function testGetItemTemplateFailureHasSourceFile()
    {
        $buildFailure = $this->getMock('BuildFailure', [], [], '', false);
        $buildFailure->SourceFile = '/path/to/source/file';

        $sut = new BuildFailureErrorsEmailDecorator();
        $expected = "%s (%s/viewBuildError.php?type=0&buildid=%u)\n%s%s\n";
        $actual = $sut->getItemTemplate($buildFailure);
        $this->assertEquals($expected, $actual);
    }

    public function testGetTemplateItemValuesFailureHasSourceFile()
    {
        $sourceFile = '/path/to/src/file';
        $stdOutput = 'blah blah blah blah blah';
        $stdError = 'bleh bleh, bleh bleh... bleh';
        $buildHost = 'https://www.tld.com/cdash';
        $buildId = '4075';

        /** @var \BuildFailure|PHPUnit_Framework_MockObject_MockObject $buildFailure */
        $buildFailure = $this->getMock('BuildFailure', [], [], '', false);
        $buildFailure->SourceFile = $sourceFile;
        $buildFailure->StdOutput = $stdOutput;
        $buildFailure->StdError = $stdError;
        $buildFailure->BuildId = $buildId;

        Config::set('CDASH_BASE_URL', $buildHost);

        $sut = new BuildFailureErrorsEmailDecorator();
        $expected = [$sourceFile, $buildHost, $buildId, "$stdOutput\n", "$stdError\n"];

        $actual = $sut->getItemTemplateValues($buildFailure);
        $this->assertEquals($expected, $actual);
    }
}
