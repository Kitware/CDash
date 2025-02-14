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

use App\Models\Site;
use App\Models\SiteInformation;
use App\Utils\SubmissionUtils;
use CDash\Collection\BuildCollection;
use CDash\Collection\SubscriptionBuilderCollection;
use CDash\Messaging\Notification\NotifyOn;
use CDash\Messaging\Subscription\UserSubscriptionBuilder;
use CDash\Messaging\Topic\DynamicAnalysisTopic;
use CDash\Messaging\Topic\TopicCollection;
use CDash\Model\Build;
use CDash\Model\BuildGroup;
use CDash\Model\DynamicAnalysis;
use CDash\Model\DynamicAnalysisDefect;
use CDash\Model\DynamicAnalysisSummary;
use CDash\Model\Label;
use CDash\Model\Project;
use CDash\Model\SubscriberInterface;

class DynamicAnalysisHandler extends AbstractXmlHandler implements ActionableBuildInterface
{
    private $StartTimeStamp;
    private $EndTimeStamp;
    private $Checker;

    private DynamicAnalysis $DynamicAnalysis;
    private $DynamicAnalysisDefect;
    private $DynamicAnalysisSummaries;
    private $Label;

    private $Builds;
    private array $BuildInformation;

    protected static ?string $schema_file = '/app/Validators/Schemas/DynamicAnalysis.xsd';

    // Map SubProjects to Labels
    private $SubProjects;
    private $TestSubProjectName;

    /** Constructor */
    public function __construct(Project $project)
    {
        parent::__construct($project);
        $this->Builds = [];
        $this->SubProjects = [];
        $this->DynamicAnalysisSummaries = [];
    }

    /** Start element */
    public function startElement($parser, $name, $attributes): void
    {
        parent::startElement($parser, $name, $attributes);
        $factory = $this->getModelFactory();

        if ($this->currentPathMatches('site')) {
            $site_name = !empty($attributes['NAME']) ? $attributes['NAME'] : '(empty)';
            $this->Site = Site::firstOrCreate(['name' => $site_name], ['name' => $site_name]);

            $siteInformation = new SiteInformation();

            // Fill in the attribute
            foreach ($attributes as $key => $value) {
                if ($key === 'BUILDNAME') {
                    $this->BuildName = $value;
                } elseif ($key === 'BUILDSTAMP') {
                    $this->BuildStamp = $value;
                } elseif ($key === 'GENERATOR') {
                    $this->Generator = $value;
                } elseif ($key === 'CHANGEID') {
                    $this->PullRequest = $value;
                } else {
                    $siteInformation->SetValue($key, $value);
                    switch ($key) {
                        case 'OSNAME':
                            $this->BuildInformation['osname'] = $value;
                            break;
                        case 'OSRELEASE':
                            $this->BuildInformation['osrelease'] = $value;
                            break;
                        case 'OSVERSION':
                            $this->BuildInformation['osversion'] = $value;
                            break;
                        case 'OSPLATFORM':
                            $this->BuildInformation['osplatform'] = $value;
                            break;
                        case 'COMPILERNAME':
                            $this->BuildInformation['compilername'] = $value;
                            break;
                        case 'COMPILERVERSION':
                            $this->BuildInformation['compilerversion'] = $value;
                            break;
                    }
                }
            }

            if (empty($this->BuildName)) {
                $this->BuildName = '(empty)';
            }
            $this->Site->mostRecentInformation()->save($siteInformation);
        } elseif ($name == 'SUBPROJECT') {
            $this->SubProjectName = $attributes['NAME'];
            if (!array_key_exists($this->SubProjectName, $this->SubProjects)) {
                $this->SubProjects[$this->SubProjectName] = [];
            }
        } elseif ($name == 'DYNAMICANALYSIS') {
            $this->Checker = $attributes['CHECKER'];
            if (empty($this->DynamicAnalysisSummaries)) {
                $summary = $factory->create(DynamicAnalysisSummary::class);
                $summary->Empty = true;
                $summary->Checker = $this->Checker;
                $this->DynamicAnalysisSummaries[$this->SubProjectName] = $summary;
            } else {
                foreach ($this->DynamicAnalysisSummaries as $subprojectName => $summary) {
                    $summary->Checker = $this->Checker;
                }
            }
        } elseif ($name == 'TEST' && isset($attributes['STATUS'])) {
            $this->DynamicAnalysis = $factory->create(DynamicAnalysis::class);
            $this->DynamicAnalysis->Checker = $this->Checker;
            $this->DynamicAnalysis->Status = $attributes['STATUS'];
            $this->TestSubProjectName = '';
        } elseif ($name == 'DEFECT') {
            $this->DynamicAnalysisDefect = $factory->create(DynamicAnalysisDefect::class);
            $this->DynamicAnalysisDefect->Type = $attributes['TYPE'];
        } elseif ($name == 'LABEL') {
            $this->Label = $factory->create(Label::class);
        } elseif ($name == 'LOG') {
            $this->DynamicAnalysis->LogCompression = $attributes['COMPRESSION'] ?? '';
            $this->DynamicAnalysis->LogEncoding = $attributes['ENCODING'] ?? '';
        }
    }

