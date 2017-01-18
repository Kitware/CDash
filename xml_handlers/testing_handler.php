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
require_once 'models/feed.php';

class TestingHandler extends AbstractHandler
{
    private $StartTimeStamp;
    private $EndTimeStamp;

    private $Test;
    private $BuildTest;
    private $TestMeasurement;
    private $Label;
    private $Append;

    private $Builds;
    private $BuildInformation;

    // Map SubProjects to Labels
    private $SubProjects;
    private $TestSubProjectName;

    // Keep a record of the number of tests passed, failed and notrun
    // This works only because we have one test file per submission
    private $NumberTestsFailed;
    private $NumberTestsNotRun;
    private $NumberTestsPassed;

    private $Feed;

    /** Constructor */
    public function __construct($projectID, $scheduleID)
    {
        parent::__construct($projectID, $scheduleID);
        $this->Builds = array();
        $this->SubProjects = array();
        $this->Site = new Site();
        $this->NumberTestsFailed = array();
        $this->NumberTestsNotRun = array();
        $this->NumberTestsPassed = array();
        $this->StartTimeStamp = 0;
        $this->EndTimeStamp = 0;
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

        if ($name == 'SITE') {
            $this->Site->Name = $attributes['NAME'];
            if (empty($this->Site->Name)) {
                $this->Site->Name = '(empty)';
            }
            $this->Site->Insert();

            $siteInformation = new SiteInformation();
            $this->BuildInformation = new BuildInformation();

            // Fill in the attribute
            foreach ($attributes as $key => $value) {
                $siteInformation->SetValue($key, $value);
                $this->BuildInformation->SetValue($key, $value);
            }

            $this->Site->SetInformation($siteInformation);

            if (array_key_exists('APPEND', $attributes)) {
                $this->Append = $attributes['APPEND'];
            } else {
                $this->Append = false;
            }
        } elseif ($name == 'SUBPROJECT') {
            $this->SubProjectName = $attributes['NAME'];
            if (!array_key_exists($this->SubProjectName, $this->SubProjects)) {
                $this->SubProjects[$this->SubProjectName] = array();
            }
        } elseif ($name == 'TEST' && count($attributes) > 0) {
            $this->Test = new Test();
            $this->Test->ProjectId = $this->projectid;
            $this->BuildTest = new BuildTest();
            $this->BuildTest->Status = $attributes['STATUS'];
            $this->TestSubProjectName = "";
        } elseif ($name == 'NAMEDMEASUREMENT') {
            $this->TestMeasurement = new TestMeasurement();

            if ($attributes['TYPE'] == 'file') {
                $this->TestMeasurement->Name = $attributes['FILENAME'];
            } else {
                $this->TestMeasurement->Name = $attributes['NAME'];
            }
            $this->TestMeasurement->Type = $attributes['TYPE'];
        } elseif ($name == 'VALUE' && $parent == 'MEASUREMENT') {
            if (isset($attributes['COMPRESSION']) && $attributes['COMPRESSION'] == 'gzip') {
                $this->Test->CompressedOutput = true;
            }
        } elseif ($name == 'LABEL' && $parent == 'LABELS') {
            $this->Label = new Label();
        }
    }

