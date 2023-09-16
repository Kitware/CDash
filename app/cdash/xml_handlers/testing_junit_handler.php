<?php
/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

require_once 'xml_handlers/abstract_handler.php';

use App\Services\TestCreator;

use CDash\Model\Build;
use App\Models\BuildInformation;
use App\Models\Site;
use App\Models\SiteInformation;

class TestingJUnitHandler extends AbstractHandler
{
    private $StartTimeStamp;
    private $EndTimeStamp;
    // Should we update the end time of the build?
    private $UpdateEndTime;
    // The buildgroup to submit to (defaults to Nightly).
    private $Group;

    // Keep a record of the number of tests passed, failed and notrun.
    // This works only because we have one test file per submission.
    private $NumberTestsFailed;
    private $NumberTestsNotRun;
    private $NumberTestsPassed;
    private $TestProperties;
    private $HasSiteTag;
    private $BuildAdded;
    private $TotalTestDuration;

    /** Constructor */
    public function __construct($projectID)
    {
        parent::__construct($projectID);
        $this->Build = new Build();

        $this->UpdateEndTime = false;
        $this->Group = 'Nightly';
        $this->NumberTestsFailed = 0;
        $this->NumberTestsNotRun = 0;
        $this->NumberTestsPassed = 0;
        $this->TestProperties = '';
        $this->HasSiteTag = false;
        $this->BuildAdded = false;
        $this->TotalTestDuration = 0;
    }

    /** Destructor */
    public function __destruct()
    {
    }

    /** Start Element */
    public function startElement($parser, $name, $attributes)
    {
        parent::startElement($parser, $name, $attributes);
        $parent = $this->getParent(); // should be before endElement

        if ($name == 'SITE') {
            $this->HasSiteTag = true;
            $site_name = !empty($attributes['NAME']) ? $attributes['NAME'] : '(empty)';
            $this->Site = Site::firstOrCreate(['name' => $site_name], ['name' => $site_name]);

            $siteInformation = new SiteInformation();
            $buildInformation = new BuildInformation();

            // Fill in the attribute
            foreach ($attributes as $key => $value) {
                $siteInformation->SetValue($key, $value);
                $buildInformation->SetValue($key, $value);
            }

            $this->Site->mostRecentInformation()->save($siteInformation);

            $this->Build->SiteId = $this->Site->id;
            $this->Build->Name = $attributes['BUILDNAME'];
            if (empty($this->Build->Name)) {
                $this->Build->Name = '(empty)';
            }

            $this->Build->SetStamp($attributes['BUILDSTAMP']);
            $this->Build->Generator = $attributes['GENERATOR'];
            $this->Build->Information = $buildInformation;
        } elseif ($name == 'FAILURE' || $name == 'ERROR') {
            $this->TestCreator->testDetails = $attributes['TYPE'];
        } elseif ($name == 'PROPERTY' && $parent == 'PROPERTIES') {
            $this->TestProperties .= $attributes['NAME'] . '=' . $attributes['VALUE'] . "\n";
            if ($this->HasSiteTag == false) {
                switch ($attributes['NAME']) {
                    case 'os.name':
                        $this->Build->Information->osname = $attributes['VALUE'];
                        break;
                    case 'os.version':
                        $this->Build->Information->osversion = $attributes['VALUE'];
                        break;
                    case 'java.vm.name':
                        $this->Build->Information->compilername = $attributes['VALUE'];
                        break;
                    case 'java.vm.version':
                        $this->Build->Information->compilerversion = $attributes['VALUE'];
                        break;
                    case 'hostname':
                        if (empty($this->Site->name)) {
                            $this->Site = Site::firstOrCreate(['name' => $attributes['VALUE']], ['name' => $attributes['VALUE']]);
                            $this->Build->SiteId = $this->Site->id;
                        }
                        break;
                    case 'track':
                    case 'group':
                        $this->Group = $attributes['VALUE'];
                        break;
                }
            }
        } elseif ($name == 'TESTCASE' && count($attributes) > 0) {
            $this->TestCreator = new TestCreator;
            $this->TestCreator->testCommand = $this->TestProperties;
            $this->TestCreator->projectid = $this->projectid;

            if (isset($attributes['TIME'])) {
                $this->TestCreator->buildTestTime = $attributes['TIME'];
            }

            // Default is that the test passes.
            $this->TestCreator->testStatus = 'passed';
            if (array_key_exists('STATUS', $attributes)) {
                $status = $attributes['STATUS'];
                if (stripos($status, 'fail') !== false) {
                    $this->TestCreator->testStatus = 'failed';
                }
                if (strcasecmp($status, 'notrun') === 0) {
                    $this->TestCreator->testStatus = 'notrun';
                }
            }

            $this->TestCreator->setTestName($attributes['NAME']);
            $this->TestCreator->testPath = $attributes['CLASSNAME'];
        } elseif ($name == 'TESTSUITE') {
            // If the XML file doesn't have a <Site> tag then we use the information
            // provided by the testsuite.
            // buildname is 'name'
            // sitename is 'hostname'
            // timestamp is 'timestamp'
            if ($this->HasSiteTag == false) {
                // Hostname is not necessarily defined
                if (!empty($attributes['HOSTNAME'])) {
                    $this->Site = Site::firstOrCreate(['name' => $attributes['HOSTNAME']], ['name' => $attributes['HOSTNAME']]);
                    $this->Build->SiteId = $this->Site->id;
                }

                $this->Build->Information = new BuildInformation();
                $this->Build->Name = $attributes['NAME'];

                // If the TIMESTAMP attribute is not defined we take the current timestamp.
                if (!isset($attributes['TIMESTAMP'])) {
                    $timestamp = time();
                } else {
                    $timestamp = strtotime($attributes['TIMESTAMP']);
                }
                $this->Append = false;
            } elseif (!isset($attributes['TIMESTAMP'])) {
                $stamp = $this->Build->GetStamp();
                $timestamp = mktime(substr($stamp, 9, 2), substr($stamp, 11, 2), 0,
                    substr($stamp, 6, 2), substr($stamp, 4, 2), substr($stamp, 0, 4));
            } else {
                $timestamp = 0;
            }

            $this->StartTimeStamp = $timestamp;
            $this->EndTimeStamp = $this->StartTimeStamp + $attributes['TIME'];
            $this->TotalTestDuration += $attributes['TIME'];
        }
    }

