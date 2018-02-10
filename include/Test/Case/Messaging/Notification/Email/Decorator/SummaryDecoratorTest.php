<?php
use CDash\Messaging\Notification\Email\Decorator\SummaryDecorator;
use CDash\Messaging\Notification\Email\EmailMessage;

class SummaryDecoratorTest extends \CDash\Test\CDashTestCase
{
    public function testWith()
    {
        $topic = [
            ['project_name' => 'BreakerBreaker1-9',
            'site_name' => 'SmokeySite',
            'build_name' => 'I405Convoy',
            'build_time' => '2010-01-11T10:01:11',
            'build_group' => 'Experimental',
            'summary_string' => 'Configuration errors',
            'summary_count' => '10',]
        ];

        $sut = new SummaryDecorator();
        $sut
            ->decorate(new EmailMessage())
            ->with($topic);

        $expected_lines = [
            'Project: BreakerBreaker1-9',
            'Site: SmokeySite',
            'Build Name: I405Convoy',
            'Build Time: 2010-01-11T10:01:11',
            'Type: Experimental',
            'Configuration errors: 10',
            '',
        ];

        $expected = implode("\n", $expected_lines);
        $actual = "{$sut}";

        $this->assertEquals($expected, $actual);
    }
}
