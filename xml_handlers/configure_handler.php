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
require_once 'xml_handlers/actionable_build_interface.php';

use CDash\Model\Build;
use CDash\Model\BuildConfigure;
use CDash\Model\BuildInformation;
use CDash\Model\Label;
use CDash\Model\Site;
use CDash\Model\SiteInformation;

use CDash\Collection\BuildCollection;

class ConfigureHandler extends AbstractHandler implements ActionableBuildInterface
{
    private $StartTimeStamp;
    private $EndTimeStamp;
    private $Builds;
    private $BuildInformation;
    // Map SubProjects to Labels
    private $SubProjects;
    private $Configure;
    private $Label;
    private $Notified;
    private $BuildName;
    private $BuildStamp;
    private $Generator;
    private $PullRequest;

    public function __construct($projectid, $scheduleid)
    {
        parent::__construct($projectid, $scheduleid);
        $this->Builds = [];
        $this->SubProjects = [];
        $this->StartTimeStamp = 0;
        $this->EndTimeStamp = 0;
        // Only complain about errors & warnings once.
        $this->Notified = false;
    }

    public function startElement($parser, $name, $attributes)
    {
        parent::startElement($parser, $name, $attributes);
        $factory = $this->getModelFactory();

        if ($name == 'SITE') {
            $this->Site = $factory->create(Site::class);
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
        } elseif ($name == 'SUBPROJECT') {
            $this->SubProjectName = $attributes['NAME'];
            if (!array_key_exists($this->SubProjectName, $this->SubProjects)) {
                $this->SubProjects[$this->SubProjectName] = [];
            }
            if (!array_key_exists($this->SubProjectName, $this->Builds)) {
                $build = $factory->create(Build::class); // new Build();
                $build->SiteId = $this->Site->Id;
                $build->Name = $this->BuildName;
                $build->SetStamp($this->BuildStamp);
                $build->Generator = $this->Generator;
                $build->Information = $this->BuildInformation;
                $this->Builds[$this->SubProjectName] = $build;
                $build->SetActionableType(ActionableTypes::CONFIGURE);
            }
        } elseif ($name == 'CONFIGURE') {
            $this->Configure = $factory->create(BuildConfigure::class);
            if (empty($this->Builds)) {
                // No subprojects
                $build = $factory->create(Build::class);
                $build->SiteId = $this->Site->Id;
                $build->Name = $this->BuildName;
                $build->SetStamp($this->BuildStamp);
                $build->Generator = $this->Generator;
                $build->Information = $this->BuildInformation;
                $this->Builds[] = $build;
            }
        } elseif ($name == 'LABEL') {
            $this->Label = $factory->create(Label::class);
        }
    }

    public function endElement($parser, $name)
    {
        $parent = $this->getParent();
        $factory = $this->getModelFactory();

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

            foreach ($this->Builds as $subproject => $build) {
                $build->SetBuildConfigure($this->Configure);
                $build->ProjectId = $this->projectid;
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
                    add_build($build, $this->scheduleid);
                } else {
                    // Otherwise we make sure that it's up-to-date.
                    $build->UpdateBuild($build->Id, -1, -1);
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
                $build->SetNumberOfConfigureWarnings(
                        $this->Configure->NumberOfWarnings);
                $build->SetNumberOfConfigureErrors(
                        $this->Configure->NumberOfErrors);

                $build->ComputeConfigureDifferences();

                // Record configure duration with the build.
                $duration = $this->EndTimeStamp - $this->StartTimeStamp;
                $build->SetConfigureDuration($duration, !$all_at_once);
                if ($all_at_once && !$parent_duration_set) {
                    $parent_build = $factory->create(Build::class);
                    $parent_build->Id = $build->GetParentId();
                    $parent_build->SetConfigureDuration($duration, false);
                    $parent_duration_set = true;
                }
            }

            // Update the tally of warnings & errors in the parent build,
            // if applicable.
            // All subprojects share the same configure file and parent build,
            // so only need to do this once
            $build->UpdateParentConfigureNumbers(
                    $this->Configure->NumberOfWarnings, $this->Configure->NumberOfErrors);
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
        $factory = $this->getModelFactory();

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
            $label = $factory->create(Label::class);
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

    /**
     * @return Build[]
     * @deprecated use GetBuildCollection
     */
    public function getBuilds()
    {
        return array_values($this->Builds);
    }
    public function getActionableBuilds()
    {
        return $this->Builds;
    }

    /**
     * @return BuildCollection
     * TODO: Looks like this method can be moved into AbstractHandler
     */
    public function GetBuildCollection()
    {
        return new BuildCollection($this->Builds);
    }

    public function getProjectId()
    {
        // TODO: Implement getProjectId() method.
    }
}
