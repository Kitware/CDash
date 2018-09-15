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

use CDash\Config;
use CDash\Messaging\Notification\Email\Decorator\DynamicAnalysisDecorator;
use CDash\Messaging\Topic\DynamicAnalysisTopic;
use CDash\Model\Build;
use CDash\Model\DynamicAnalysis;

class DynamicAnalysisDecoratorTest extends PHPUnit_Framework_TestCase
{
    public function testSetTopic()
    {
        $baseUrl = Config::getInstance()->getBaseUrl();
        $build = new Build();

        $da1 = new DynamicAnalysis();
        $da2 = new DynamicAnalysis();
        $da3 = new DynamicAnalysis();

        $da1->Status = DynamicAnalysis::FAILED;
        $da2->Status = DynamicAnalysis::NOTRUN;
        $da3->Status = DynamicAnalysis::PASSED;

        $da1->Name = 'Test One';
        $da2->Name = 'Test Two';

        $da1->Id = 101;
        $da2->Id = 102;

        $build->AddDynamicAnalysis($da1)
            ->AddDynamicAnalysis($da2)
            ->AddDynamicAnalysis($da3);

        $topic = (new DynamicAnalysisTopic());
        $topic->setTopicData($build);

        $sut = (new DynamicAnalysisDecorator())
            ->setMaxTopicItems(5);

        $expected = implode(PHP_EOL, [
            '',
            '*Dynamic analysis tests failing or not run*',
            "Test One ({$baseUrl}/viewDynamicAnalysisFile.php?id=101)",
            "Test Two ({$baseUrl}/viewDynamicAnalysisFile.php?id=102)",
            '',
        ]);

        $actual = $sut->setTopic($topic);
        $this->assertEquals($expected, $actual);
    }
}
