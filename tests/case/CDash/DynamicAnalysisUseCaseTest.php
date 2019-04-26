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

use CDash\Model\Build;
use CDash\Model\DynamicAnalysis;
use CDash\Test\UseCase\UseCase;
use CDash\Test\CDashUseCaseTestCase;
use CDash\Test\UseCase\DynamicAnalysisUseCase;

class DynamicAnalysisUseCaseTest extends CDashUseCaseTestCase
{
    public function testUseCaseBuildsDynamicAnalysisUseCase()
    {
        $sut = UseCase::createBuilder($this, UseCase::DYNAMIC_ANALYSIS);
        $this->assertInstanceOf(DynamicAnalysisUseCase::class, $sut);
    }

    public function testDynamicAnalysisUseCase()
    {
        $sut = UseCase::createBuilder($this, UseCase::DYNAMIC_ANALYSIS)
            ->createSite([
                'Name' => 'Site.name',
                'BuildName' => 'CTestTest-Linux-c++-Subprojects',
                'BuildStamp' => '20160728-1932-Experimental',
                'Generator' => 'ctest-3.6.20160726-g3e55f-dirty',
            ])
            ->createSubproject('MyExperimentalFeature')
            ->createSubproject('MyProductionCode')
            ->createSubproject('MyThirdPartyDependency')
            ->setChecker('/usr/bin/valgrind')
            ->createFailedTest('experimentalFail', ['Labels' => ['MyExperimentalFeature']])
            ->createPassedTest(
                'thirdparty',
                ['Labels' =>
                    ['MyThirdPartyDependency', 'NotASubproject']
                ]
            );

        /** @var DynamicAnalysisHandler $handle */
        $handle = $sut->build();
        $this->assertInstanceOf(DynamicAnalysisHandler::class, $handle);

        $builds = $handle->GetBuildCollection();
        $this->assertCount(3, $builds);

        /** @var Build $build */
        $build = $builds->get('MyExperimentalFeature');
        $this->assertInstanceOf(Build::class, $build);

        $collection = $build->GetDynamicAnalysisCollection();
        $this->assertCount(1, $collection);

        /** @var DynamicAnalysis $failed */
        $failed = $collection->get('experimentalFail');
        $this->assertEquals(DynamicAnalysis::FAILED, $failed->Status);

        $build = $builds->get('MyThirdPartyDependency');
        $this->assertInstanceOf(Build::class, $build);

        $collection = $build->GetDynamicAnalysisCollection();
        $this->assertCount(1, $collection);

        /** @var DynamicAnalysis $passed */
        $passed = $collection->get('thirdparty');
        $this->assertEquals(DynamicAnalysis::PASSED, $passed->Status);
    }
}
