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

namespace CDash\Lib\Parsing\JUnit;


use CDash\Lib\Parsing\Xml\AbstractXmlParser;
use CDash\Model\Build;
use CDash\Model\BuildInformation;
use CDash\Model\BuildTest;
use CDash\Model\Site;
use CDash\Model\SiteInformation;
use CDash\Model\Test;

class TestingParser extends AbstractXmlParser
{
    // Should we update the end time of the build?
    private $updateEndTime;
    // The buildgroup to submit to (defaults to Nightly).
    private $track;

    private $tests;
    private $currentTest;
    private $buildTests;
    private $currentBuildTest;
    private $append;

    // Keep a record of the number of tests passed, failed and notrun.
    // This works only because we have one test file per submission.
    private $numberTestsFailed;
    private $numberTestsNotRun;
    private $numberTestsPassed;
    private $testProperties;
    private $hasSiteTag;
    private $buildAdded;
    private $totalTestDuration;


    /** Constructor */
    public function __construct($projectId)
    {
        parent::__construct($projectId);
        $this->build = $this->getInstance(Build::class);
        $this->site = $this->getInstance(Site::class);
        $this->buildTests = [];
        $this->tests = [];

        $this->updateEndTime = false;
        $this->track = 'Nightly';
        $this->numberTestsFailed = 0;
        $this->numberTestsNotRun = 0;
        $this->numberTestsPassed = 0;
        $this->testProperties = '';
        $this->hasSiteTag = false;
        $this->buildAdded = false;
        $this->totalTestDuration = 0;
    }

