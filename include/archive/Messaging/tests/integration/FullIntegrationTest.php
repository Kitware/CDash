<?php

class FullIntegrationTest extends \CDash\Test\CDashTestCase
{
    /*
     * Definitions:
     *   1] ActionableBuildInterface - This is an xml handler that results in some sort of action
     *                                 based on its type. For instance if the type is a Test Failure
     *                                 the action is to email users who have subscribed to that
     *                                 action.
     * Directions:
     *   1] Create (or mock) an ActionableBuildInterface handler of type Test Failure
     *   2] Use the mocked handler to create a collection of subscribers
     *   3] User the mocked handler to create a collection of notifications
     *   4] Mock the notification transport to insure that all of the steps above result in the
     *      transport being called with the proper arguments for each subscribed user.
     */

    public function testActionIsNotifySubscribersOfTestFailure()
    {
        $handler = $this->getMockBuilder('ActionableBuildInterface')
            ->setMethods(['getActionableBuilds', 'getType', 'getBuildGroupId', 'getProjectId'])
            ->getMockForAbstractClass();

        $handler
            ->expects($this->any())
            ->method('getType')
            ->willReturn(ActionableBuildInterface::TYPE_TEST);

        $handler
            ->expects($this->any())
            ->method('getProjectId')
            ->willReturn('101');

        $handler
            ->expects($this->any())
            ->method('getBuildGroupId')
            ->willReturn('201');

        $project = $this->getMockProject();

        $project
            ->expects($this->any())
            ->method('GetProjectSubscribers')
            ->willReturn([]);
    }
}
