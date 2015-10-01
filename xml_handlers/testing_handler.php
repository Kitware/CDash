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
require_once('models/feed.php');

class TestingHandler extends AbstractHandler
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
      $this->Feed = new Feed();
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
        $this->Site->Name = $attributes['NAME'];
        if (empty($this->Site->Name)) {
            $this->Site->Name = "(empty)";
        }
        $this->Site->Insert();

        $siteInformation = new SiteInformation();
        $buildInformation =  new BuildInformation();

      // Fill in the attribute
      foreach ($attributes as $key=>$value) {
          if ($key === "CHANGEID") {
              $this->Build->SetPullRequest($value);
              continue;
          }
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
    } elseif ($name == "TEST" && count($attributes) > 0) {
        $this->Test = new Test();
        $this->Test->ProjectId = $this->projectid;
        $this->BuildTest = new BuildTest();
        $this->BuildTest->Status = $attributes['STATUS'];

        if ($attributes['STATUS'] == "passed") {
            $this->NumberTestsPassed++;
        } elseif ($attributes['STATUS'] == "failed") {
            $this->NumberTestsFailed++;
        } elseif ($attributes['STATUS'] == "notrun") {
            $this->NumberTestsNotRun++;
        }
    } elseif ($name == "NAMEDMEASUREMENT") {
        $this->TestMeasurement = new TestMeasurement();

        if ($attributes['TYPE'] == 'file') {
            $this->TestMeasurement->Name = $attributes['FILENAME'];
        } else {
            $this->TestMeasurement->Name = $attributes['NAME'];
        }
        $this->TestMeasurement->Type = $attributes['TYPE'];
    } elseif ($name == "VALUE" && $parent== "MEASUREMENT") {
        if (isset($attributes['COMPRESSION']) && $attributes['COMPRESSION']=="gzip") {
            $this->Test->CompressedOutput = true;
        }
    } elseif ($name == 'LABEL' && $parent == 'LABELS') {
        $this->Label = new Label();
    } elseif ($name == "TESTLIST" && $parent == 'TESTING') {
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
    }
  } // end startElement


  /** End Element */
  public function endElement($parser, $name)
  {
      $parent = $this->getParent(); // should be before endElement
    parent::endElement($parser, $name);

      if ($name == "TEST" && $parent == 'TESTING') {
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
      } elseif ($name == 'LABEL' && $parent == 'LABELS') {
          if (isset($this->Test)) {
              $this->Test->AddLabel($this->Label);
          }
      } elseif ($name == "NAMEDMEASUREMENT") {
          if ($this->TestMeasurement->Name == 'Execution Time') {
              $this->BuildTest->Time = $this->TestMeasurement->Value;
          } elseif ($this->TestMeasurement->Name == 'Exit Code') {
              if (strlen($this->Test->Details)>0) {
                  $this->Test->Details .= ' ('.$this->TestMeasurement->Value.')';
              } else {
                  $this->Test->Details = $this->TestMeasurement->Value;
              }
          } elseif ($this->TestMeasurement->Name == 'Completion Status') {
              if (strlen($this->Test->Details)>0) {
                  $this->Test->Details =  $this->TestMeasurement->Value.' ('.$this->Test->Details.')';
              } else {
                  $this->Test->Details = $this->TestMeasurement->Value;
              }
          } elseif ($this->TestMeasurement->Name == 'Command Line') {
              // don't do anything since it should already be in the FullCommandLine
          } else {
              // explicit measurement

        // If it's an image we add it as an image
        if (strpos($this->TestMeasurement->Type, 'image')!== false) {
            $image = new Image();
            $image->Extension = $this->TestMeasurement->Type;
            $image->Data = $this->TestMeasurement->Value;
            $image->Name = $this->TestMeasurement->Name;
            $this->Test->AddImage($image);
        } else {
            $this->TestMeasurement->Value = trim($this->TestMeasurement->Value);
            $this->Test->AddMeasurement($this->TestMeasurement);
        }
          }
      } // end named measurement
    elseif ($name == "SITE") {
        if (strlen($this->EndTimeStamp)>0 && $this->UpdateEndTime) {
            $end_time = gmdate(FMT_DATETIME, $this->EndTimeStamp); // The EndTimeStamp
        $this->Build->UpdateEndTime($end_time);
        }

      // Update the number of tests in the Build table
      $this->Build->UpdateTestNumbers($this->NumberTestsPassed,
                                      $this->NumberTestsFailed,
                                      $this->NumberTestsNotRun);
        $this->Build->ComputeTestTiming();

        global $CDASH_ENABLE_FEED;
        if ($CDASH_ENABLE_FEED) {
            // Insert the build into the feed
        $this->Feed->InsertTest($this->projectid, $this->Build->Id);
        }
    }
  } // end endElement

  /** Text function */
  public function text($parser, $data)
  {
      $parent = $this->getParent();
      $element = $this->getElement();

      if ($parent == 'TESTING' && $element == 'STARTDATETIME') {
          $this->StartTimeStamp = str_to_time($data, $this->Build->GetStamp());
      } elseif ($parent == 'TESTING' && $element == 'STARTTESTTIME') {
          $this->StartTimeStamp = $data;
      } elseif ($parent == 'TESTING' && $element == 'ENDDATETIME') {
          $this->EndTimeStamp = str_to_time($data, $this->Build->GetStamp());
      } elseif ($parent == 'TESTING' && $element == 'ENDTESTTIME') {
          $this->EndTimeStamp = $data;
      } elseif ($parent == 'TESTING' && $element == 'ELAPSEDMINUTES') {
          $this->Build->SaveTotalTestsTime($data);
      } elseif ($parent == "TEST") {
          switch ($element) {
        case "NAME":
          $this->Test->Name .= $data;
        break;
        case "PATH":
          $this->Test->Path .= $data;
        break;
        case "FULLNAME":
          //$this->Test->Command = $data;
          break;
        case "FULLCOMMANDLINE":
          $this->Test->Command .= $data;
          break;
        }
      } elseif ($parent == "NAMEDMEASUREMENT" && $element == "VALUE") {
          $this->TestMeasurement->Value .= $data;
      } elseif ($parent == "MEASUREMENT" && $element == "VALUE") {
          $this->Test->Output .= $data;
      } elseif ($parent == 'LABELS' && $element == 'LABEL') {
          $this->Label->SetText($data);
      }
  }
}
