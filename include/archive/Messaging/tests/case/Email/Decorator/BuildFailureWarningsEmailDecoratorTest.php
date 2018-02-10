<?php

use CDash\Messaging\Email\Decorator\BuildFailureWarningsEmailDecorator;
use CDash\Config\Config;

class BuildFailureWarningsEmailDecoratorTest extends PHPUnit_Framework_TestCase
{
    public function testGetBodyTemplate()
    {
        $sut = new BuildFailureWarningsEmailDecorator();
        $expected = "*Warnings*\n%s";
        $actual = $sut->getBodyTemplate();
        $this->assertEquals($expected, $actual);
    }

    public function testGetTemplateTopicItems()
    {
        $expected = ['one','build','failure', 'too', 'many'];
        $build = $this->getMock('\Build', ['GetFailures'], [], '', false);
        $build
            ->expects($this->once())
            ->method('GetFailures')
            ->with($this->equalTo(['type' => \Build::TYPE_WARN]))
            ->will($this->returnValue($expected));
        $sut = new BuildFailureWarningsEmailDecorator();
        $actual = $sut->getTemplateTopicItems($build, '');
        $this->assertSame($expected, $actual);
    }

    public function testGetItemTemplateFailureHasSourceFile()
    {
        $buildFailure = $this->getMock('BuildFailure', [], [], '', false);
        $buildFailure->SourceFile = '/path/to/source/file';

        $sut = new BuildFailureWarningsEmailDecorator();
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

        $buildFailure = $this->getMock('BuildFailure', [], [], '', false);
        $buildFailure->SourceFile = $sourceFile;
        $buildFailure->StdOutput = $stdOutput;
        $buildFailure->StdError = $stdError;
        $buildFailure->BuildId = $buildId;

        Config::set('CDASH_BASE_URL', $buildHost);

        $sut = new BuildFailureWarningsEmailDecorator();
        $expected = [$sourceFile, $buildHost, $buildId, "$stdOutput\n", "$stdError\n"];

        $actual = $sut->getItemTemplateValues($buildFailure);
        $this->assertEquals($expected, $actual);
    }
}
