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
require_once 'models/build.php';
require_once 'models/label.php';
require_once 'models/site.php';
require_once 'models/test.php';
require_once 'models/image.php';

class TestingJUnitHandler extends AbstractHandler
{
    private $StartTimeStamp;
    private $EndTimeStamp;
    private $UpdateEndTime; // should we update the end time of the build

    private $Test;
    private $BuildTest;
    private $Append;

    // Keep a record of the number of tests passed, failed and notrun
    // This works only because we have one test file per submission
    private $NumberTestsFailed;
    private $NumberTestsNotRun;
    private $NumberTestsPassed;
    private $TestProperties;
    private $HasSiteTag;
    private $BuildAdded;

    /** Constructor */
    public function __construct($projectID, $scheduleID)
    {
        parent::__construct($projectID, $scheduleID);
        $this->Build = new Build();
        $this->Site = new Site();
        $this->UpdateEndTime = false;
        $this->NumberTestsFailed = 0;
        $this->NumberTestsNotRun = 0;
        $this->NumberTestsPassed = 0;
        $this->TestProperties = '';
        $this->HasSiteTag = false;
        $this->BuildAdded = false;
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
            $this->Site->Name = $attributes['NAME'];
            if (empty($this->Site->Name)) {
                $this->Site->Name = '(empty)';
            }
            $this->Site->Insert();

            $siteInformation = new SiteInformation();
            $buildInformation = new BuildInformation();

            // Fill in the attribute
            foreach ($attributes as $key => $value) {
                $siteInformation->SetValue($key, $value);
                $buildInformation->SetValue($key, $value);
            }

            $this->Site->SetInformation($siteInformation);

            $this->Build->SiteId = $this->Site->Id;
            $this->Build->Name = $attributes['BUILDNAME'];
            if (empty($this->Build->Name)) {
                $this->Build->Name = '(empty)';
            }

            $this->Build->SetStamp($attributes['BUILDSTAMP']);
            $this->Build->Generator = $attributes['GENERATOR'];
            $this->Build->Information = $buildInformation;

            if (array_key_exists('APPEND', $attributes)) {
                $this->Append = $attributes['APPEND'];
            } else {
                $this->Append = false;
            }
        } elseif ($name == 'FAILURE') {
            $this->Test->Details = $attributes['TYPE'];
        } elseif ($name == 'PROPERTY' && $parent == 'PROPERTIES') {
            $this->TestProperties .= $attributes['NAME'] . '=' . $attributes['VALUE'] . "\n";
            if ($this->HasSiteTag == false) {
                switch ($attributes['NAME']) {
                    case 'os.name':
                        $this->Build->Information->OSName = $attributes['VALUE'];
                        break;
                    case 'os.version':
                        $this->Build->Information->OSVersion = $attributes['VALUE'];
                        break;
                    case 'java.vm.name':
                        $this->Build->Information->CompilerName = $attributes['VALUE'];
                        break;
                    case 'java.vm.version':
                        $this->Build->Information->CompilerVersion = $attributes['VALUE'];
                        break;
                    case 'hostname':
                        if (empty($this->Site->Name)) {
                            $this->Site->Name = $attributes['VALUE'];
                            $this->Site->Insert();
                            $this->Build->SiteId = $this->Site->Id;
                        }
                        break;
                }
            }
        } elseif ($name == 'TESTCASE' && count($attributes) > 0) {
            if ($this->BuildAdded == false) {
                $this->Build->SaveTotalTestsTime(0);

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
                    add_build($this->Build, $this->scheduleid);

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

            $this->Test = new Test();
            $this->Test->Command = $this->TestProperties;
            $this->Test->ProjectId = $this->projectid;
            $this->BuildTest = new BuildTest();

            if (isset($attributes['TIME'])) {
                $this->BuildTest->Time = $attributes['TIME'];
            }

            // Default is that the test passes unless there is a <failure> tag
            $this->BuildTest->Status = 'passed';

            $this->Test->Name = $attributes['NAME'];
            $this->Test->Path = $attributes['CLASSNAME'];
        } elseif ($name == 'TESTSUITE') {
            // If the XML file doesn't have a <Site> tag then we use the information
            // provided by the testsuite.
            // buildname is 'name'
            // sitename is 'hostname'
            // timestamp is 'timestamp'
            if ($this->HasSiteTag == false) {
                // Hostname is not necessarily defined
                if (!empty($attributes['HOSTNAME'])) {
                    $this->Site->Name = $attributes['HOSTNAME'];
                    $this->Site->Insert();
                    $this->Build->SiteId = $this->Site->Id;
                }

                $this->Build->Information = new BuildInformation();

                $this->Build->Name = $attributes['NAME'];

                // Construct a CMake-Like build stamp
                // We assume Nightly
                // If the TIMESTAMP attribute is not defined we take the current timestamp
                if (!isset($attributes['TIMESTAMP'])) {
                    $timestamp = time();
                } else {
                    $timestamp = strtotime($attributes['TIMESTAMP']);
                }
                $stamp = date('Ymd-Hi', $timestamp) . '-Nightly';
                $this->Build->SetStamp($stamp);
                $this->Append = false;
            } elseif (!isset($attributes['TIMESTAMP'])) {
                $stamp = $this->Build->GetStamp();
                $timestamp = mktime(substr($stamp, 9, 2), substr($stamp, 11, 2), 0,
                    substr($stamp, 6, 2), substr($stamp, 4, 2), substr($stamp, 0, 4));
            }

            $this->StartTimeStamp = $timestamp;
            $this->EndTimeStamp = $this->StartTimeStamp + $attributes['TIME'];
            $this->Build->SaveTotalTestsTime($attributes['TIME']);
        }
    }

    /** End Element */
    public function endElement($parser, $name)
    {
        parent::endElement($parser, $name);
        if ($name == 'FAILURE') {
            $this->BuildTest->Status = 'failed';
        } elseif ($name == 'TESTCASE') {
            if ($this->BuildTest->Status == 'passed') {
                $this->NumberTestsPassed++;
            } elseif ($this->BuildTest->Status == 'failed') {
                $this->NumberTestsFailed++;
            }
            $this->Test->Insert();
            if ($this->Test->Id > 0) {
                $this->BuildTest->TestId = $this->Test->Id;
                $this->BuildTest->BuildId = $this->Build->Id;
                $this->BuildTest->Insert();

                $this->Test->InsertLabelAssociations($this->Build->Id);
            } else {
                add_log('Cannot insert test', 'Test XML parser', LOG_ERR,
                    $this->projectid, $this->Build->Id);
            }
        } elseif ($name == 'SITE' || ($this->HasSiteTag == false && $name == 'TESTSUITE')) {
            if (strlen($this->EndTimeStamp) > 0 && $this->UpdateEndTime) {
                $end_time = gmdate(FMT_DATETIME, $this->EndTimeStamp); // The EndTimeStamp
                $this->Build->UpdateEndTime($end_time);
            }

            // Update the number of tests in the Build table
            $this->Build->UpdateTestNumbers($this->NumberTestsPassed,
                $this->NumberTestsFailed,
                $this->NumberTestsNotRun);
            $this->Build->ComputeTestTiming();
        }
    }

    /** Text function */
    public function text($parser, $data)
    {
        $element = $this->getElement();
        if ($element == 'FAILURE') {
            $this->Test->Output .= $data;
        }
    }
}
