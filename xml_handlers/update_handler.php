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

use CDash\Collection\BuildCollection;

require_once 'xml_handlers/abstract_handler.php';
require_once 'xml_handlers/actionable_build_interface.php';

use CDash\Model\Build;
use CDash\Model\BuildUpdate;
use CDash\Model\BuildUpdateFile;
use CDash\Model\Feed;
use CDash\Model\Project;
use CDash\Model\Repository;
use CDash\Model\Site;

/** Write the updates in one block
 *  In case of a lot of updates this might take up some memory */
class UpdateHandler extends AbstractHandler implements ActionableBuildInterface
{
    private $StartTimeStamp;
    private $EndTimeStamp;
    private $Append;
    private $Update;
    private $UpdateFile;
    private $Feed;

    /** Constructor */
    public function __construct($projectID, $scheduleID)
    {
        parent::__construct($projectID, $scheduleID);
        $factory = $this->getModelFactory();
        $this->Build = $factory->create(Build::class);
        $this->Site = $factory->create(Site::class);
        $this->Append = false;
        $this->Feed = $factory->create(Feed::class);
    }

    /** Start element */
    public function startElement($parser, $name, $attributes)
    {
        parent::startElement($parser, $name, $attributes);
        $factory = $this->getModelFactory();
        if ($name == 'UPDATE') {
            $this->Build = $factory->create(Build::class);
            $this->Update = $factory->create(BuildUpdate::class);

            if (isset($attributes['GENERATOR'])) {
                $this->Build->Generator = $attributes['GENERATOR'];
            }

            if (array_key_exists('APPEND', $attributes)) {
                if (strtolower($attributes['APPEND']) == 'true') {
                    $this->Append = true;
                }
            } else {
                $this->Append = false;
            }

            $this->Update->Append = $this->Append;
        } elseif ($name == 'UPDATED' || $name == 'CONFLICTING' || $name == 'MODIFIED') {
            $this->UpdateFile = $factory->create(BuildUpdateFile::class);
            $this->UpdateFile->Status = $name;
        } elseif ($name == 'UPDATERETURNSTATUS') {
            $this->Update->Status = '';
        }
    }

    /** End element */
    public function endElement($parser, $name)
    {
        parent::endElement($parser, $name);
        if ($name == 'SITE') {
            $this->Site->Insert();
        } elseif ($name == 'UPDATE') {
            $this->Build->SiteId = $this->Site->Id;

            $start_time = gmdate(FMT_DATETIME, $this->StartTimeStamp);
            $end_time = gmdate(FMT_DATETIME, $this->EndTimeStamp);
            $submit_time = gmdate(FMT_DATETIME);
            $this->Build->StartTime = $start_time;
            $this->Build->EndTime = $end_time;
            $this->Build->SubmitTime = $submit_time;

            $this->Build->ProjectId = $this->projectid;

            $this->Build->GetIdFromName($this->SubProjectName);
            // Update.xml doesn't include SubProject information.
            // Check if GetIdFromName returned a child build, and
            // if so, change our buildid to point at the parent instead.
            $parentid = $this->Build->LookupParentBuildId();
            if ($parentid > 0) {
                $this->Build->Id = $parentid;
            }

            $this->Build->RemoveIfDone();

            // If the build doesn't exist we add it
            if ($this->Build->Id == 0) {
                $this->Build->SetSubProject($this->SubProjectName);
                $this->Build->Append = $this->Append;
                $this->Build->InsertErrors = false;
                add_build($this->Build, $this->scheduleid);
            } else {
                // Otherwise make sure that it's up-to-date.
                $this->Build->UpdateBuild($this->Build->Id, -1, -1);
            }

            $GLOBALS['PHP_ERROR_BUILD_ID'] = $this->Build->Id;
            $this->Update->BuildId = $this->Build->Id;
            $this->Update->StartTime = $start_time;
            $this->Update->EndTime = $end_time;

            // Insert the update
            $this->Update->Insert();
            $this->Build->SetBuildUpdate($this->Update);

            if ($this->config->get('CDASH_ENABLE_FEED')) {
                // We need to work the magic here to have a good description
                $this->Feed->InsertUpdate($this->projectid, $this->Build->Id);
            }

            if ($this->Update->Command === '') {
                // If the UpdateCommand was not set, then this was a
                // "version only" update.  This means that CTest only told us
                // what version of the code is being built, not what changed
                // since last time.  In this case we need to query the remote
                // repository to figure out what changed.
                $project = new Project();
                $project->Id = $this->projectid;
                $project->Fill();
                try {
                    Repository::compareCommits($this->Update, $project);
                } catch (\Exception $e) {
                    // Do nothing.
                }
            }

            // Compute the update statistics
            $this->Build->ComputeUpdateStatistics();
        } elseif ($name == 'UPDATED' || $name == 'CONFLICTING' || $name == 'MODIFIED') {
            $this->Update->AddFile($this->UpdateFile);
            unset($this->UpdateFile);
        }
    }

