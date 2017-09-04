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
require_once 'models/build.php';
require_once 'models/label.php';
require_once 'models/site.php';
require_once 'models/buildconfigure.php';

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
        $this->Builds = array();
        $this->SubProjects = array();
        $this->Site = new Site();
        $this->Configure = new BuildConfigure();
        $this->StartTimeStamp = 0;
        $this->EndTimeStamp = 0;
        // Only complain about errors & warnings once.
        $this->Notified = false;
    }

    public function startElement($parser, $name, $attributes)
    {
        parent::startElement($parser, $name, $attributes);

        if ($name == 'SITE') {
            $this->Site->Name = $attributes['NAME'];
            if (empty($this->Site->Name)) {
                $this->Site->Name = '(empty)';
            }
            $this->Site->Insert();

            $siteInformation = new SiteInformation();
            $this->BuildInformation = new BuildInformation();
            $this->BuildInformation = new BuildInformation();
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
                $this->SubProjects[$this->SubProjectName] = array();
            }
            if (!array_key_exists($this->SubProjectName, $this->Builds)) {
                $build = new Build();
                $build->SiteId = $this->Site->Id;
                $build->Name = $this->BuildName;
                $build->SetStamp($this->BuildStamp);
                $build->Generator = $this->Generator;
                $build->Information = $this->BuildInformation;
                $this->Builds[$this->SubProjectName] = $build;
            }
        } elseif ($name == 'CONFIGURE') {
            if (empty($this->Builds)) {
                // No subprojects
                $build = new Build();
                $build->SiteId = $this->Site->Id;
                $build->Name = $this->BuildName;
                $build->SetStamp($this->BuildStamp);
                $build->Generator = $this->Generator;
                $build->Information = $this->BuildInformation;
                $this->Builds[''] = $build;
            }
        } elseif ($name == 'LABEL') {
            $this->Label = new Label();
        }
    }

    public function endElement($parser, $name)
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

            foreach ($this->Builds as $subproject => $build) {
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
                    $parent_build = new Build();
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

        if ($parent == 'CONFIGURE') {
            switch ($element) {
                case 'STARTDATETIME':
                    $this->StartTimeStamp = str_to_time($data, $this->BuildStamp);
                    break;
                case 'STARTCONFIGURETIME':
                    $this->StartTimeStamp = $data;
                    break;
                case 'ENDCONFIGURETIME':
                    $this->EndTimeStamp = $data;
                    break;
                case 'ELAPSEDMINUTES':
                    if ($this->EndTimeStamp === 0) {
                        $this->EndTimeStamp = $this->StartTimeStamp + $data * 60;
                    }
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
                    $this->Configure->Status .= $data;
                    break;
            }
        } elseif ($parent == 'SUBPROJECT' && $element == 'LABEL') {
            $this->SubProjects[$this->SubProjectName][] =  $data;
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
     */
    public function getActionableBuilds()
    {
        return $this->Builds;
    }
}
