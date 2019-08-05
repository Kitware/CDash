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

use CDash\Model\Build;
use CDash\Model\CoverageFile;
use CDash\Model\CoverageFileLog;
use CDash\Model\Project;
use CDash\Model\Site;
use CDash\Model\SubProject;

class CoverageLogHandler extends AbstractHandler
{
    private $StartTimeStamp;
    private $EndTimeStamp;

    private $CurrentCoverageFile;
    private $CurrentCoverageFileLog;
    private $CoverageFiles;

    private $UpdateEndTime;
    private $CurrentLine;

    /** Constructor */
    public function __construct($projectID, $scheduleID)
    {
        parent::__construct($projectID, $scheduleID);
        $this->Build = new Build();
        $this->Site = new Site();
        $this->UpdateEndTime = false;
        $this->CoverageFiles = array();
        $this->CurrentLine = "";
    }

    /** Start element */
    public function startElement($parser, $name, $attributes)
    {
        parent::startElement($parser, $name, $attributes);
        if ($name == 'SITE') {
            $this->Site->Name = $attributes['NAME'];
            if (empty($this->Site->Name)) {
                $this->Site->Name = '(empty)';
            }
            $this->Site->Insert();
            $this->Build->SiteId = $this->Site->Id;
            $this->Build->Name = $attributes['BUILDNAME'];
            if (empty($this->Build->Name)) {
                $this->Build->Name = '(empty)';
            }
            $this->Build->SetStamp($attributes['BUILDSTAMP']);
            $this->Build->Generator = $attributes['GENERATOR'];
        } elseif ($name == 'FILE') {
            $this->CurrentCoverageFile = new CoverageFile();
            $this->CurrentCoverageFileLog = new CoverageFileLog();
            $this->CurrentCoverageFile->FullPath = trim($attributes['FULLPATH']);
        } elseif ($name == 'LINE') {
            if ($attributes['COUNT'] >= 0) {
                $this->CurrentCoverageFileLog->AddLine($attributes['NUMBER'], $attributes['COUNT']);
            }
            $this->CurrentLine = "";
        }
    }

    /** End Element */
    public function endElement($parser, $name)
    {
        parent::endElement($parser, $name);

        if ($name === 'SITE') {
            $start_time = gmdate(FMT_DATETIME, $this->StartTimeStamp);
            $end_time = gmdate(FMT_DATETIME, $this->EndTimeStamp);
            $this->Build->ProjectId = $this->projectid;
            $this->Build->StartTime = $start_time;
            $this->Build->EndTime = $end_time;
            $this->Build->SubmitTime = gmdate(FMT_DATETIME);
            $this->Build->SetSubProject($this->SubProjectName);
            $this->Build->GetIdFromName($this->SubProjectName);
            $this->Build->RemoveIfDone();
            if ($this->Build->Id == 0) {
                // If the build doesn't exist we add it.
                $this->Build->InsertErrors = false;
                add_build($this->Build, $this->scheduleid);
            } else {
                // Otherwise make sure that it's up-to-date.
                $this->Build->UpdateBuild($this->Build->Id, -1, -1);
            }

            // Does this project have subprojects?
            $project = new Project();
            $project->Id = $this->projectid;
            $has_subprojects = $project->GetNumberOfSubProjects() > 0;

            // Record the coverage data that we parsed from this file.
            foreach ($this->CoverageFiles as $coverageInfo) {
                $coverageFile = $coverageInfo[0];
                if (empty($coverageFile->FullPath)) {
                    continue;
                }
                $coverageFileLog = $coverageInfo[1];
                $coverageFile->TrimLastNewline();

                $buildid = $this->Build->Id;
                if ($has_subprojects) {
                    // Make sure this file gets associated with the correct
                    // subproject based on its path.
                    $subproject = SubProject::GetSubProjectFromPath(
                            $coverageFile->FullPath, $this->projectid);
                    if (!is_null($subproject)) {
                        $subprojectBuild = Build::GetSubProjectBuild(
                                $this->Build->GetParentId(), $subproject->GetId());
                        if (is_null($subprojectBuild)) {
                            // This SubProject build doesn't exist yet, add it here.
                            $subprojectBuild = new Build();
                            $subprojectBuild->ProjectId = $this->projectid;
                            $subprojectBuild->Name = $this->Build->Name;
                            $subprojectBuild->SiteId = $this->Build->SiteId;
                            $subprojectBuild->SetParentId($this->Build->GetParentId());
                            $subprojectBuild->SetStamp($this->Build->GetStamp());
                            $subprojectBuild->SetSubProject($subproject->GetName());
                            $subprojectBuild->StartTime = $this->Build->StartTime;
                            $subprojectBuild->EndTime = $this->Build->EndTime;
                            $subprojectBuild->SubmitTime = gmdate(FMT_DATETIME);
                            add_build($subprojectBuild, 0);
                        }
                        $buildid = $subprojectBuild->Id;
                    }
                }
                $coverageFile->Update($buildid);
                $coverageFileLog->BuildId = $buildid;
                $coverageFileLog->FileId = $coverageFile->Id;
                $coverageFileLog->Insert(true);
            }
        } elseif ($name == 'LINE') {
            $this->CurrentCoverageFile->File .= rtrim($this->CurrentLine);
            // Cannot be <br/> for backward compatibility.
            $this->CurrentCoverageFile->File .= '<br>';
        } elseif ($name == 'FILE') {
            // Store these objects to be inserted after we're guaranteed
            // to have a valid buildid.
            $this->CoverageFiles[] = array($this->CurrentCoverageFile,
                $this->CurrentCoverageFileLog);
        } elseif ($name == 'COVERAGELOG') {
            if (empty($this->CoverageFiles)) {
                // Store these objects to be inserted after we're guaranteed
                // to have a valid buildid.
                $this->CoverageFiles[] = array(new CoverageFile(), new CoverageFileLog());
            }
        }
    }

    /** Text */
    public function text($parser, $data)
    {
        $element = $this->getElement();
        switch ($element) {
            case 'LINE':
                $this->CurrentLine .= $data;
                break;
            case 'STARTTIME':
                $this->StartTimeStamp = $data;
                break;
            case 'ENDTIME':
                $this->EndTimeStamp = $data;
                break;
        }
    }
}
