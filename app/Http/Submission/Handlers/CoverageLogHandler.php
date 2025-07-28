<?php

namespace App\Http\Submission\Handlers;

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
use App\Utils\SubmissionUtils;
use CDash\Model\Build;
use CDash\Model\CoverageFile;
use CDash\Model\CoverageFileLog;
use CDash\Model\Project;
use CDash\Model\SubProject;

class CoverageLogHandler extends AbstractXmlHandler
{
    private $StartTimeStamp;
    private $EndTimeStamp;

    private $CurrentCoverageFile;
    private $CurrentCoverageFileLog;
    private $CoverageFiles;

    private $CurrentLine;

    protected static ?string $schema_file = '/app/Validators/Schemas/CoverageLog.xsd';

    /** Constructor */
    public function __construct(Project $project)
    {
        parent::__construct($project);
        $this->Site = new Site();
        $this->CoverageFiles = [];
        $this->CurrentLine = '';
    }

    /** Start element */
    public function startElement($parser, $name, $attributes): void
    {
        parent::startElement($parser, $name, $attributes);
        if ($this->currentPathMatches('site')) {
            $site_name = !empty($attributes['NAME']) ? $attributes['NAME'] : '(empty)';
            $this->Site = Site::firstOrCreate(['name' => $site_name], ['name' => $site_name]);

            $this->Build->SiteId = $this->Site->id;
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
            $this->CurrentLine = '';
        }
    }

    /** End Element */
    public function endElement($parser, $name): void
    {
        if ($this->currentPathMatches('site')) {
            $start_time = gmdate(FMT_DATETIME, $this->StartTimeStamp);
            $end_time = gmdate(FMT_DATETIME, $this->EndTimeStamp);
            $this->Build->ProjectId = $this->GetProject()->Id;
            $this->Build->StartTime = $start_time;
            $this->Build->EndTime = $end_time;
            $this->Build->SubmitTime = gmdate(FMT_DATETIME);
            $this->Build->SetSubProject($this->SubProjectName);
            $this->Build->GetIdFromName($this->SubProjectName);
            $this->Build->RemoveIfDone();
            if ($this->Build->Id == 0) {
                // If the build doesn't exist we add it.
                $this->Build->InsertErrors = false;
                SubmissionUtils::add_build($this->Build);
            } else {
                // Otherwise make sure that it's up-to-date.
                $this->Build->UpdateBuild($this->Build->Id, -1, -1);
            }

            // Does this project have subprojects?
            $project = new Project();
            $project->Id = $this->GetProject()->Id;
            $has_subprojects = $project->GetNumberOfSubProjects() > 0;

            // Record the coverage data that we parsed from this file.
            foreach ($this->CoverageFiles as $coverageInfo) {
                $coverageFile = $coverageInfo[0];
                if (empty($coverageFile->FullPath)) {
                    continue;
                }
                $coverageFileLog = $coverageInfo[1];

                $buildid = $this->Build->Id;
                if ($has_subprojects) {
                    // Make sure this file gets associated with the correct
                    // subproject based on its path.
                    $subproject = SubProject::GetSubProjectFromPath(
                        $coverageFile->FullPath, intval($this->GetProject()->Id));
                    if (!is_null($subproject)) {
                        $subprojectBuild = Build::GetSubProjectBuild(
                            $this->Build->GetParentId(), $subproject->GetId());
                        if (is_null($subprojectBuild)) {
                            // This SubProject build doesn't exist yet, add it here.
                            $subprojectBuild = new Build();
                            $subprojectBuild->ProjectId = $this->GetProject()->Id;
                            $subprojectBuild->Name = $this->Build->Name;
                            $subprojectBuild->SiteId = $this->Build->SiteId;
                            $subprojectBuild->SetParentId($this->Build->GetParentId());
                            $subprojectBuild->SetStamp($this->Build->GetStamp());
                            $subprojectBuild->SetSubProject($subproject->GetName());
                            $subprojectBuild->StartTime = $this->Build->StartTime;
                            $subprojectBuild->EndTime = $this->Build->EndTime;
                            $subprojectBuild->SubmitTime = gmdate(FMT_DATETIME);
                            SubmissionUtils::add_build($subprojectBuild);
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
            $this->CurrentCoverageFile->File .= $this->CurrentLine . PHP_EOL;
        } elseif ($name == 'FILE') {
            // Store these objects to be inserted after we're guaranteed
            // to have a valid buildid.
            $this->CoverageFiles[] = [$this->CurrentCoverageFile,
                $this->CurrentCoverageFileLog];
        } elseif ($name == 'COVERAGELOG') {
            if (empty($this->CoverageFiles)) {
                // Store these objects to be inserted after we're guaranteed
                // to have a valid buildid.
                $this->CoverageFiles[] = [new CoverageFile(), new CoverageFileLog()];
            }
        }

        parent::endElement($parser, $name);
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
