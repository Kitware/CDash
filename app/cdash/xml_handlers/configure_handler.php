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

use App\Utils\SubmissionUtils;
use CDash\Collection\SubscriptionBuilderCollection;
use CDash\Messaging\Notification\NotifyOn;
use CDash\Messaging\Subscription\UserSubscriptionBuilder;
use CDash\Messaging\Topic\ConfigureTopic;
use CDash\Messaging\Topic\TopicCollection;
use CDash\Model\Build;
use CDash\Model\BuildConfigure;
use CDash\Model\BuildGroup;
use CDash\Model\Label;
use App\Models\Site;
use App\Models\SiteInformation;

use CDash\Collection\BuildCollection;
use CDash\Model\Project;
use CDash\Model\SubscriberInterface;

class ConfigureHandler extends AbstractXmlHandler implements ActionableBuildInterface
{
    private $StartTimeStamp;
    private $EndTimeStamp;
    private $Builds;
    private array $BuildInformation;
    // Map SubProjects to Labels
    private $SubProjects;
    private $Configure;
    private $Label;
    private $Notified;
    private $BuildName;
    private $BuildStamp;
    private $Generator;
    private $PullRequest;
    public static string $schema_file = "/app/Validators/Schemas/Configure.xsd";

    public function __construct(Project $project)
    {
        parent::__construct($project);

        $this->Builds = [];
        $this->SubProjects = [];
        $this->StartTimeStamp = 0;
        $this->EndTimeStamp = 0;
        // Only complain about errors & warnings once.
        $this->Notified = false;
        // Instantiate model factory.
        $this->getModelFactory();
    }

    public function startElement($parser, $name, $attributes): void
    {
        parent::startElement($parser, $name, $attributes);

        if ($name == 'SITE') {
            $sitename = !empty($attributes['NAME']) ? $attributes['NAME'] : '(empty)';
            $this->Site = Site::firstOrCreate(['name' => $sitename], ['name' => $sitename]);

            $siteInformation = new SiteInformation;
            $this->BuildInformation = [];
            $this->BuildName = "";
            $this->BuildStamp = "";
            $this->SubProjectName = "";
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
            if (!array_key_exists($this->SubProjectName, $this->Builds)) {
                $build = $this->CreateBuild();
                $build->SubProjectName = $this->SubProjectName;
                $this->Builds[$this->SubProjectName] = $build;
            }
        } elseif ($name == 'CONFIGURE') {
            $this->Configure = $this->getModelFactory()->create(BuildConfigure::class);
            if (empty($this->Builds)) {
                // No subprojects
                $this->Builds[] = $this->CreateBuild();
            }
        } elseif ($name == 'LABEL') {
            $this->Label = $this->getModelFactory()->create(Label::class);
        }
    }

    public function endElement($parser, $name): void
    {
        $parent = $this->getParent();

        parent::endElement($parser, $name);

        if ($name == 'CONFIGURE') {
            $start_time = gmdate(FMT_DATETIME, $this->StartTimeStamp);
            $end_time = gmdate(FMT_DATETIME, $this->EndTimeStamp);
            // Configure Duration is handled differently if this XML
            // file represents multiple builds.  We refer to this situation
            // as an "all at once" SubProject build.  In this case, we do
            // not want to add each build's configure duration to the parent's
            // tally.
            $all_at_once = count($this->Builds) > 1;
            $parent_duration_set = false;

            /**
             * @var string $subproject
             * @var  Build $build
             */
            foreach ($this->Builds as $subproject => $build) {
                $build->ProjectId = $this->GetProject()->Id;
                $build->StartTime = $start_time;
                $build->EndTime = $end_time;
                $build->SubmitTime = gmdate(FMT_DATETIME);
                if (empty($subproject)) {
                    $build->SetSubProject($this->SubProjectName);
                    $build->GetIdFromName($this->SubProjectName);
                } else {
                    $build->SetSubProject($subproject);
                    $build->GetIdFromName($subproject);
                }
                $build->InsertErrors = false;
                if (!$this->Notified && !empty($this->PullRequest)) {
                    // Only perform PR notification for the first build parsed.
                    $build->SetPullRequest($this->PullRequest);
                    $this->Notified = true;
                }

                $build->RemoveIfDone();
                if ($build->Id == 0) {
                    // If the build doesn't exist we add it
                    SubmissionUtils::add_build($build);
                } else {
                    // Otherwise we make sure that it's up-to-date.
                    $build->UpdateBuild($build->Id, -1, -1);

                    // Honor the Append flag if this build already existed.
                    if ($this->Append) {
                        // Get existing log & status from the database.
                        $existing_config = $this->getModelFactory()->create(BuildConfigure::class);
                        $existing_config->BuildId = $build->Id;
                        if ($existing_config->Exists()) {
                            $existing_config_results = $existing_config->GetConfigureForBuild();
                            if ($existing_config_results) {
                                // Combine these with the data we just parsed out of the XML.
                                $this->Configure->Log = $existing_config_results['log'] . "\n" . $this->Configure->Log;
                                $this->Configure->Status = intval($existing_config_results['status']) + intval($this->Configure->Status);

                                // Also reuse the prior start time for this configure step.
                                $existing_start_timestamp = strtotime($existing_config_results['starttime'] . ' UTC');
                                if ($existing_start_timestamp) {
                                    $this->StartTimeStamp = $existing_start_timestamp;
                                }
                            }
                            // Delete the existing configure for this build.
                            // A new one will be created below.
                            $existing_config->Delete();
                        }
                    }
                }
                $GLOBALS['PHP_ERROR_BUILD_ID'] = $build->Id;
                $this->Configure->BuildId = $build->Id;
                $this->Configure->StartTime = $start_time;
                $this->Configure->EndTime = $end_time;

                // Insert the configure
                if ($this->Configure->Insert()) {
                    // Insert errors from the log file
                    $this->Configure->ComputeWarnings();
                    $this->Configure->ComputeErrors();
                }

                // Record the number of warnings & errors with the build.
                $build->SetNumberOfConfigureWarnings((int) $this->Configure->NumberOfWarnings);
                $build->SetNumberOfConfigureErrors((int) $this->Configure->NumberOfErrors);

                $build->ComputeConfigureDifferences();

                // Record configure duration with the build.
                $duration = $this->EndTimeStamp - $this->StartTimeStamp;
                $build->SetConfigureDuration((int) $duration, !$all_at_once);
                if ($all_at_once && !$parent_duration_set) {
                    $parent_build = $this->getModelFactory()->create(Build::class);
                    $parent_build->Id = $build->GetParentId();
                    $parent_build->SetConfigureDuration($duration, false);
                    $parent_duration_set = true;
                }

                $configure = clone $this->Configure;
                $build->SetBuildConfigure($configure);
            }

            // Update the tally of warnings & errors in the parent build,
            // if applicable.
            // All subprojects share the same configure file and parent build,
            // so only need to do this once
            $build->UpdateParentConfigureNumbers(
                (int) $this->Configure->NumberOfWarnings, (int) $this->Configure->NumberOfErrors);
        } elseif ($name == 'LABEL' && $parent == 'LABELS') {
            if (isset($this->Configure)) {
                $this->Configure->AddLabel($this->Label);
            }
        }
    }

