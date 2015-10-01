<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
require_once 'xml_handlers/abstract_handler.php';
require_once('models/build.php');
require_once('models/label.php');
require_once('models/site.php');
require_once('models/test.php');
require_once('models/image.php');

class TestingNUnitHandler extends AbstractHandler
{
    private $StartTimeStamp;
    private $EndTimeStamp;
    private $UpdateEndTime; // should we update the end time of the build

  private $BuildId;
    private $Test;
    private $BuildTest;
    private $BuildTestDiff;
    private $TestImage;
    private $TestMeasurement;
    private $Label;
    private $Append;

  // Keep a record of the number of tests passed, failed and notrun
  // This works only because we have one test file per submission
  private $NumberTestsFailed;
    private $NumberTestsNotRun;
    private $NumberTestsPassed;
    private $HasSiteTag;
    private $BuildAdded;

  /** Constructor */
  public function __construct($projectID, $scheduleID)
  {
      parent::__construct($projectID, $scheduleID);
      $this->Build = new Build();
      $this->Site = new Site();
      $this->UpdateEndTime = false;
      $this->NumberTestsFailed=0;
      $this->NumberTestsNotRun=0;
      $this->NumberTestsPassed=0;
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

    if ($name=='SITE') {
        $this->HasSiteTag = true;
        $this->Site->Name = $attributes['NAME'];
        if (empty($this->Site->Name)) {
            $this->Site->Name = "(empty)";
        }
        $this->Site->Insert();

        $siteInformation = new SiteInformation();
        $buildInformation =  new BuildInformation();

      // Fill in the attribute
      foreach ($attributes as $key=>$value) {
          $siteInformation->SetValue($key, $value);
          $buildInformation->SetValue($key, $value);
      }

        $this->Site->SetInformation($siteInformation);

        $this->Build->SiteId = $this->Site->Id;
        $this->Build->Name = $attributes['BUILDNAME'];
        if (empty($this->Build->Name)) {
            $this->Build->Name = "(empty)";
        }
        $this->Build->SetStamp($attributes['BUILDSTAMP']);
        $this->Build->Generator = $attributes['GENERATOR'];
        $this->Build->Information = $buildInformation;

        if (array_key_exists('APPEND', $attributes)) {
            $this->Append = $attributes['APPEND'];
        } else {
            $this->Append = false;
        }
    } elseif ($name == "TEST-CASE" && count($attributes) > 0) {
        $this->Test = new Test();
        $this->Test->ProjectId = $this->projectid;
        $this->BuildTest = new BuildTest();

        $teststatus = "notrun";
        if ($attributes['RESULT'] == "Success") {
            $teststatus = "passed";
        } elseif ($attributes['RESULT'] == "Failure") {
            $teststatus = "failed";
        }

        $this->BuildTest->Status = $teststatus;

        if ($teststatus == "passed") {
            $this->NumberTestsPassed++;
        } elseif ($teststatus == "failed") {
            $this->NumberTestsFailed++;
        } elseif ($teststatus == "notrun") {
            $this->NumberTestsNotRun++;
        }

        if (isset($attributes['TIME'])) {
            $this->BuildTest->Time = $attributes['TIME'];
        }

        if (isset($attributes['ASSERTS'])) {
            $this->Test->Details = $attributes['ASSERTS'];
        }

        $this->Test->Name = $attributes['NAME'];
        $this->Test->Path = $attributes['NAME'];
        $this->Test->Command = $attributes['NAME'];
    } elseif ($name == "TEST-RESULTS") {
        $this->StartTimeStamp = strtotime($attributes['DATE'].' '.$attributes['TIME']);
        $this->EndTimeStamp = $this->StartTimeStamp;
    } elseif ($this->HasSiteTag == false && $name == "ENVIRONMENT") {
        // If the XML file doesn't have a <Site> tag then we use the information
      // provided by the testsuite.
      // buildname is 'name'
      // sitename is 'hostname'
      // timestamp is 'timestamp'
      $this->Site->Name = $attributes['MACHINE-NAME'];
        $this->Site->Insert();

        $buildInformation =  new BuildInformation();
        $buildInformation->OSName = $attributes['PLATFORM'];
        $buildInformation->OSVersion = $attributes['OS-VERSION'];
        $buildInformation->CompilerName = 'CLR';
        $buildInformation->CompilerVersion = $attributes['CLR-VERSION'];

        $this->Build->SiteId = $this->Site->Id;
        $this->Build->Name = $attributes['PLATFORM'];

      // Construct a CMake-Like build stamp
      // We assume Nightly
      $stamp = date("Ymd-Hi", $this->StartTimeStamp).'-Nightly';
        $this->Build->SetStamp($stamp);
        $this->Build->Information = $buildInformation;
        $this->Append = false;
    } elseif ($this->BuildAdded == false && $name == "TEST-SUITE") {
        $this->Build->SaveTotalTestsTime(0);

        $start_time = gmdate(FMT_DATETIME, $this->StartTimeStamp);
        $this->Build->ProjectId = $this->projectid;
        $buildid = $this->Build->GetIdFromName($this->SubProjectName);

      // If the build doesn't exist we add it
      if ($buildid==0) {
          $this->Build->ProjectId = $this->projectid;
          $this->Build->StartTime = $start_time;
          $this->Build->EndTime = $start_time;
          $this->Build->SubmitTime = gmdate(FMT_DATETIME);
          $this->Build->SetSubProject($this->SubProjectName);
          $this->Build->Append = $this->Append;
          $this->Build->InsertErrors = false;
          add_build($this->Build, $this->scheduleid);

          $this->UpdateEndTime = true;
          $buildid = $this->Build->Id;
      } else {
          $this->Build->Id = $buildid;
        //if the build already exists factor the number of tests that have
        //already been run into our running total
        $this->NumberTestsFailed += $this->Build->GetNumberOfFailedTests();
          $this->NumberTestsNotRun += $this->Build->GetNumberOfNotRunTests();
          $this->NumberTestsPassed += $this->Build->GetNumberOfPassedTests();
      }

        $GLOBALS['PHP_ERROR_BUILD_ID'] = $buildid;
        $this->BuildId = $buildid;
        $this->BuildAdded = true;
    }
  } // end startElement


  /** End Element */
  public function endElement($parser, $name)
  {
      //$parent = $this->getParent(); // should be before endElement
    parent::endElement($parser, $name);

      if ($name == "TEST-CASE") {
          $this->Test->Insert();
          if ($this->Test->Id>0) {
              $this->BuildTest->TestId = $this->Test->Id;
              $this->BuildTest->BuildId = $this->BuildId;
              $this->BuildTest->Insert();

              $this->Test->InsertLabelAssociations($this->BuildId);
          } else {
              add_log("Cannot insert test", "Test XML parser", LOG_ERR,
                $this->projectid, $this->BuildId);
          }
      } elseif ($name == "SITE" || ($this->HasSiteTag == false && $name == "TEST-RESULTS")) {
          if (strlen($this->EndTimeStamp)>0 && $this->UpdateEndTime) {
              $end_time = gmdate(FMT_DATETIME, $this->EndTimeStamp); // The EndTimeStamp
        $this->Build->UpdateEndTime($end_time);
          }

      // Update the number of tests in the Build table
      $this->Build->UpdateTestNumbers($this->NumberTestsPassed,
                                      $this->NumberTestsFailed,
                                      $this->NumberTestsNotRun);
          $this->Build->ComputeTestTiming();
      }
  } // end endElement

  /** Text function */
  public function text($parser, $data)
  {
      //$parent = $this->getParent();
    //$element = $this->getElement();
  }
}
