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

    private $Configure;
    private $Label;

    public function __construct($projectid, $scheduleid)
    {
        parent::__construct($projectid, $scheduleid);
        $this->Build = new Build();
        $this->Site = new Site();
        $this->Configure = new BuildConfigure();
        $this->StartTimeStamp = 0;
        $this->EndTimeStamp = 0;
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
            $buildInformation = new BuildInformation();

            // Fill in the attribute
            foreach ($attributes as $key => $value) {
                if ($key === 'CHANGEID') {
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
                $this->Build->Name = '(empty)';
            }
            $this->Build->SetStamp($attributes['BUILDSTAMP']);
            $this->Build->Generator = $attributes['GENERATOR'];
            $this->Build->Information = $buildInformation;
        } elseif ($name == 'LABEL') {
            $this->Label = new Label();
        }
    }

    public function endElement($parser, $name)
    {
        parent::endElement($parser, $name);

        if ($name == 'CONFIGURE') {
            $start_time = gmdate(FMT_DATETIME, $this->StartTimeStamp);
            $end_time = gmdate(FMT_DATETIME, $this->EndTimeStamp);

            $this->Build->ProjectId = $this->projectid;
            $this->Build->ProjectId = $this->projectid;
            $this->Build->StartTime = $start_time;
            $this->Build->EndTime = $end_time;
            $this->Build->SubmitTime = gmdate(FMT_DATETIME);
            $this->Build->SetSubProject($this->SubProjectName);
            $this->Build->InsertErrors = false;

            $this->Build->GetIdFromName($this->SubProjectName);
            $this->Build->RemoveIfDone();
            if ($this->Build->Id == 0) {
                // If the build doesn't exist we add it
                add_build($this->Build, $this->scheduleid);
            } else {
                // Otherwise we make sure that it's up-to-date.
                $this->Build->UpdateBuild($this->Build->Id, -1, -1);
            }
            $GLOBALS['PHP_ERROR_BUILD_ID'] = $this->Build->Id;
            $this->Configure->BuildId = $this->Build->Id;
            $this->Configure->StartTime = $start_time;
            $this->Configure->EndTime = $end_time;

            // Insert the configure
            if ($this->Configure->Exists()) {
                $this->Configure->Delete();
            }
            if ($this->Configure->Insert()) {
                // Insert errors from the log file
                $this->Configure->ComputeWarnings();
                $this->Configure->ComputeErrors();
            }

            // Record the number of warnings & errors with the build.
            $this->Build->SetNumberOfConfigureWarnings(
                $this->Configure->NumberOfWarnings);
            $this->Build->SetNumberOfConfigureErrors(
                $this->Configure->NumberOfErrors);

            $this->Build->ComputeConfigureDifferences();

            // Record configure duration with the build.
            $this->Build->SetConfigureDuration(
                $this->EndTimeStamp - $this->StartTimeStamp);

            // Update the tally of warnings & errors in the parent build,
            // if applicable.
            $this->Build->UpdateParentConfigureNumbers(
                $this->Configure->NumberOfWarnings, $this->Configure->NumberOfErrors);
        } elseif ($name == 'LABEL') {
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
                    $this->StartTimeStamp = str_to_time($data, $this->Build->GetStamp());
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

        if ($element == 'LABEL') {
            $this->Label->SetText($data);
        }
    }
}
