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

use CDash\Collection\BuildCollection;

require_once 'xml_handlers/abstract_handler.php';
require_once 'xml_handlers/actionable_build_interface.php';

use CDash\Model\Build;
use CDash\Model\BuildInformation;
use CDash\Model\BuildTest;
use CDash\Model\Feed;
use CDash\Model\Image;
use CDash\Model\Label;
use CDash\Model\Site;
use CDash\Model\SiteInformation;
use CDash\Model\Test;
use CDash\Model\TestMeasurement;

class TestingHandler extends AbstractHandler implements ActionableBuildInterface
{
    private $StartTimeStamp;
    private $EndTimeStamp;

    /** @var Test Test */
    private $Test;
    private $BuildTest;
    private $TestMeasurement;
    private $Label;
    private $Append;

    /** @var Build[] Builds */
    private $Builds;
    private $BuildInformation;

    // Map SubProjects to Labels
    private $SubProjects;
    private $TestSubProjectName;
    private $BuildName;
    private $BuildStamp;
    private $Generator;
    private $PullRequest;

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
        $this->Builds = [];
        $this->SubProjects = [];
        $this->NumberTestsFailed = [];
        $this->NumberTestsNotRun = [];
        $this->NumberTestsPassed = [];
        $this->StartTimeStamp = 0;
        $this->EndTimeStamp = 0;
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
        $factory = $this->getModelFactory();