    /** Function endElement */
    public function endElement($parser, $name): void
    {
        $factory = $this->getModelFactory();
        if ($name === 'STARTTESTTIME' && $this->getParent() === 'DYNAMICANALYSIS') {
            if (empty($this->SubProjects)) {
                // Not a SubProject build.
                $this->createBuild('');
            } else {
                // Make sure we have a build for each SubProject.
                foreach ($this->SubProjects as $subproject => $labels) {
                    $this->createBuild($subproject);
                }
            }
        } elseif ($name === 'TEST' && $this->getParent() == 'DYNAMICANALYSIS') {
            /** @var Build $build */
            $build = $this->Builds[$this->SubProjectName];
            $GLOBALS['PHP_ERROR_BUILD_ID'] = $build->Id;
            $this->DynamicAnalysisSummaries[$this->SubProjectName]->Empty = false;
            foreach ($this->DynamicAnalysis->GetDefects() as $defect) {
                $this->DynamicAnalysisSummaries[$this->SubProjectName]->AddDefects(
                    $defect->Value);
            }
            $this->DynamicAnalysis->BuildId = $build->Id;
            $this->DynamicAnalysis->Insert();
            $analysis = clone $this->DynamicAnalysis;
            $build->AddDynamicAnalysis($analysis);
        } elseif ($name == 'DEFECT') {
            $this->DynamicAnalysis->AddDefect($this->DynamicAnalysisDefect);
            unset($this->DynamicAnalysisDefect);
        } elseif ($name == 'LABEL') {
            if (!empty($this->TestSubProjectName)) {
                $this->SubProjectName = $this->TestSubProjectName;
            } elseif (isset($this->DynamicAnalysis)) {
                $this->DynamicAnalysis->AddLabel($this->Label);
            }
        } elseif ($name == 'DYNAMICANALYSIS') {
            foreach ($this->Builds as $subprojectName => $build) {
                // Update this build's end time if necessary.
                $build->EndTime = gmdate(FMT_DATETIME, $this->EndTimeStamp);
                $build->UpdateBuild($build->Id, -1, -1);

                // If everything is perfect CTest doesn't send any <test>
                // But we still want a line showing the current dynamic analysis
                if ($this->DynamicAnalysisSummaries[$subprojectName]->Empty) {
                    $this->DynamicAnalysis = $factory->create(DynamicAnalysis::class);
                    $this->DynamicAnalysis->BuildId = $build->Id;
                    $this->DynamicAnalysis->Status = 'passed';
                    $this->DynamicAnalysis->Checker = $this->Checker;
                    $this->DynamicAnalysis->Insert();
                }
                $this->DynamicAnalysisSummaries[$subprojectName]->Insert();

                // If this is a child build append these defects to the parent's
                // summary.
                $parentid = $build->LookupParentBuildId();
                if ($parentid > 0) {
                    $this->DynamicAnalysisSummaries[$subprojectName]->BuildId = $parentid;
                    $this->DynamicAnalysisSummaries[$subprojectName]->Insert(true);
                }
            }
        }

        parent::endElement($parser, $name);
    }

