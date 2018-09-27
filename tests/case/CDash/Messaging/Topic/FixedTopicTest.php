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

use CDash\Messaging\Topic\BuildErrorTopic;
use CDash\Messaging\Topic\FixedTopic;
use CDash\Messaging\Topic\TestFailureTopic;
use CDash\Model\Build;
use CDash\Test\BuildDiffForTesting;

class FixedTopicTest extends \CDash\Test\CDashTestCase
{
    use BuildDiffForTesting;

    public function testSubscribesToBuildGivenNoFixes()
    {
        // BuildErrorTopic implements Fixable
        $buildTopic = new BuildErrorTopic();
        $sut = new FixedTopic($buildTopic);

        $build = $this->createMockBuildWithDiff($this->getDiff());
        $buildTopic->subscribesToBuild($build);

        $this->assertFalse($sut->subscribesToBuild($build));

        // TestFailureTopic implements Fixable
        $testTopic = new TestFailureTopic();
        $sut = new FixedTopic($testTopic);

        $testTopic->subscribesToBuild($build);

        $this->assertFalse($sut->subscribesToBuild($build));
    }

    public function testSubscribesToBuildGivenBuildErrorFix()
    {
        $buildTopic = new BuildErrorTopic();
        $sut = new FixedTopic($buildTopic);

        $build = $this->createMockBuildWithDiff($this->createFixed('builderrorsnegative'));
        $buildTopic->subscribesToBuild($build);

        $this->assertTrue($sut->subscribesToBuild($build));
    }

    public function testSubscribesToBuildGivenBuildWarningFix()
    {
        $buildTopic = new BuildErrorTopic();
        $buildTopic->setType(Build::TYPE_WARN);

        $sut = new FixedTopic($buildTopic);

        $build = $this->createMockBuildWithDiff($this->createFixed('buildwarningsnegative'));
        $buildTopic->subscribesToBuild($build);

        $this->assertTrue($sut->subscribesToBuild($build));
    }

    public function testSubscribesToBuildGivenTestFailureFix()
    {
        $testTopic = new TestFailureTopic();
        $sut = new FixedTopic($testTopic);

        $build = $this->createMockBuildWithDiff($this->createFixed('testfailednegative'));
        $testTopic->subscribesToBuild($build);

        $this->assertTrue($sut->subscribesToBuild($build));
    }

    public function testSubscribesToBuildGivenTestNotRunFix()
    {
        $testTopic = new TestFailureTopic();
        $sut = new FixedTopic($testTopic);

        $build = $this->createMockBuildWithDiff($this->createFixed('testnotrunnegative'));
        $testTopic->subscribesToBuild($build);

        $this->assertFalse($sut->subscribesToBuild($build));
    }
}
