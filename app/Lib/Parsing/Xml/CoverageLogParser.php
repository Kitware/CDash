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

use CDash\Model\Build;
use CDash\Model\CoverageFile;
use CDash\Model\CoverageFileLog;
use CDash\Model\Project;
use CDash\Model\Site;
use CDash\Model\SubProject;

/**
 * Class CoverageLogParser
 * @package CDash\Lib\Parsing\Xml
 */
class CoverageLogParser extends AbstractXmlParser
{
    private $currentCoverageFile;
    private $currentCoverageFileLog;
    private $coverageFiles;

    private $updateEndTime;
    private $currentLine;

    /**
     * CoverageLogParser constructor.
     * @param $projectId
     */
    public function __construct($projectId)
    {
        parent::__construct($projectId);
        $this->build = $this->getInstance(Build::class);
        $this->site = $this->getInstance(Site::class);
        $this->updateEndTime = false;
        $this->coverageFiles = [];
        $this->currentLine = "";
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
        if ($name == 'SITE') {
            $this->site->Name = $attributes['NAME'];
            if (empty($this->site->Name)) {
                $this->site->Name = '(empty)';
            }
            $this->site->Insert();
            $this->build->SiteId = $this->site->Id;
            $this->build->Name = $attributes['BUILDNAME'];
            if (empty($this->build->Name)) {
                $this->build->Name = '(empty)';
            }
            $this->build->SetStamp($attributes['BUILDSTAMP']);
            $this->build->Generator = $attributes['GENERATOR'];
        } elseif ($name == 'FILE') {
            $this->currentCoverageFile = $this->getInstance(CoverageFile::class);
            $this->currentCoverageFileLog = $this->getInstance(CoverageFileLog::class);
            $this->currentCoverageFile->FullPath = trim($attributes['FULLPATH']);
        } elseif ($name == 'LINE') {
            if ($attributes['COUNT'] >= 0) {
                $this->currentCoverageFileLog->AddLine($attributes['NUMBER'], $attributes['COUNT']);
            }
            $this->currentLine = "";
        }
    }

    /**
     * @param $parser
     * @param $name
     * @return mixed|void
     */
    public function endElement($parser, $name)
    {
        parent::endElement($parser, $name);

        if ($name === 'SITE') {
            $start_time = gmdate(FMT_DATETIME, $this->startTimeStamp);
            $end_time = gmdate(FMT_DATETIME, $this->endTimeStamp);
            $this->build->ProjectId = $this->projectId;
            $this->build->StartTime = $start_time;
            $this->build->EndTime = $end_time;
            $this->build->SubmitTime = gmdate(FMT_DATETIME);
            $this->build->SetSubProject($this->subProjectName);
            $this->build->GetIdFromName($this->subProjectName);
            $this->build->RemoveIfDone();
            if ($this->build->Id == 0) {
                // If the build doesn't exist we add it.
                $this->build->InsertErrors = false;
                add_build($this->build, $this->scheduleId);
            } else {
                // Otherwise make sure that it's up-to-date.
                $this->build->UpdateBuild($this->build->Id, -1, -1);
            }

            // Does this project have subprojects?
            $project = $this->getInstance(Project::class);
            $project->Id = $this->projectId;
            $has_subprojects = $project->GetNumberOfSubProjects() > 0;

            // Record the coverage data that we parsed from this file.
            foreach ($this->coverageFiles as $coverageInfo) {
                $coverageFile = $coverageInfo[0];
                if (empty($coverageFile->FullPath)) {
                    continue;
                }
                $coverageFileLog = $coverageInfo[1];
                $coverageFile->TrimLastNewline();

                $buildid = $this->build->Id;
                if ($has_subprojects) {
                    // Make sure this file gets associated with the correct
                    // subproject based on its path.
                    $subproject = SubProject::GetSubProjectFromPath(
                        $coverageFile->FullPath, $this->projectId);
                    if (!is_null($subproject)) {
                        $subprojectBuild = Build::GetSubProjectBuild(
                            $this->build->GetParentId(), $subproject->GetId());
                        if (is_null($subprojectBuild)) {
                            // This SubProject build doesn't exist yet, add it here.
                            $subprojectBuild = $this->getInstance(Build::class);
                            $subprojectBuild->ProjectId = $this->projectId;
                            $subprojectBuild->Name = $this->build->Name;
                            $subprojectBuild->SiteId = $this->build->SiteId;
                            $subprojectBuild->SetParentId($this->build->GetParentId());
                            $subprojectBuild->SetStamp($this->build->GetStamp());
                            $subprojectBuild->SetSubProject($subproject->GetName());
                            $subprojectBuild->StartTime = $this->build->StartTime;
                            $subprojectBuild->EndTime = $this->build->EndTime;
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
            $this->currentCoverageFile->File .= rtrim($this->currentLine);
            // Cannot be <br/> for backward compatibility.
            $this->currentCoverageFile->File .= '<br>';
        } elseif ($name == 'FILE') {
            // Store these objects to be inserted after we're guaranteed
            // to have a valid buildid.
            $this->coverageFiles[] = array($this->currentCoverageFile,
                $this->currentCoverageFileLog);
        } elseif ($name == 'COVERAGELOG') {
            if (empty($this->coverageFiles)) {
                // Store these objects to be inserted after we're guaranteed
                // to have a valid buildid.
                $this->coverageFiles[] = [
                    $this->getInstance(CoverageFile::class),
                    $this->getInstance(CoverageFileLog::class)
                ];
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
        $element = $this->getElement();
        switch ($element) {
            case 'LINE':
                $this->currentLine .= $data;
                break;
            case 'STARTTIME':
                $this->startTimeStamp = $data;
                break;
            case 'ENDTIME':
                $this->endTimeStamp = $data;
                break;
        }
    }
}
