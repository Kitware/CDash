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

use CDash\Lib\Collection\BuildCollection;
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

/**
 * Class TestingParser
 * @package CDash\Lib\Parsing\Xml
 */
class TestingParser extends AbstractXmlParser
{
    /** @var Test Test */
    private $test;
    private $buildTest;
    private $testMeasurement;
    private $label;
    private $append;

    /** @var Build[] Builds */
    private $builds;
    private $buildInformation;

    // Map SubProjects to Labels
    private $subProjects;
    private $testSubProjectName;
    private $buildName;
    private $buildStamp;
    private $generator;
    private $pullRequest;

    // Keep a record of the number of tests passed, failed and notrun
    // This works only because we have one test file per submission
    private $numberTestsFailed;
    private $numberTestsNotRun;
    private $numberTestsPassed;

    private $feed;

    /**
     * TestingParser constructor.
     * @param $projectId
     */
    public function __construct($projectId)
    {
        parent::__construct($projectId);
        $this->builds = [];
        $this->subProjects = [];
        $this->numberTestsFailed = [];
        $this->numberTestsNotRun = [];
        $this->numberTestsPassed = [];
        $this->startTimeStamp = 0;
        $this->endTimeStamp = 0;
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
        $parent = $this->getParent(); // should be before endElement

        if ($name == 'SITE') {
            $this->site = $this->getInstance(Site::class);
            $this->site->Name = $attributes['NAME'];
            if (empty($this->site->Name)) {
                $this->site->Name = '(empty)';
            }
            $this->site->Insert();

            $siteInformation = $this->getInstance(SiteInformation::class);
            $this->buildInformation = $this->getInstance(BuildInformation::class);
            $this->buildName = "";
            $this->buildStamp = "";
            $this->generator = "";
            $this->pullRequest = "";

            // Fill in the attribute
            foreach ($attributes as $key => $value) {
                if ($key === 'BUILDNAME') {
                    $this->buildName = $value;
                } elseif ($key === 'BUILDSTAMP') {
                    $this->buildStamp = $value;
                } elseif ($key === 'GENERATOR') {
                    $this->generator = $value;
                } elseif ($key == 'CHANGEID') {
                    $this->pullRequest = $value;
                } else {
                    $siteInformation->SetValue($key, $value);
                    $this->buildInformation->SetValue($key, $value);
                }
            }

            if (empty($this->buildName)) {
                $this->buildName = '(empty)';
            }
            $this->site->SetInformation($siteInformation);

            if (array_key_exists('APPEND', $attributes)) {
                $this->append = $attributes['APPEND'];
            } else {
                $this->append = false;
            }
        } elseif ($name == 'SUBPROJECT') {
            $this->subProjectName = $attributes['NAME'];
            if (!array_key_exists($this->subProjectName, $this->subProjects)) {
                $this->subProjects[$this->subProjectName] = [];
                $this->createBuild();
            }
        } elseif ($name == 'TEST' && count($attributes) > 0) {
            $this->test = $this->getInstance(Test::class);
            $this->test->ProjectId = $this->projectId;
            $this->buildTest = $this->getInstance(BuildTest::class);
            $this->buildTest->Status = $attributes['STATUS'];
            $this->testSubProjectName = "";
        } elseif ($name == 'NAMEDMEASUREMENT') {
            $this->testMeasurement = $this->getInstance(TestMeasurement::class);

            if ($attributes['TYPE'] == 'file') {
                $this->testMeasurement->Name = $attributes['FILENAME'];
            } else {
                $this->testMeasurement->Name = $attributes['NAME'];
            }
            $this->testMeasurement->Type = $attributes['TYPE'];
        } elseif ($name == 'VALUE' && $parent == 'MEASUREMENT') {
            if (isset($attributes['COMPRESSION']) && $attributes['COMPRESSION'] == 'gzip') {
                $this->test->CompressedOutput = true;
            }
        } elseif ($name == 'LABEL' && $parent == 'LABELS') {
            $this->label = $this->getInstance(Label::class);
        }
    }

