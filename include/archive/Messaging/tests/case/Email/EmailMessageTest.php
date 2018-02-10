<?php

use CDash\Messaging\Collection\BuildCollection;
use CDash\Messaging\Email\EmailMessage;

class EmailMessageTest extends PHPUnit_Framework_TestCase
{
    public function testGetRecipients()
    {
        $project = $this->getMock('Project', ['GetProjectUsers'], [], '', false);
        $build1 = $this->getMock('Build', ['GetCommitAuthors'], [], '', false);
        $build2 = $this->getMock('Build', ['GetCommitAuthors'], [], '', false);
        $build1->Name = 'BUILDONE';
        $build2->Name = 'BUILDTWO';

        $buildCollection = new BuildCollection();
        $buildCollection->add($build1);
        $buildCollection->add($build2);

        $sut = new EmailMessage($project, null, $buildCollection);

        $projectUser = $this->getMock('UserProject', [], [], '', false);
        $build1author1 = $this->getMock('UserProject', [], [], '', false);
        $build1author2 = $this->getMock('UserProject', [], [], '', false);
        $build2author1 = $this->getMock('UserProject', [], [], '', false);
        $build2author2 = $this->getMock('UserProject', [], [], '', false);

        $projectUsers = [
            'projectuser@tld.com' => $projectUser,
        ];

        $build1Authors = [
            'build1author1@tld.com' => $build1author1,
            'build1author2@tld.com' => $build1author2,
        ];

        $build2Authors = [
            'build2author1@tld.com' => $build2author1,
            'build2author2@tld.com' => $build2author2,
        ];

        $project
            ->expects($this->once())
            ->method('GetProjectUsers')
            ->willReturn($projectUsers);

        $build1
            ->expects($this->once())
            ->method('GetCommitAuthors')
            ->with($this->equalTo(false))
            ->willReturn($build1Authors);

        $build2
            ->expects($this->once())
            ->method('GetCommitAuthors')
            ->with($this->equalTo(false))
            ->willReturn($build2Authors);

        $expected = [
            'projectuser@tld.com' => $projectUser,
            'build1author1@tld.com' => $build1author1,
            'build1author2@tld.com' => $build1author2,
            'build2author1@tld.com' => $build2author1,
            'build2author2@tld.com' => $build2author2,
        ];

        $actual = $sut->getRecipients(false);

        $this->assertEquals($expected, $actual);
    }
}