    /** Text */
    public function text($parser, $data)
    {
        $parent = $this->getParent();
        $element = $this->getElement();
        if ($parent == 'UPDATE') {
            switch ($element) {
                case 'BUILDNAME':
                    $this->Build->Name = $data;
                    if (empty($this->Build->Name)) {
                        $this->Build->Name = '(empty)';
                    }
                    break;
                case 'BUILDSTAMP':
                    $this->Build->SetStamp($data);
                    break;
                case 'SITE':
                    $this->Site->Name = $data;
                    if (empty($this->Site->Name)) {
                        $this->Site->Name = '(empty)';
                    }
                    break;
                case 'STARTTIME':
                    $this->StartTimeStamp = $data;
                    break;
                case 'ENDTIME':
                    $this->EndTimeStamp = $data;
                    break;
                case 'UPDATECOMMAND':
                    $this->Update->Command .= $data;
                    break;
                case 'UPDATETYPE':
                    $this->Update->Type = $data;
                    break;
                case 'REVISION':
                    $this->Update->Revision = $data;
                    break;
                case 'PRIORREVISION':
                    $this->Update->PriorRevision = $data;
                    break;
                case 'PATH':
                    $this->Update->Path = $data;
                    break;
                case 'UPDATERETURNSTATUS':
                    $this->Update->Status .= $data;
                    break;
            }
        } elseif ($parent != 'REVISIONS' && $element == 'FULLNAME') {
            $this->UpdateFile->Filename = $data;
        } elseif ($parent != 'REVISIONS' && $element == 'CHECKINDATE') {
            $this->UpdateFile->CheckinDate = $data;
        } elseif ($parent != 'REVISIONS' && $element == 'AUTHOR') {
            $this->UpdateFile->Author .= $data;
        } elseif ($parent != 'REVISIONS' && $element == 'EMAIL') {
            $this->UpdateFile->Email .= $data;
        } elseif ($parent != 'REVISIONS' && $element == 'COMMITTER') {
            $this->UpdateFile->Committer .= $data;
        } elseif ($parent != 'REVISIONS' && $element == 'COMMITTEREMAIL') {
            $this->UpdateFile->CommitterEmail .= $data;
        } elseif ($parent != 'REVISIONS' && $element == 'LOG') {
            $this->UpdateFile->Log .= $data;
        } elseif ($parent != 'REVISIONS' && $element == 'REVISION') {
            if ($data == 'Unknown') {
                $data = -1;
            }
            $this->UpdateFile->Revision = $data;
        } elseif ($parent != 'REVISIONS' && $element == 'PRIORREVISION') {
            if ($data == 'Unknown') {
                $data = -1;
            }
            $this->UpdateFile->PriorRevision = $data;
        }
    }

    /**
     * @return Build[]
     * @deprecated
     */
    public function getActionableBuilds()
    {
        return $this->getBuilds();
    }

    /**
     * @return BuildCollection
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public function GetBuildCollection()
    {
        $factory = $this->getModelFactory();
        /** @var BuildCollection $collection */
        $collection = $factory->create(BuildCollection::class);
        $collection->add($this->Build);
        return $collection;
    }

    public function getProjectId()
    {
        // TODO: Implement getProjectId() method.
    }
}
