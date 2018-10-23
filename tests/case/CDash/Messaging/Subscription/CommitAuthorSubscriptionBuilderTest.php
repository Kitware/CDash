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

use CDash\Collection\BuildCollection;
use CDash\Messaging\Subscription\CommitAuthorSubscriptionBuilder;
use CDash\Messaging\Subscription\SubscriptionCollection;
use CDash\Model\Build;
use CDash\Model\Project;
use CDash\Model\Site;
use CDash\Test\BuildDiffForTesting;

class CommitAuthorSubscriptionBuilderTest extends PHPUnit_Framework_TestCase
{
    use BuildDiffForTesting;

    public function testBuildGivenSubmissionWithBuildErrors()
    {
        $subscriptions = new SubscriptionCollection();
        $this->assertCount(0, $subscriptions);

        $key = 'builderrorspositive';
        $mock_submission = $this->getMockSubmission($key);
        $mock_submission->expects($this->any())
            ->method('GetTopicCollectionForSubscriber')
            ->willReturnCallback(function ($subscriber) {
                $handler = new BuildHandler(0, 0);
                return $handler->GetTopicCollectionForSubscriber($subscriber);
            });

        $sut = new CommitAuthorSubscriptionBuilder($mock_submission);
        $sut->build($subscriptions);

        $this->assertCount(2, $subscriptions);
        $this->assertTrue($subscriptions->has('com.mitter@company.tld'));
        $this->assertTrue($subscriptions->has('auth.or@company.tld'));
    }

    public function testBuildGivenSubmissionWithTestFailures()
    {
        $subscriptions = new SubscriptionCollection();
        $this->assertCount(0, $subscriptions);

        $key = 'testfailedpositive';
        $mock_submission = $this->getMockSubmission($key);
        $mock_submission->expects($this->any())
            ->method('GetTopicCollectionForSubscriber')
            ->willReturnCallback(function ($subscriber) {
                $handler = new TestingHandler(0, 0);
                return $handler->GetTopicCollectionForSubscriber($subscriber);
            });

        $sut = new CommitAuthorSubscriptionBuilder($mock_submission);
        $sut->build($subscriptions);

        $this->assertCount(2, $subscriptions);
        $this->assertTrue($subscriptions->has('com.mitter@company.tld'));
        $this->assertTrue($subscriptions->has('auth.or@company.tld'));
    }

    /**
     * @return ActionableBuildInterface
     */
    public function getMockSubmission($key)
    {
        /** @var Project $mock_project */
        $mock_project = $this->getMockBuilder(Project::class)
            ->getMock();

        /** @var Site $mock_site */
        $mock_site = $this->getMockBuilder(Site::class)
            ->getMock();

        /** @var Build|PHPUnit_Framework_MockObject_MockObject $mock_build */
        $mock_build = $this->createMockBuildWithDiff(
            $this->createNew($key)
        );

        /** @var ActionableBuildInterface|PHPUnit_Framework_MockObject_MockObject $mock_handler */
        $mock_handler = $this->getMockBuilder(ActionableBuildInterface::class)
            ->getMockForAbstractClass();

        $mock_handler->expects($this->any())
            ->method('GetProject')
            ->willReturn($mock_project);

        $mock_handler->expects($this->any())
            ->method('GetSite')
            ->willReturn($mock_site);

        $mock_handler->expects($this->any())
            ->method('GetBuildCollection')
            ->willReturn((new BuildCollection)
                ->add($mock_build));

        $mock_handler->expects($this->any())
            ->method('GetCommitAuthors')
            ->willReturn($this->getCommitAuthors());

        return $mock_handler;
    }

    public function getCommitAuthors()
    {
        return ['com.mitter@company.tld', 'auth.or@company.tld'];
    }
}