    /**
     * @param $parser
     * @param $name
     * @return mixed|void
     */
    public function endElement($parser, $name)
    {
        $parent = $this->getParent(); // should be before endElement
        parent::endElement($parser, $name);

        if ($name == 'TEST' && $parent == 'TESTING') {
            // By now, will either have one subproject for the entire file
            // Or a subproject specifically for this test
            // Or no subprojects.
            if (!array_key_exists($this->subProjectName, $this->builds)) {
                $this->createBuild();
            }

            $build = $this->builds[$this->subProjectName];

            $GLOBALS['PHP_ERROR_BUILD_ID'] = $build->Id;

            if ($this->buildTest->Status == 'passed') {
                $this->numberTestsPassed[$this->subProjectName]++;
            } elseif ($this->buildTest->Status == 'failed') {
                $this->numberTestsFailed[$this->subProjectName]++;
            } elseif ($this->buildTest->Status == 'notrun') {
                $this->numberTestsNotRun[$this->subProjectName]++;
            }

            $this->test->Insert();
            $build->AddTest($this->test);
            $this->test->SetBuildTest($this->buildTest);
            if ($this->test->Id > 0) {
                $this->buildTest->TestId = $this->test->Id;
                $this->buildTest->BuildId = $build->Id;
                $this->buildTest->Insert();

                $this->test->InsertLabelAssociations($build->Id);
            } else {
                add_log('Cannot insert test', 'Test XML parser', LOG_ERR,
                    $this->projectId, $build->Id);
            }
        } elseif ($name == 'LABEL' && $parent == 'LABELS') {
            if (!empty($this->testSubProjectName)) {
                $this->subProjectName = $this->testSubProjectName;
            } elseif (isset($this->test)) {
                $this->test->AddLabel($this->label);
            }
        } elseif ($name == 'NAMEDMEASUREMENT') {
            if ($this->testMeasurement->Name == 'Execution Time') {
                $this->buildTest->Time = $this->testMeasurement->Value;
            } elseif ($this->testMeasurement->Name == 'Exit Code') {
                if (strlen($this->test->Details) > 0 && $this->testMeasurement->Value) {
                    $this->test->Details .= ' (' . $this->testMeasurement->Value . ')';
                } elseif ($this->testMeasurement->Value) {
                    $this->test->Details = $this->testMeasurement->Value;
                }
            } elseif ($this->testMeasurement->Name == 'Completion Status') {
                if (strlen($this->test->Details) > 0) {
                    $this->test->Details = $this->testMeasurement->Value . ' (' . $this->test->Details . ')';
                } else {
                    $this->test->Details = $this->testMeasurement->Value;
                }
            } elseif ($this->testMeasurement->Name == 'Command Line') {
                // don't do anything since it should already be in the FullCommandLine
            } else {
                // explicit measurement

                // If it's an image we add it as an image
                if (strpos($this->testMeasurement->Type, 'image') !== false) {
                    $image = $this->getInstance(Image::class);
                    $image->Extension = $this->testMeasurement->Type;
                    $image->Data = $this->testMeasurement->Value;
                    $image->Name = $this->testMeasurement->Name;
                    $this->test->AddImage($image);
                } else {
                    $this->testMeasurement->Value = trim($this->testMeasurement->Value);
                    $this->test->AddMeasurement($this->testMeasurement);
                }
            }
        } elseif ($name == 'SITE') {
            // If we've gotten this far without creating any builds, there's no
            // tests. Create a build anyway.
            if (empty($this->builds)) {
                $this->createBuild();
            }

            // Do not accumulate the parent's testing duration if this
            // XML file represents multiple "all-at-once" SubProject builds.
            $all_at_once = count($this->builds) > 1;
            $parent_duration_set = false;
            foreach ($this->builds as $subproject => $build) {
                $build->StartTime = gmdate(FMT_DATETIME, $this->startTimeStamp);
                $build->EndTime = gmdate(FMT_DATETIME, $this->endTimeStamp);
                $build->UpdateBuild($build->Id, -1, -1);

                // Update the number of tests in the Build table
                $build->UpdateTestNumbers($this->numberTestsPassed[$subproject],
                    $this->numberTestsFailed[$subproject],
                    $this->numberTestsNotRun[$subproject]);
                $build->ComputeTestTiming();

                if ($this->startTimeStamp > 0 && $this->endTimeStamp > 0) {
                    // Update test duration in the Build table.
                    $duration = $this->endTimeStamp - $this->startTimeStamp;
                    $build->SaveTotalTestsTime($duration, !$all_at_once);
                    if ($all_at_once && !$parent_duration_set) {
                        $parent_build = $this->getInstance(Build::class);
                        $parent_build->Id = $build->GetParentId();
                        $parent_build->SaveTotalTestsTime($duration, false);
                        $parent_duration_set = true;
                    }
                }

                if ($this->getConfigValue('CDASH_ENABLE_FEED')) {
                    // Insert the build into the feed
                    $this->feed = $this->getInstance(Feed::class);
                    $this->feed->InsertTest($this->projectId, $build->Id);
                }
            }
        }
    }

