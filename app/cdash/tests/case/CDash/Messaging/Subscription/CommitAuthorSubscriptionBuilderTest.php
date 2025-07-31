<?php

/**
 * =========================================================================
 *   Program:   CDash - Cross-Platform Dashboard System
 *   Module:    $Id$
 *   Language:  PHP
 *   Date:      $Date$
 *   Version:   $Revision$
 *   Copyright (c) Kitware, Inc. All rights reserved.
 *   See LICENSE or http://www.cdash.org/licensing/ for details.
 *   This software is distributed WITHOUT ANY WARRANTY; without even
 *   the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
 *   PURPOSE. See the above copyright notices for more information.
 * =========================================================================
 */

use App\Http\Submission\Handlers\ActionableBuildInterface;
use App\Http\Submission\Handlers\BuildHandler;
use App\Http\Submission\Handlers\TestingHandler;
use App\Models\Site;
use CDash\Collection\BuildCollection;
use CDash\Messaging\Subscription\CommitAuthorSubscriptionBuilder;
use CDash\Messaging\Subscription\SubscriptionCollection;
use CDash\Model\Build;
use CDash\Model\BuildGroup;
use CDash\Model\Project;
use CDash\Test\BuildDiffForTesting;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class CommitAuthorSubscriptionBuilderTest extends TestCase
{
    use BuildDiffForTesting;

    public function testBuildGivenSubmissionWithBuildErrors()
    {
        $subscriptions = new SubscriptionCollection();
        $this->assertCount(0, $subscriptions);

        $key = 'builderrorspositive';
        $mock_submission = $this->getMockSubmission($key, BuildHandler::class);
        $mock_submission->expects($this->any())
            ->method('GetTopicCollectionForSubscriber')
            ->willReturnCallback(function ($subscriber) {
                $project = new Project();
                $project->Id = 0;
                $handler = new BuildHandler($project);
                return $handler->GetTopicCollectionForSubscriber($subscriber);
            });

        $sut = new CommitAuthorSubscriptionBuilder($mock_submission);
        $sut->build($subscriptions);

        $this->assertCount(2, $subscriptions);
        $this->assertTrue($subscriptions->has('com.mitter@company.tld'));
        $this->assertTrue($subscriptions->has('auth.or@company.tld'));
    }

    public function testBuildGivenSubmissionWithBuildWarnings()
    {
        $subscriptions = new SubscriptionCollection();
        $this->assertCount(0, $subscriptions);

        $key = 'buildwarningspositive';
        $mock_submission = $this->getMockSubmission($key, BuildHandler::class);
        $mock_submission->expects($this->any())
            ->method('GetTopicCollectionForSubscriber')
            ->willReturnCallback(function ($subscriber) {
                $project = new Project();
                $project->Id = 0;
                $handler = new BuildHandler($project);
                return $handler->GetTopicCollectionForSubscriber($subscriber);
            });

        $sut = new CommitAuthorSubscriptionBuilder($mock_submission);
        $sut->build($subscriptions);

        $this->assertEmpty($subscriptions);
        $this->assertFalse($subscriptions->has('com.mitter@company.tld'));
        $this->assertFalse($subscriptions->has('auth.or@compnay.tld'));
    }

    public function testBuildGivenSubmissionWithTestFailures()
    {
        $subscriptions = new SubscriptionCollection();
        $this->assertCount(0, $subscriptions);

        $key = 'testfailedpositive';
        $mock_submission = $this->getMockSubmission($key, TestingHandler::class);
        $mock_submission->expects($this->any())
            ->method('GetTopicCollectionForSubscriber')
            ->willReturnCallback(function ($subscriber) {
                $project = new Project();
                $project->Id = 0;
                $handler = new TestingHandler($project);
                return $handler->GetTopicCollectionForSubscriber($subscriber);
            });

        $sut = new CommitAuthorSubscriptionBuilder($mock_submission);
        $sut->build($subscriptions);

        $this->assertCount(2, $subscriptions);
        $this->assertTrue($subscriptions->has('com.mitter@company.tld'));
        $this->assertTrue($subscriptions->has('auth.or@company.tld'));
    }

    /**
     * @return ActionableBuildInterface|MockObject
     */
    public function getMockSubmission($key, $handler_class)
    {
        /** @var Project $mock_project */
        $mock_project = $this->getMockBuilder(Project::class)
            ->getMock();

        $mock_site = Mockery::mock(Site::class);

        /** @var Build|MockObject $mock_build */
        $mock_build = $this->createMockBuildWithDiff(
            $this->createNew($key)
        );

        /** @var BuildGroup|MockObject $mock_group */
        $mock_group = $this->getMockBuilder(BuildGroup::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isNotifyingCommitters'])
            ->getMock();

        $mock_group->expects($this->any())
            ->method('isNotifyingCommitters')
            ->willReturn(true);

        /** @var ActionableBuildInterface|MockObject $mock_handler */
        $mock_handler = $this->getMockBuilder($handler_class)
            ->disableOriginalConstructor()
            ->onlyMethods(['GetProject', 'GetSite', 'GetBuildCollection', 'GetCommitAuthors', 'GetBuildGroup', 'GetTopicCollectionForSubscriber'])
            ->getMock();

        $mock_handler->expects($this->any())
            ->method('GetProject')
            ->willReturn($mock_project);

        $mock_handler->expects($this->any())
            ->method('GetSite')
            ->willReturn($mock_site);

        $mock_handler->expects($this->any())
            ->method('GetBuildCollection')
            ->willReturn((new BuildCollection())
                ->add($mock_build));

        $mock_handler->expects($this->any())
            ->method('GetCommitAuthors')
            ->willReturn($this->getCommitAuthors());

        $mock_handler->expects($this->any())
            ->method('GetBuildGroup')
            ->willReturn($mock_group);

        return $mock_handler;
    }

    public function getCommitAuthors(): array
    {
        return ['com.mitter@company.tld', 'auth.or@company.tld'];
    }
}
