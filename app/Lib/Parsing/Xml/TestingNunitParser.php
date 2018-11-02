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

namespace CDash\Lib\Parsing\Xml;

use CDash\Model\Build;
use CDash\Model\BuildInformation;
use CDash\Model\BuildTest;
use CDash\Model\Site;
use CDash\Model\SiteInformation;
use CDash\Model\Test;

/*
 * app
 *   - Lib
 *     - Parser
 *        - Bazel
 *        - CTest
 *        - JUnit
 *        - NUnit
 *
 *
 */
/**
 * Class TestingNunitParser
 * @package CDash\Lib\Parsing\Xml
 */
class TestingNunitParser extends AbstractXmlParser
{
    private $updateEndTime; // should we update the end time of the build

    private $test;
    private $buildTest;
    private $append;

    // Keep a record of the number of tests passed, failed and notrun
    // This works only because we have one test file per submission
    private $numberTestsFailed;
    private $numberTestsNotRun;
    private $numberTestsPassed;
    private $hasSiteTag;
    private $buildAdded;

    /**
     * TestingNUnitParser constructor.
     * @param $projectId
     */
    public function __construct($projectId)
    {
        parent::__construct($projectId);
        $this->build = $this->getInstance(Build::class);
        $this->site = $this->getInstance(Site::class);
        $this->updateEndTime = false;
        $this->numberTestsFailed = 0;
        $this->numberTestsNotRun = 0;
        $this->numberTestsPassed = 0;
        $this->hasSiteTag = false;
        $this->buildAdded = false;
    }