        if ($name == 'SITE') {
            $this->Site = $factory->create(Site::class);
            $this->Project = $factory->create(Project::class);
            $this->Project->Id = $this->projectid;

            $this->Site->Name = $attributes['NAME'];
            if (empty($this->Site->Name)) {
                $this->Site->Name = '(empty)';
            }
            $this->Site->Insert();

            $siteInformation = $factory->create(SiteInformation::class);
            $this->BuildInformation = $factory->create(BuildInformation::class);
            $this->BuildName = "";
            $this->BuildStamp = "";
            $this->Generator = "";
            $this->PullRequest = "";

            // Fill in the attribute
            foreach ($attributes as $key => $value) {
                if ($key === 'BUILDNAME') {
                    $this->BuildName = $value;
                } elseif ($key === 'BUILDSTAMP') {
                    $this->BuildStamp = $value;
                } elseif ($key === 'GENERATOR') {
                    $this->Generator = $value;
                } elseif ($key == 'CHANGEID') {
                    $this->PullRequest = $value;
                } else {
                    $siteInformation->SetValue($key, $value);
                    $this->BuildInformation->SetValue($key, $value);
                }
            }

            if (empty($this->BuildName)) {
                $this->BuildName = '(empty)';
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
                $this->SubProjects[$this->SubProjectName] = [];
                $this->createBuild();
            }
        } elseif ($name == 'TEST' && count($attributes) > 0) {
            $this->Test = $factory->create(Test::class);
            $this->Test->ProjectId = $this->projectid;
            $this->BuildTest = $factory->create(BuildTest::class);
            $this->BuildTest->Status = $attributes['STATUS'];
            $this->TestSubProjectName = "";
        } elseif ($name == 'NAMEDMEASUREMENT') {
            $this->TestMeasurement = $factory->create(TestMeasurement::class);

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
            $this->Label = $factory->create(Label::class);
        }
    }

    /** End Element */
    public function endElement($parser, $name)
    {
        $parent = $this->getParent(); // should be before endElement
        parent::endElement($parser, $name);
        $factory = $this->getModelFactory();

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
            $build->AddTest($this->Test);
            $this->Test->SetBuildTest($this->BuildTest);
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
            if (isset($this->Test)) {
                $this->Test->AddLabel($this->Label);
            }
        } elseif ($name == 'NAMEDMEASUREMENT') {
            if ($this->TestMeasurement->Name == 'Execution Time') {
                $this->BuildTest->Time = $this->TestMeasurement->Value;
            } elseif ($this->TestMeasurement->Name == 'Exit Code') {
                if (strlen($this->Test->Details) > 0 && $this->TestMeasurement->Value) {
                    $this->Test->Details .= ' (' . $this->TestMeasurement->Value . ')';
                } elseif ($this->TestMeasurement->Value) {
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
                    $image = $factory->create(Image::class);
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

            // Do not accumulate the parent's testing duration if this
            // XML file represents multiple "all-at-once" SubProject builds.
            $all_at_once = count($this->Builds) > 1;
            $parent_duration_set = false;
            foreach ($this->Builds as $subproject => $build) {
                $build->StartTime = gmdate(FMT_DATETIME, $this->StartTimeStamp);
                $build->EndTime = gmdate(FMT_DATETIME, $this->EndTimeStamp);
                $build->UpdateBuild($build->Id, -1, -1);

                // Update the number of tests in the Build table
                $build->UpdateTestNumbers($this->NumberTestsPassed[$subproject],
                    $this->NumberTestsFailed[$subproject],
                    $this->NumberTestsNotRun[$subproject]);

                // Is it really necessary to have to load the build from the db here?
                $build->ComputeTestTiming();

                if ($this->StartTimeStamp > 0 && $this->EndTimeStamp > 0) {
                    // Update test duration in the Build table.
                    $duration = $this->EndTimeStamp - $this->StartTimeStamp;
                    $build->SaveTotalTestsTime($duration, !$all_at_once);
                    if ($all_at_once && !$parent_duration_set) {
                        $parent_build = $factory->create(Build::class);
                        $parent_build->Id = $build->GetParentId();
                        $parent_build->SaveTotalTestsTime($duration, false);
                        $parent_duration_set = true;
                    }
                }

                $config = \CDash\Config::getInstance();
                if ($config->get('CDASH_ENABLE_FEED')) {
                    // Insert the build into the feed
                    $this->Feed = $factory->create(Feed::class);
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

        if ($parent == 'TESTING') {
            switch ($element) {
                case 'STARTTESTTIME':
                    $this->StartTimeStamp = $data;
                    break;
                case 'ENDTESTTIME':
                    $this->EndTimeStamp = $data;
                    break;
            }
        } elseif ($parent == 'TEST') {
            switch ($element) {
                case 'NAME':
                    $this->Test->Name = $data;
                    break;
                case 'PATH':
                    $this->Test->Path = $data;
                    break;
                case 'FULLNAME':
                    //$this->Test->Command = $data;
                    break;
                case 'FULLCOMMANDLINE':
                    $this->Test->Command .= $data;
                    break;
            }
        } elseif ($parent == 'NAMEDMEASUREMENT' && $element == 'VALUE') {
            if (!isset($this->TestMeasurement->Value)) {
                $this->TestMeasurement->Value = $data;
            } else {
                $this->TestMeasurement->Value .= $data;
            }
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
            if (is_a($this->Label, Label::class)) {
                $this->Label->SetText($data);
            }
        }
    }

    public function getBuildStamp()
    {
        return $this->BuildStamp;
    }

    public function getBuildName()
    {
        return $this->BuildName;
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
        $factory = $this->getModelFactory();
        $build = $factory->create(Build::class);
        $build->SetSite($this->Site);

        if (!empty($this->PullRequest)) {
            $build->SetPullRequest($this->PullRequest);
        }

        $build->SiteId = $this->Site->Id;
        $build->Name = $this->BuildName;
        $build->SetStamp($this->BuildStamp);
        $build->Generator = $this->Generator;
        $build->Information = $this->BuildInformation;
        $build->ProjectId = $this->projectid;
        $build->SetProject($this->Project);
        $build->SubmitTime = gmdate(FMT_DATETIME);

        // TODO: dark days lie in waiting for this...
        $build->StartTime = gmdate(FMT_DATETIME);

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

    /**
     * @return Build[]
     */
    public function getBuilds()
    {
        return array_values($this->Builds);
    }
    /**
     * @return Build[]
     * @deprecated Use GetBuildCollection() 02/04/18
     */
    public function getActionableBuilds()
    {
        return $this->Builds;
    }

    public function GetBuildCollection()
    {
        $collection = new BuildCollection();
        foreach ($this->Builds as $key => $build) {
            $collection->addItem($build, $key);
        }
        return $collection;
    }

    public function getProjectId()
    {
        // TODO: remove
    }
}