    /** End Element */
    public function endElement($parser, $name)
    {
        parent::endElement($parser, $name);
        if ($name == 'FAILURE' || $name == 'ERROR') {
            // Mark this test as failed if it has a <failure> or <error> tag.
            $this->TestCreator->testStatus = 'failed';
        } elseif ($name == 'TESTCASE') {
            // At this point we should have enough information
            // to create a build if we haven't done so already.
            $this->createBuild();

            // Update our tally of passing/failing/notrun tests.
            if ($this->TestCreator->testStatus == 'passed') {
                $this->NumberTestsPassed++;
            } elseif ($this->TestCreator->testStatus == 'failed') {
                $this->NumberTestsFailed++;
            } elseif ($this->TestCreator->testStatus == 'notrun') {
                $this->NumberTestsNotRun++;
            }

            // Record this test in the database.
            $this->TestCreator->create($this->Build);
        } elseif ($name == 'SITE' || ($this->HasSiteTag == false && $name == 'TESTSUITE')) {
            if (strlen($this->EndTimeStamp) > 0 && $this->UpdateEndTime) {
                $end_time = gmdate(FMT_DATETIME, $this->EndTimeStamp); // The EndTimeStamp
                $this->Build->UpdateEndTime($end_time);
            }

            // Update the number of tests in the Build table
            $this->Build->UpdateTestNumbers($this->NumberTestsPassed,
                $this->NumberTestsFailed,
                $this->NumberTestsNotRun);

            $this->Build->UpdateTestDuration($this->TotalTestDuration);
            $this->Build->ComputeTestTiming();
        }
    }

    /** Text function */
    public function text($parser, $data)
    {
        $element = $this->getElement();
        if ($element == 'FAILURE') {
            $this->TestCreator->testOutput .= $data;
        }
    }

    private function createBuild()
    {
        // Add the build if necessary.
        if ($this->BuildAdded) {
            return;
        }
        $start_time = gmdate(FMT_DATETIME, $this->StartTimeStamp);
        $this->Build->ProjectId = $this->projectid;
        $this->Build->GetIdFromName($this->SubProjectName);
        $this->Build->RemoveIfDone();

        // If the build doesn't exist we add it
        if ($this->Build->Id == 0) {
            $this->Build->ProjectId = $this->projectid;
            $this->Build->StartTime = $start_time;
            $this->Build->EndTime = $start_time;
            $this->Build->SubmitTime = gmdate(FMT_DATETIME);
            $this->Build->SetSubProject($this->SubProjectName);
            $this->Build->Append = $this->Append;
            $this->Build->InsertErrors = false;
            if ($this->HasSiteTag == false) {
                // Construct the build stamp.
                $stamp = date('Ymd-Hi', $this->StartTimeStamp) . "-$this->Group";
                $this->Build->SetStamp($stamp);
            }
            add_build($this->Build);
            $this->UpdateEndTime = true;
        } else {
            // Otherwise make sure that the build is up-to-date.
            $this->Build->UpdateBuild($this->Build->Id, -1, -1);

            //if the build already exists factor the number of tests that have
            //already been run into our running total
            $this->NumberTestsFailed += $this->Build->GetNumberOfFailedTests();
            $this->NumberTestsNotRun += $this->Build->GetNumberOfNotRunTests();
            $this->NumberTestsPassed += $this->Build->GetNumberOfPassedTests();
        }
        $GLOBALS['PHP_ERROR_BUILD_ID'] = $this->Build->Id;
        $this->BuildAdded = true;
    }
}