    public function text($parser, $data)
    {
        $parent = $this->getParent();
        $element = $this->getElement();

        if ($parent == 'CONFIGURE') {
            switch ($element) {
                case 'STARTCONFIGURETIME':
                    $this->StartTimeStamp = $data;
                    break;
                case 'ENDCONFIGURETIME':
                    $this->EndTimeStamp = $data;
                    break;
                case 'BUILDCOMMAND':
                    $this->Configure->Command .= $data;
                    break;
                case 'LOG':
                    $this->Configure->Log .= $data;
                    break;
                case 'CONFIGURECOMMAND':
                    $this->Configure->Command .= $data;
                    break;
                case 'CONFIGURESTATUS':
                    // TODO: discuss
                    // What is the .= here for? Are there ever more than
                    // one ConfigureStatus or is this method fed data from a buffer?
                    // That StartTimeStamp is not .= leads me to believe
                    // that this is not necessary and asking for trouble. Further
                    // investigation reveals that these properties are not initialized
                    // so the concat operation is appending a null with (presumably) a string;
                    // also not desirable.
                    $this->Configure->Status .= $data;
                    $this->Configure->NumberOfErrors = $data;
                    break;
            }
        } elseif ($parent == 'SUBPROJECT' && $element == 'LABEL') {
            $this->SubProjects[$this->SubProjectName][] =  $data;
            $build = $this->Builds[$this->SubProjectName];
            $label = $this->getModelFactory()->create(Label::class);
            $label->Text = $data;
            $build->AddLabel($label);
        } elseif ($parent == 'LABELS' && $element == 'LABEL') {
            // First, check if this label belongs to a SubProject
            $subproject_name = "";
            foreach ($this->SubProjects as $subproject => $labels) {
                if (in_array($data, $labels)) {
                    $subproject_name = $subproject;
                    break;
                }
            }
            if (empty($subproject_name)) {
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
        foreach ($this->Builds as $build) {
            $collection->add($build);
        }
        return $collection;
    }

    public function GetTopicCollectionForSubscriber(SubscriberInterface $subscriber): TopicCollection
    {
        $collection = new TopicCollection();
        $preferences = $subscriber->getNotificationPreferences();
        if ($preferences->get(NotifyOn::CONFIGURE)) {
            $topic = new ConfigureTopic();
            $collection->add($topic);
        }
        return $collection;
    }

    public function GetSubscriptionBuilderCollection(): SubscriptionBuilderCollection
    {
        $collection = (new SubscriptionBuilderCollection)
            ->add(new UserSubscriptionBuilder($this));
        return $collection;
    }

    public function GetBuildGroup(): BuildGroup
    {
        $buildGroup = new BuildGroup();
        foreach ($this->Builds as $build) {
            $buildGroup->SetId($build->GroupId);
            break;
        }
        return $buildGroup;
    }

    protected function CreateBuild()
    {
        $build = $this->getModelFactory()->create(Build::class);
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
        return $build;
    }
}