    /**
     * @param $parser
     * @param $name
     * @param $attributes
     * @return mixed|void
     */
    public function startElement($parser, $name, $attributes)
    {
        parent::startElement($parser, $name, $attributes);

        if ($name == 'SITE') {
            $this->hasSiteTag = true;
            $this->site->Name = $attributes['NAME'];
            if (empty($this->site->Name)) {
                $this->site->Name = '(empty)';
            }
            $this->site->Insert();

            $siteInformation = $this->getInstance(SiteInformation::class);
            $buildInformation = $this->getInstance(BuildInformation::class);

            // Fill in the attribute
            foreach ($attributes as $key => $value) {
                $siteInformation->SetValue($key, $value);
                $buildInformation->SetValue($key, $value);
            }

            $this->site->SetInformation($siteInformation);

            $this->build->SiteId = $this->site->Id;
            $this->build->Name = $attributes['BUILDNAME'];
            if (empty($this->build->Name)) {
                $this->build->Name = '(empty)';
            }
            $this->build->SetStamp($attributes['BUILDSTAMP']);
            $this->build->Generator = $attributes['GENERATOR'];
            $this->build->Information = $buildInformation;

            if (array_key_exists('APPEND', $attributes)) {
                $this->append = $attributes['APPEND'];
            } else {
                $this->append = false;
            }
        } elseif ($name == 'TEST-CASE' && count($attributes) > 0) {
            $this->test = $this->getInstance(Test::class);
            $this->test->ProjectId = $this->projectId;
            $this->buildTest = $this->getInstance(BuildTest::class);

            $teststatus = 'notrun';
            if ($attributes['RESULT'] == 'Success') {
                $teststatus = 'passed';
            } elseif ($attributes['RESULT'] == 'Failure') {
                $teststatus = 'failed';
            }

            $this->buildTest->Status = $teststatus;

            if ($teststatus == 'passed') {
                $this->numberTestsPassed++;
            } elseif ($teststatus == 'failed') {
                $this->numberTestsFailed++;
            } elseif ($teststatus == 'notrun') {
                $this->numberTestsNotRun++;
            }

            if (isset($attributes['TIME'])) {
                $this->buildTest->Time = $attributes['TIME'];
            }

            if (isset($attributes['ASSERTS'])) {
                $this->test->Details = $attributes['ASSERTS'];
            }

            $this->test->Name = $attributes['NAME'];
            $this->test->Path = $attributes['NAME'];
            $this->test->Command = $attributes['NAME'];
        } elseif ($name == 'TEST-RESULTS') {
            $this->startTimeStamp = strtotime($attributes['DATE'] . ' ' . $attributes['TIME']);
            $this->endTimeStamp = $this->startTimeStamp;
        } elseif ($this->hasSiteTag == false && $name == 'ENVIRONMENT') {
            // If the XML file doesn't have a <Site> tag then we use the information
            // provided by the testsuite.
            // buildname is 'name'
            // sitename is 'hostname'
            // timestamp is 'timestamp'
            $this->site->Name = $attributes['MACHINE-NAME'];
            $this->site->Insert();

            $buildInformation = $this->getInstance(BuildInformation::class);
            $buildInformation->OSName = $attributes['PLATFORM'];
            $buildInformation->OSVersion = $attributes['OS-VERSION'];
            $buildInformation->CompilerName = 'CLR';
            $buildInformation->CompilerVersion = $attributes['CLR-VERSION'];

            $this->build->SiteId = $this->site->Id;
            $this->build->Name = $attributes['PLATFORM'];

            // Construct a CMake-Like build stamp
            // We assume Nightly
            $stamp = date('Ymd-Hi', $this->startTimeStamp) . '-Nightly';
            $this->build->SetStamp($stamp);
            $this->build->Information = $buildInformation;
            $this->append = false;
        } elseif ($this->buildAdded == false && $name == 'TEST-SUITE') {
            $this->build->SaveTotalTestsTime(0);

            $start_time = gmdate(FMT_DATETIME, $this->startTimeStamp);
            $this->build->ProjectId = $this->projectId;
            $this->build->GetIdFromName($this->subProjectName);
            $this->build->RemoveIfDone();

            // If the build doesn't exist we add it
            if ($this->build->Id == 0) {
                $this->build->ProjectId = $this->projectId;
                $this->build->StartTime = $start_time;
                $this->build->EndTime = $start_time;
                $this->build->SubmitTime = gmdate(FMT_DATETIME);
                $this->build->SetSubProject($this->subProjectName);
                $this->build->Append = $this->append;
                $this->build->InsertErrors = false;
                add_build($this->build);

                $this->updateEndTime = true;
            } else {
                // Otherwise make sure that the build is up-to-date.
                $this->build->UpdateBuild($this->build->Id, -1, -1);

                //if the build already exists factor the number of tests that have
                //already been run into our running total
                $this->numberTestsFailed += $this->build->GetNumberOfFailedTests();
                $this->numberTestsNotRun += $this->build->GetNumberOfNotRunTests();
                $this->numberTestsPassed += $this->build->GetNumberOfPassedTests();
            }

            $GLOBALS['PHP_ERROR_BUILD_ID'] = $this->build->Id;
            $this->buildAdded = true;
        }
    }

    /**
     * @param $parser
     * @param $name
     * @return mixed|void
     */
    public function endElement($parser, $name)
    {
        parent::endElement($parser, $name);

        if ($name == 'TEST-CASE') {
            $this->test->Insert();
            if ($this->test->Id > 0) {
                $this->buildTest->TestId = $this->test->Id;
                $this->buildTest->BuildId = $this->build->Id;
                $this->buildTest->Insert();

                $this->test->InsertLabelAssociations($this->build->Id);
            } else {
                add_log('Cannot insert test', 'Test XML parser', LOG_ERR,
                    $this->projectId, $this->build->Id);
            }
        } elseif ($name == 'SITE' || ($this->hasSiteTag == false && $name == 'TEST-RESULTS')) {
            if (strlen($this->endTimeStamp) > 0 && $this->updateEndTime) {
                $end_time = gmdate(FMT_DATETIME, $this->endTimeStamp); // The EndTimeStamp
                $this->build->UpdateEndTime($end_time);
            }

            // Update the number of tests in the Build table
            $this->build->UpdateTestNumbers($this->numberTestsPassed,
                $this->numberTestsFailed,
                $this->numberTestsNotRun);
            $this->build->ComputeTestTiming();
        }
    }

    /**
     * @param $parser
     * @param $data
     * @return mixed|void
     */
    public function text($parser, $data)
    {
    }
}
