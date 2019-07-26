<?php

use CDash\Model\Build;
use CDash\Submission\CommitAuthorHandlerTrait;

class CommitAuthorHandlerTraitTest extends PHPUnit_Framework_TestCase
{
    public function testGetCommitAuthors()
    {
        $has_errors = $this->getMockBuilder(Build::class)
            ->disableOriginalConstructor()
            ->setMethods(['GetCommitAuthors'])
            ->getMock();

        $has_warnings = $this->getMockBuilder(Build::class)
            ->disableOriginalConstructor()
            ->setMethods(['GetCommitAuthors'])
            ->getMock();

        $has_errors->expects($this->once())
            ->method('GetCommitAuthors')
            ->willReturn(['oneuser@company.tld', 'twouser@company.tld']);

        $has_warnings->expects($this->once())
            ->method('GetCommitAuthors')
            ->willReturn(['twouser@company.tld', 'threeuser@company.tld']);

        $builds = [$has_errors, $has_warnings];

        $sut = new class($builds) {
            use CommitAuthorHandlerTrait;

            private $Builds;

            public function __construct($builds)
            {
                $this->Builds = $builds;
            }
        };

        $actual = $sut->GetCommitAuthors();

        $this->assertCount(3, $actual);
        $this->assertContains('oneuser@company.tld', $actual);
        $this->assertContains('twouser@company.tld', $actual);
        $this->assertContains('threeuser@company.tld', $actual);
    }
}