    /** Function Text */
    public function text($parser, $data)
    {
        $parent = $this->getParent();
        $element = $this->getElement();

        if ($parent == 'DYNAMICANALYSIS') {
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
                    $this->DynamicAnalysis->Name .= $data;
                    break;
                case 'PATH':
                    $this->DynamicAnalysis->Path .= $data;
                    break;
                case 'FULLCOMMANDLINE':
                    $this->DynamicAnalysis->FullCommandLine .= $data;
                    break;
                case 'LOG':
                    $this->DynamicAnalysis->Log .= $data;
                    break;
            }
        } elseif ($parent == 'RESULTS') {
            if ($element == 'DEFECT') {
                $this->DynamicAnalysisDefect->Value .= $data;
            }
        } elseif ($parent == 'SUBPROJECT' && $element == 'LABEL') {
            $this->SubProjects[$this->SubProjectName][] = $data;
        } elseif ($element == 'LABEL') {
            // Check if this label belongs to a SubProject.
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
        return $this->BuildStamp;
    }

    public function getBuildName()
    {
        return $this->BuildName;
    }

    public function getSubProjectName()
    {
        return $this->SubProjectName;
    }

    private function createBuild($subprojectName)
    {
        $factory = $this->getModelFactory();
        $build = $factory->create(Build::class);

        $build->SiteId = $this->Site->id;
        $build->Name = $this->BuildName;

        $build->SetStamp($this->BuildStamp);
        $build->Generator = $this->Generator;
        $build->OSName = $this->BuildInformation['osname'] ?? null;
        $build->OSRelease = $this->BuildInformation['osrelease'] ?? null;
        $build->OSVersion = $this->BuildInformation['osversion'] ?? null;
        $build->OSPlatform = $this->BuildInformation['osplatform'] ?? null;
        $build->CompilerName = $this->BuildInformation['compilername'] ?? null;
        $build->CompilerVersion = $this->BuildInformation['compilerversion'] ?? null;

        $start_time = gmdate(FMT_DATETIME, $this->StartTimeStamp);

        $build->ProjectId = $this->GetProject()->Id;
        $build->StartTime = $start_time;
        // EndTimeStamp hasn't been parsed yet.  We update this value later.
        $build->EndTime = $start_time;
        $build->SubmitTime = gmdate(FMT_DATETIME);
        $build->SetSubProject($subprojectName);

        $build->GetIdFromName($subprojectName);
        $build->RemoveIfDone();

        // If the build doesn't exist we add it
        if ($build->Id == 0) {
            $build->InsertErrors = false;
            SubmissionUtils::add_build($build);
        } else {
            // Otherwise make sure that the build is up-to-date.
            $build->UpdateBuild($build->Id, -1, -1);

            // Remove any previous analysis.
            /** @var DynamicAnalysis $DA */
            $DA = $factory->create(DynamicAnalysis::class);
            $DA->BuildId = $build->Id;
            $DA->RemoveAll();
        }

        $this->Builds[$subprojectName] = $build;

        // Initialize a dynamic analysis summary for this build.
        $summary = $factory->create(DynamicAnalysisSummary::class);
        $summary->Empty = true;
        $summary->BuildId = $build->Id;
        $summary->Checker = $this->Checker;
        $this->DynamicAnalysisSummaries[$subprojectName] = $summary;
    }

    public function getBuild(): Build
    {
        if (count($this->Builds) > 1) {
            $build = new Build();
            $build->Id = array_values($this->Builds)[0]->GetParentId();
            return $build;
        } else {
            return array_values($this->Builds)[0];
        }
    }

    public function GetBuildCollection(): BuildCollection
    {
        $collection = new BuildCollection();
        foreach ($this->Builds as $key => $build) {
            if (is_numeric($key) || empty($key)) {
                $collection->add($build);
            } else {
                $collection->addItem($build, $key);
            }
        }
        return $collection;
    }

    public function GetTopicCollectionForSubscriber(SubscriberInterface $subscriber): TopicCollection
    {
        $collection = new TopicCollection();
        $preferences = $subscriber->getNotificationPreferences();
        if ($preferences->get(NotifyOn::DYNAMIC_ANALYSIS)) {
            $topic = new DynamicAnalysisTopic();
            $collection->add($topic);
        }
        return $collection;
    }

    public function GetSubscriptionBuilderCollection(): SubscriptionBuilderCollection
    {
        $collection = (new SubscriptionBuilderCollection())
            ->add(new UserSubscriptionBuilder($this));
        return $collection;
    }

    public function GetBuildGroup(): BuildGroup
    {
        $buildGroup = new BuildGroup();
        foreach ($this->Builds as $build) {
            if (!$build->GroupId) {
                $build->AssignToGroup();
            }
            $buildGroup->SetId($build->GroupId);
            break;
        }
        return $buildGroup;
    }
}