    /** Start Element */
    public function startElement($parser, $name, $attributes)
    {
        parent::startElement($parser, $name, $attributes);
        $parent = $this->getParent(); // should be before endElement

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
        } elseif ($name == 'FAILURE' || $name == 'ERROR') {
            $this->currentTest->Details = $attributes['TYPE'];
        } elseif ($name == 'PROPERTY' && $parent == 'PROPERTIES') {
            $this->testProperties .= $attributes['NAME'] . '=' . $attributes['VALUE'] . "\n";
            if ($this->hasSiteTag == false) {
                switch ($attributes['NAME']) {
                    case 'os.name':
                        $this->build->Information->OSName = $attributes['VALUE'];
                        break;
                    case 'os.version':
                        $this->build->Information->OSVersion = $attributes['VALUE'];
                        break;
                    case 'java.vm.name':
                        $this->build->Information->CompilerName = $attributes['VALUE'];
                        break;
                    case 'java.vm.version':
                        $this->build->Information->CompilerVersion = $attributes['VALUE'];
                        break;
                    case 'hostname':
                        if (empty($this->site->Name)) {
                            $this->site->Name = $attributes['VALUE'];
                            $this->site->Insert();
                            $this->build->SiteId = $this->site->Id;
                        }
                        break;
                    case 'track':
                        $this->track = $attributes['VALUE'];
                        break;
                }
            }
        } elseif ($name == 'TESTCASE' && count($attributes) > 0) {
            $this->currentTest = $this->getInstance(Test::class);
            $this->currentTest->Command = $this->testProperties;
            $this->currentTest->ProjectId = $this->projectId;
            $this->currentBuildTest = $this->getInstance(BuildTest::class);

            if (isset($attributes['TIME'])) {
                $this->currentBuildTest->Time = $attributes['TIME'];
            }

            // Default is that the test passes.
            $this->currentBuildTest->Status = 'passed';
            if (array_key_exists('STATUS', $attributes)) {
                $status = $attributes['STATUS'];
                if (stripos($status, 'fail') !== false) {
                    $this->currentBuildTest->Status = 'failed';
                }
                if (strcasecmp($status, 'notrun') === 0) {
                    $this->currentBuildTest->Status = 'notrun';
                }
            }

            $this->currentTest->Name = $attributes['NAME'];
            $this->currentTest->Path = $attributes['CLASSNAME'];
        } elseif ($name == 'TESTSUITE') {
            // If the XML file doesn't have a <Site> tag then we use the information
            // provided by the testsuite.
            // buildname is 'name'
            // sitename is 'hostname'
            // timestamp is 'timestamp'
            if ($this->hasSiteTag == false) {
                // Hostname is not necessarily defined
                if (!empty($attributes['HOSTNAME'])) {
                    $this->site->Name = $attributes['HOSTNAME'];
                    $this->site->Insert();
                    $this->build->SiteId = $this->site->Id;
                }

                $this->build->Information = $this->getInstance(BuildInformation::class);
                $this->build->Name = $attributes['NAME'];

                // If the TIMESTAMP attribute is not defined we take the current timestamp.
                if (!isset($attributes['TIMESTAMP'])) {
                    $timestamp = time();
                } else {
                    $timestamp = strtotime($attributes['TIMESTAMP']);
                }
                $this->append = false;
            } elseif (!isset($attributes['TIMESTAMP'])) {
                $stamp = $this->build->GetStamp();
                $timestamp = mktime(substr($stamp, 9, 2), substr($stamp, 11, 2), 0,
                    substr($stamp, 6, 2), substr($stamp, 4, 2), substr($stamp, 0, 4));
            } else {
                $timestamp = 0;
            }

            $this->startTimeStamp = $timestamp;
            $this->endTimeStamp = $this->startTimeStamp + $attributes['TIME'];
            $this->totalTestDuration += $attributes['TIME'];
        }
    }

    /** End Element */
    public function endElement($parser, $name)
    {
        parent::endElement($parser, $name);
        if ($name == 'FAILURE' || $name == 'ERROR') {
            // Mark this test as failed if it has a <failure> or <error> tag.
            $this->currentBuildTest->Status = 'failed';
        } elseif ($name == 'TESTCASE') {
            // Update our tally of passing/failing/notrun tests.
            if ($this->currentBuildTest->Status == 'passed') {
                $this->numberTestsPassed++;
            } elseif ($this->currentBuildTest->Status == 'failed') {
                $this->numberTestsFailed++;
            } elseif ($this->currentBuildTest->Status == 'notrun') {
                $this->numberTestsNotRun++;
            }
            // Record this test in the database.
            $this->currentTest->Insert();
            if ($this->currentTest->Id > 0) {
                $this->currentBuildTest->TestId = $this->currentTest->Id;
                $this->tests[] = $this->currentTest;
                $this->buildTests[] = $this->currentBuildTest;
            } else {
                add_log('Cannot insert test', 'Test XML parser', LOG_ERR,
                    $this->projectId, $this->build->Id);
            }
        } elseif ($name == 'SITE' || ($this->hasSiteTag == false && $name == 'TESTSUITE')) {
            if (strlen($this->endTimeStamp) > 0 && $this->updateEndTime) {
                $end_time = gmdate(FMT_DATETIME, $this->endTimeStamp); // The EndTimeStamp
                $this->build->UpdateEndTime($end_time);
            }

            // Add the build if necessary.
            if ($this->buildAdded == false) {
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
                    if ($this->hasSiteTag == false) {
                        // Construct the build stamp.
                        $stamp = date('Ymd-Hi', $this->startTimeStamp) . "-$this->track";
                        $this->build->SetStamp($stamp);
                    }
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

            // Add the tests for this build.
            foreach ($this->buildTests as $buildtest) {
                $buildtest->BuildId = $this->build->Id;
                $buildtest->Insert();
            }

            // Set any label associations for tests.
            foreach ($this->tests as $test) {
                $test->InsertLabelAssociations($this->build->Id);
            }

            // Update the number of tests in the Build table
            $this->build->UpdateTestNumbers($this->numberTestsPassed,
                $this->numberTestsFailed,
                $this->numberTestsNotRun);

            $this->build->SaveTotalTestsTime($this->totalTestDuration);
            $this->build->ComputeTestTiming();
        }
    }

    /** Text function */
    public function text($parser, $data)
    {
        $element = $this->getElement();
        if ($element == 'FAILURE') {
            $this->currentTest->Output .= $data;
        }
    }
}