    /**
     * @param $parser
     * @param $data
     * @return mixed|void
     */
    public function text($parser, $data)
    {
        $parent = $this->getParent();
        $element = $this->getElement();

        if ($parent == 'TESTING') {
            switch ($element) {
                case 'STARTTESTTIME':
                    $this->startTimeStamp = $data;
                    break;
                case 'ENDTESTTIME':
                    $this->endTimeStamp = $data;
                    break;
            }
        } elseif ($parent == 'TEST') {
            switch ($element) {
                case 'NAME':
                    $this->test->Name = $data;
                    break;
                case 'PATH':
                    $this->test->Path = $data;
                    break;
                case 'FULLNAME':
                    //$this->Test->Command = $data;
                    break;
                case 'FULLCOMMANDLINE':
                    $this->test->Command .= $data;
                    break;
            }
        } elseif ($parent == 'NAMEDMEASUREMENT' && $element == 'VALUE') {
            if (!isset($this->testMeasurement->Value)) {
                $this->testMeasurement->Value = $data;
            } else {
                $this->testMeasurement->Value .= $data;
            }
        } elseif ($parent == 'MEASUREMENT' && $element == 'VALUE') {
            $this->test->Output .= $data;
        } elseif ($parent == 'SUBPROJECT' && $element == 'LABEL') {
            $this->subProjects[$this->subProjectName][] =  $data;
        } elseif ($parent == 'LABELS' && $element == 'LABEL') {
            // First, check if this label belongs to a SubProject
            foreach ($this->subProjects as $subproject => $labels) {
                if (in_array($data, $labels)) {
                    $this->testSubProjectName = $subproject;
                    break;
                }
            }
            if (empty($this->testSubProjectName)) {
                $this->label->SetText($data);
            }
        }
    }

    /**
     * @return string
     */
    public function getBuildStamp()
    {
        return $this->buildStamp;
    }

    /**
     * @return string
     */
    public function getBuildName()
    {
        return $this->buildName;
    }

    /**
     * @return void
     */
    private function createBuild()
    {
        if (!array_key_exists($this->subProjectName, $this->numberTestsFailed)) {
            $this->numberTestsFailed[$this->subProjectName] = 0;
        }
        if (!array_key_exists($this->subProjectName, $this->numberTestsNotRun)) {
            $this->numberTestsNotRun[$this->subProjectName] = 0;
        }
        if (!array_key_exists($this->subProjectName, $this->numberTestsPassed)) {
            $this->numberTestsPassed[$this->subProjectName] = 0;
        }

        $build = $this->getInstance(Build::class);
        $build->SetSite($this->site);

        if (!empty($this->pullRequest)) {
            $build->SetPullRequest($this->pullRequest);
        }

        $build->SiteId = $this->site->Id;
        $build->Name = $this->buildName;
        $build->SetStamp($this->buildStamp);
        $build->Generator = $this->generator;
        $build->Information = $this->buildInformation;
        $build->ProjectId = $this->projectId;
        $build->SubmitTime = gmdate(FMT_DATETIME);

        // TODO: dark days lie in waiting for this...
        $build->StartTime = gmdate(FMT_DATETIME);

        $build->SetSubProject($this->subProjectName);

        $build->GetIdFromName($this->subProjectName);
        $build->RemoveIfDone();

        // If the build doesn't exist we add it
        if ($build->Id == 0) {
            $build->Append = $this->append;
            $build->InsertErrors = false;
            add_build($build);
        } else {
            // Otherwise make sure that the build is up-to-date.
            $build->UpdateBuild($build->Id, -1, -1);

            // If the build already exists factor the number of tests
            // that have already been run into our running total.
            $this->numberTestsFailed[$this->subProjectName] += $build->GetNumberOfFailedTests();
            $this->numberTestsNotRun[$this->subProjectName] += $build->GetNumberOfNotRunTests();
            $this->numberTestsPassed[$this->subProjectName] += $build->GetNumberOfPassedTests();
        }

        $this->builds[$this->subProjectName] = $build;
    }

    /**
     * @return Build[]
     */
    public function getBuilds()
    {
        return array_values($this->builds);
    }
    /**
     * @return Build[]
     * @deprecated Use GetBuildCollection() 02/04/18
     */
    public function getActionableBuilds()
    {
        return $this->builds;
    }

    /**
     * @return BuildCollection
     */
    public function GetBuildCollection()
    {
        return new BuildCollection($this->builds);
    }
}
