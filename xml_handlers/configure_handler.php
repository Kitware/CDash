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
require_once 'models/buildconfigure.php';

class ConfigureHandler extends AbstractHandler
{
    private $StartTimeStamp;
    private $EndTimeStamp;
    private $Builds;
    private $BuildInformation;
    private $Configure;
    private $Label;
    private $Notified;
    // Map SubProjects to Labels
    private $SubProjects;
    private $LabelIsSubproject;

    public function __construct($projectid, $scheduleid)
    {
        parent::__construct($projectid, $scheduleid);
        $this->Builds = array();
        $this->Site = new Site();
        $this->Configure = new BuildConfigure();
        $this->StartTimeStamp = 0;
        $this->EndTimeStamp = 0;
        // Only complain about errors & warnings once.
        $this->Notified = false;
        $this->SubProjects = array();
        $this->LabelIsSubproject = true;
    }

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
        } elseif ($name == 'SUBPROJECT') {
            $this->SubProjectName = $attributes['NAME'];
            if (!array_key_exists($this->SubProjectName, $this->SubProjects)) {
                $this->SubProjects[$this->SubProjectName] = array();
            }
            $build = new Build();
            $build->SiteId = $this->Site->Id;
            $build->Name = $this->BuildInformation->BuildName;
            $build->SetStamp($this->BuildInformation->BuildStamp);
            $build->Generator = $this->BuildInformation->Generator;
            $build->Information = $this->BuildInformation;
            $this->Builds[$this->SubProjectName] = $build;
        } elseif ($name == 'CONFIGURE') {
            if (empty($this->Builds)) {
                // No subprojects
                $build = new Build();
                $build->SiteId = $this->Site->Id;
                $build->Name = $this->BuildInformation->BuildName;
                $build->SetStamp($this->BuildInformation->BuildStamp);
                $build->Generator = $this->BuildInformation->Generator;
                $build->Information = $this->BuildInformation;
                $this->Builds[''] = $build;
            }
        } elseif ($name == 'LABEL' && $parent == 'LABELS') {
            $this->Label = new Label();
        }
    }

    public function endElement($parser, $name)
    {
        $parent = $this->getParent(); // should be before endElement
        parent::endElement($parser, $name);

        if ($name == 'CONFIGURE') {
            $start_time = gmdate(FMT_DATETIME, $this->StartTimeStamp);
            $end_time = gmdate(FMT_DATETIME, $this->EndTimeStamp);
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

                $build->ComputeConfigureDifferences();

                if (!$this->Notified && !empty($this->BuildInformation->PullRequest)) {
                    // Only perform PR notification for the first build parsed.
                    $build->SetPullRequest($this->BuildInformation->PullRequest);
                    $this->Notified = true;
                }

                // Record the number of warnings & errors with the build.
                $build->SetNumberOfConfigureWarnings(
                        $this->Configure->NumberOfWarnings);
                $build->SetNumberOfConfigureErrors(
                        $this->Configure->NumberOfErrors);

                // Record configure duration with the build.
                $build->SetConfigureDuration(
                        $this->EndTimeStamp - $this->StartTimeStamp);
            }

            // Update the tally of warnings & errors in the parent build,
            // if applicable.
            // All subprojects share the same configure file and parent build,
            // so only need to do this once
            $build->UpdateParentConfigureNumbers(
                    $this->Configure->NumberOfWarnings, $this->Configure->NumberOfErrors);
        } elseif ($name == 'LABEL' && $parent == 'LABELS') {
           if (!$this->LabelIsSubproject && isset($this->Configure)) {
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
                    $this->StartTimeStamp = str_to_time($data, $this->BuildInformation->BuildStamp);
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
        }

        if ($parent == 'SUBPROJECT' && $element == 'LABEL') {
           $this->SubProjects[$this->SubProjectName][] =  $data;
        } elseif ($parent == 'LABELS' && $element == 'LABEL') {
            // First, check if this label belongs to a SubProject
            foreach ($this->SubProjects as $subproject => $labels) {
              if (in_array($data, $labels)) {
                $this->LabelIsSubproject = true;
                break;
              }
            }
            if ($this->LabelIsSubproject) {
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
}
