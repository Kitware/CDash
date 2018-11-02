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

namespace CDash\Lib\Parser\CTest;

use CDash\Lib\Parser\AbstractXmlParser;
use CDash\Model\Build;
use CDash\Model\BuildUpdate;
use CDash\Model\BuildUpdateFile;
use CDash\Model\Feed;
use CDash\Model\Site;

/**
 * Class UpdateParser
 * @package CDash\Lib\Parser\CTest
 */
class UpdateParser extends AbstractXmlParser
{
    private $append;
    private $update;
    private $updateFile;
    private $feed;

    /**
     * UpdateParser constructor.
     * @param $projectId
     */
    public function __construct($projectId)
    {
        parent::__construct($projectId);
        $this->append = false;
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
        if ($name == 'UPDATE') {
            $this->build = $this->getInstance(Build::class);
            $this->update = $this->getInstance(BuildUpdate::class);

            if (isset($attributes['GENERATOR'])) {
                $this->build->Generator = $attributes['GENERATOR'];
            }

            if (array_key_exists('APPEND', $attributes)) {
                if (strtolower($attributes['APPEND']) == 'true') {
                    $this->append = true;
                }
            } else {
                $this->append = false;
            }

            $this->update->Append = $this->append;
        } elseif ($name == 'UPDATED' || $name == 'CONFLICTING' || $name == 'MODIFIED') {
            $this->updateFile = $this->getInstance(BuildUpdateFile::class);
            $this->updateFile->Status = $name;
        } elseif ($name == 'UPDATERETURNSTATUS') {
            $this->update->Status = '';
        } elseif ($name == 'SITE') {
            $this->site = $this->getInstance(Site::class);
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
        if ($name == 'SITE') {
            $this->site->Insert();
        } elseif ($name == 'UPDATE') {
            $this->build->SiteId = $this->site->Id;

            $start_time = gmdate(FMT_DATETIME, $this->startTimeStamp);
            $end_time = gmdate(FMT_DATETIME, $this->endTimeStamp);
            $submit_time = gmdate(FMT_DATETIME);
            $this->build->StartTime = $start_time;
            $this->build->EndTime = $end_time;
            $this->build->SubmitTime = $submit_time;

            $this->build->ProjectId = $this->projectId;

            $this->build->GetIdFromName($this->subProjectName);
            // Update.xml doesn't include SubProject information.
            // Check if GetIdFromName returned a child build, and
            // if so, change our buildid to point at the parent instead.
            $parentid = $this->build->LookupParentBuildId();
            if ($parentid > 0) {
                $this->build->Id = $parentid;
            }

            $this->build->RemoveIfDone();

            // If the build doesn't exist we add it
            if ($this->build->Id == 0) {
                $this->build->SetSubProject($this->subProjectName);
                $this->build->Append = $this->append;
                $this->build->InsertErrors = false;
                add_build($this->build);
            } else {
                // Otherwise make sure that it's up-to-date.
                $this->build->UpdateBuild($this->build->Id, -1, -1);
            }

            $GLOBALS['PHP_ERROR_BUILD_ID'] = $this->build->Id;
            $this->update->BuildId = $this->build->Id;
            $this->update->StartTime = $start_time;
            $this->update->EndTime = $end_time;

            // Insert the update
            $this->update->Insert();

            if ($this->getConfigValue('CDASH_ENABLE_FEED')) {
                $this->feed = $this->getInstance(Feed::class);
                // We need to work the magic here to have a good description
                $this->feed->InsertUpdate($this->projectId, $this->build->Id);
            }

            if ($this->update->Command === '') {
                // If the UpdateCommand was not set, then this was a
                // "version only" update.  This means that CTest only told us
                // what version of the code is being built, not what changed
                // since last time.  In this case we need to query the remote
                // repository to figure out what changed.
                perform_version_only_diff($this->update, $this->projectId);
            }

            // Compute the update statistics
            $this->build->ComputeUpdateStatistics();
        } elseif ($name == 'UPDATED' || $name == 'CONFLICTING' || $name == 'MODIFIED') {
            $this->update->AddFile($this->updateFile);
            unset($this->updateFile);
        }
    }

    /**
     * @param $parser
     * @param $data
     * @return mixed|void
     */
    public function text($parser, $data)
    {
        $parent = $this->getParent();
        $element = $this->getElement();
        if ($parent == 'UPDATE') {
            switch ($element) {
                case 'BUILDNAME':
                    $this->build->Name = $data;
                    if (empty($this->build->Name)) {
                        $this->build->Name = '(empty)';
                    }
                    break;
                case 'BUILDSTAMP':
                    $this->build->SetStamp($data);
                    break;
                case 'SITE':
                    $this->site->Name = $data;
                    if (empty($this->site->Name)) {
                        $this->site->Name = '(empty)';
                    }
                    break;
                case 'STARTTIME':
                    $this->startTimeStamp = $data;
                    break;
                case 'ENDTIME':
                    $this->endTimeStamp = $data;
                    break;
                case 'UPDATECOMMAND':
                    $this->update->Command .= $data;
                    break;
                case 'UPDATETYPE':
                    $this->update->Type = $data;
                    break;
                case 'REVISION':
                    $this->update->Revision = $data;
                    break;
                case 'PRIORREVISION':
                    $this->update->PriorRevision = $data;
                    break;
                case 'PATH':
                    $this->update->Path = $data;
                    break;
                case 'UPDATERETURNSTATUS':
                    $this->update->Status .= $data;
                    break;
            }
        } elseif ($parent != 'REVISIONS' && $element == 'FULLNAME') {
            $this->updateFile->Filename = $data;
        } elseif ($parent != 'REVISIONS' && $element == 'CHECKINDATE') {
            $this->updateFile->CheckinDate = $data;
        } elseif ($parent != 'REVISIONS' && $element == 'AUTHOR') {
            $this->updateFile->Author .= $data;
        } elseif ($parent != 'REVISIONS' && $element == 'EMAIL') {
            $this->updateFile->Email .= $data;
        } elseif ($parent != 'REVISIONS' && $element == 'COMMITTER') {
            $this->updateFile->Committer .= $data;
        } elseif ($parent != 'REVISIONS' && $element == 'COMMITTEREMAIL') {
            $this->updateFile->CommitterEmail .= $data;
        } elseif ($parent != 'REVISIONS' && $element == 'LOG') {
            $this->updateFile->Log .= $data;
        } elseif ($parent != 'REVISIONS' && $element == 'REVISION') {
            if ($data == 'Unknown') {
                $data = -1;
            }
            $this->updateFile->Revision = $data;
        } elseif ($parent != 'REVISIONS' && $element == 'PRIORREVISION') {
            if ($data == 'Unknown') {
                $data = -1;
            }
            $this->updateFile->PriorRevision = $data;
        }
    }

    public function getActionableBuilds()
    {
        return $this->getBuilds();
    }
}