    /** End Element */
    public function endElement($parser, $name)
    {
        $parent = $this->getParent(); // should be before endElement
        parent::endElement($parser, $name);

        if ($name == 'TEST' && $parent == 'TESTING') {
            // By now, will either have one subproject for the entire file
            // Or a subproject specifically for this test
            // Or no subprojects.
            if (!array_key_exists($this->SubProjectName, $this->Builds)) {
                $this->createBuild();
            }

            $build = $this->Builds[$this->SubProjectName];

            $GLOBALS['PHP_ERROR_BUILD_ID'] = $build->Id;

            if ($this->BuildTest->Status == 'passed') {
                $this->NumberTestsPassed[$this->SubProjectName]++;
            } elseif ($this->BuildTest->Status == 'failed') {
                $this->NumberTestsFailed[$this->SubProjectName]++;
            } elseif ($this->BuildTest->Status == 'notrun') {
                $this->NumberTestsNotRun[$this->SubProjectName]++;
            }

            $this->Test->Insert();
            if ($this->Test->Id > 0) {
                $this->BuildTest->TestId = $this->Test->Id;
                $this->BuildTest->BuildId = $build->Id;
                $this->BuildTest->Insert();

                $this->Test->InsertLabelAssociations($build->Id);
            } else {
                add_log('Cannot insert test', 'Test XML parser', LOG_ERR,
                    $this->projectid, $build->Id);
            }
        } elseif ($name == 'LABEL' && $parent == 'LABELS') {
            if (!empty($this->TestSubProjectName)) {
                $this->SubProjectName = $this->TestSubProjectName;
            }
            elseif (isset($this->Test)) {
                $this->Test->AddLabel($this->Label);
            }
        } elseif ($name == 'NAMEDMEASUREMENT') {
            if ($this->TestMeasurement->Name == 'Execution Time') {
                $this->BuildTest->Time = $this->TestMeasurement->Value;
            } elseif ($this->TestMeasurement->Name == 'Exit Code') {
                if (strlen($this->Test->Details) > 0) {
                    $this->Test->Details .= ' (' . $this->TestMeasurement->Value . ')';
                } else {
                    $this->Test->Details = $this->TestMeasurement->Value;
                }
            } elseif ($this->TestMeasurement->Name == 'Completion Status') {
                if (strlen($this->Test->Details) > 0) {
                    $this->Test->Details = $this->TestMeasurement->Value . ' (' . $this->Test->Details . ')';
                } else {
                    $this->Test->Details = $this->TestMeasurement->Value;
                }
            } elseif ($this->TestMeasurement->Name == 'Command Line') {
                // don't do anything since it should already be in the FullCommandLine
            } else {
                // explicit measurement

                // If it's an image we add it as an image
                if (strpos($this->TestMeasurement->Type, 'image') !== false) {
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
        } elseif ($name == 'SITE') {
            // If we've gotten this far without creating any builds, there's no
            // tests. Create a build anyway.
            if (empty($this->Builds)) {
                $this->createBuild();
            }

            foreach ($this->Builds as $subproject => $build) {
                // Update the number of tests in the Build table
                $build->UpdateTestNumbers($this->NumberTestsPassed[$subproject],
                    $this->NumberTestsFailed[$subproject],
                    $this->NumberTestsNotRun[$subproject]);
                $build->ComputeTestTiming();

                if ($this->StartTimeStamp > 0 && $this->EndTimeStamp > 0) {
                    // Update test duration in the Build table.
                    $build->SaveTotalTestsTime(
                        $this->EndTimeStamp - $this->StartTimeStamp);
                }

                // Update the build's end time to extend through testing.
                $end_time = gmdate(FMT_DATETIME, $this->EndTimeStamp);
                $build->EndTime = $end_time;
                $build->UpdateBuild($build->Id, -1, -1);

                global $CDASH_ENABLE_FEED;
                if ($CDASH_ENABLE_FEED) {
                    // Insert the build into the feed
                    $this->Feed->InsertTest($this->projectid, $build->Id);
                }
            }
        }
    }

    /** Text function */
    public function text($parser, $data)
    {
        $parent = $this->getParent();
        $element = $this->getElement();

        if ($parent == 'TESTING' && $element == 'STARTDATETIME') {
            // Defer to StartTestTime as it has higher precision.
            if (!isset($this->StartTimeStamp)) {
                $this->StartTimeStamp =
                    str_to_time($data, $this->BuildInformation->BuildStamp);
            }
        } elseif ($parent == 'TESTING' && $element == 'STARTTESTTIME') {
            $this->StartTimeStamp = $data;
        } elseif ($parent == 'TESTING' && $element == 'ENDDATETIME') {
            // Defer to EndTestTime as it has higher precision.
            if (!isset($this->EndTimeStamp)) {
                $this->EndTimeStamp =
                    str_to_time($data, $this->BuildInformation->BuildStamp);
            }
        } elseif ($parent == 'TESTING' && $element == 'ENDTESTTIME') {
            $this->EndTimeStamp = $data;
        } elseif ($parent == 'TEST') {
            switch ($element) {
                case 'NAME':
                    $this->Test->Name .= $data;
                    break;
                case 'PATH':
                    $this->Test->Path .= $data;
                    break;
                case 'FULLNAME':
                    //$this->Test->Command = $data;
                    break;
                case 'FULLCOMMANDLINE':
                    $this->Test->Command .= $data;
                    break;
            }
        } elseif ($parent == 'NAMEDMEASUREMENT' && $element == 'VALUE') {
            $this->TestMeasurement->Value .= $data;
        } elseif ($parent == 'MEASUREMENT' && $element == 'VALUE') {
            $this->Test->Output .= $data;
        } elseif ($parent == 'SUBPROJECT' && $element == 'LABEL') {
           $this->SubProjects[$this->SubProjectName][] =  $data;
        } elseif ($parent == 'LABELS' && $element == 'LABEL') {
            // First, check if this label belongs to a SubProject
            foreach ($this->SubProjects as $subproject => $labels) {
              if (in_array($data, $labels)) {
                $this->TestSubProjectName = $subproject;
                break;
              }
            }
            if (empty($this->TestSubProjectName)) {
              $this->Label->SetText($data);
            }
        }
    }

    public function getBuildStamp()
    {
        return $this->BuildInformation->BuildStamp;
    }

    public function getBuildName()
    {
        return $this->BuildInformation->BuildName;
    }

    private function createBuild()
    {
        if (!array_key_exists($this->SubProjectName, $this->NumberTestsFailed)) {
            $this->NumberTestsFailed[$this->SubProjectName] = 0;
        }
        if (!array_key_exists($this->SubProjectName, $this->NumberTestsNotRun)) {
            $this->NumberTestsNotRun[$this->SubProjectName] = 0;
        }
        if (!array_key_exists($this->SubProjectName, $this->NumberTestsPassed)) {
            $this->NumberTestsPassed[$this->SubProjectName] = 0;
        }

        $build = new Build();

        if (!empty($this->BuildInformation->PullRequest)) {
            $build->SetPullRequest($this->BuildInformation->PullRequest);
        }

        $build->SiteId = $this->Site->Id;
        $build->Name = $this->BuildInformation->BuildName;

        $build->SetStamp($this->BuildInformation->BuildStamp);
        $build->Generator = $this->BuildInformation->Generator;
        $build->Information = $this->BuildInformation;

        $start_time = gmdate(FMT_DATETIME, $this->StartTimeStamp);

        $build->ProjectId = $this->projectid;
        $build->StartTime = $start_time;
        // EndTimeStamp hasn't been parsed yet.
        $build->EndTime = $start_time;
        $build->SubmitTime = gmdate(FMT_DATETIME);
        $build->SetSubProject($this->SubProjectName);

        $build->GetIdFromName($this->SubProjectName);
        $build->RemoveIfDone();

      // If the build doesn't exist we add it
      if ($build->Id == 0) {
          $build->Append = $this->Append;
          $build->InsertErrors = false;
          add_build($build, $this->scheduleid);
      } else {
          // Otherwise make sure that the build is up-to-date.
          $build->UpdateBuild($build->Id, -1, -1);

          // If the build already exists factor the number of tests
          // that have already been run into our running total.
          $this->NumberTestsFailed[$this->SubProjectName] += $build->GetNumberOfFailedTests();
          $this->NumberTestsNotRun[$this->SubProjectName] += $build->GetNumberOfNotRunTests();
          $this->NumberTestsPassed[$this->SubProjectName] += $build->GetNumberOfPassedTests();
      }

        $this->Builds[$this->SubProjectName] = $build;
    }
}
