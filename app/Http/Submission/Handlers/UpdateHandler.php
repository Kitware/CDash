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
use CDash\Collection\BuildCollection;
use CDash\Collection\SubscriptionBuilderCollection;
use CDash\Messaging\Notification\NotifyOn;
use CDash\Messaging\Subscription\CommitAuthorSubscriptionBuilder;
use CDash\Messaging\Subscription\UserSubscriptionBuilder;
use CDash\Messaging\Topic\TopicCollection;
use CDash\Messaging\Topic\UpdateErrorTopic;
use CDash\Model\Build;
use CDash\Model\BuildGroup;
use CDash\Model\BuildUpdate;
use CDash\Model\BuildUpdateFile;
use CDash\Model\Project;
use CDash\Model\Repository;
use CDash\Model\SubscriberInterface;
use CDash\Submission\CommitAuthorHandlerInterface;
use Exception;

/** Write the updates in one block
 *  In case of a lot of updates this might take up some memory */
class UpdateHandler extends AbstractXmlHandler implements ActionableBuildInterface, CommitAuthorHandlerInterface
{
    private $StartTimeStamp;
    private $EndTimeStamp;
    private $Update;
    private $UpdateFile;
    protected static ?string $schema_file = '/app/Validators/Schemas/Update.xsd';

    /** Constructor */
    public function __construct(Project $project)
    {
        parent::__construct($project);
    }

    /** Start element */
    public function startElement($parser, $name, $attributes): void
    {
        parent::startElement($parser, $name, $attributes);
        $factory = $this->getModelFactory();
        if ($name == 'UPDATE') {
            $this->Build = new Build();
            $this->Update = $factory->create(BuildUpdate::class);

            if (isset($attributes['GENERATOR'])) {
                $this->Build->Generator = $attributes['GENERATOR'];
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
    public function endElement($parser, $name): void
    {
        if ($this->currentPathMatches('site')) {
            if (!isset($this->Site)) {
                $this->Site = Site::firstOrCreate(['name' => '(unknown)']);
            } else {
                $this->Site->save();
            }
        } elseif ($name == 'UPDATE') {
            $this->Build->SiteId = $this->Site->id;

            $start_time = gmdate(FMT_DATETIME, $this->StartTimeStamp);
            $end_time = gmdate(FMT_DATETIME, $this->EndTimeStamp);
            $submit_time = gmdate(FMT_DATETIME);
            $this->Build->StartTime = $start_time;
            $this->Build->EndTime = $end_time;
            $this->Build->SubmitTime = $submit_time;

            $this->Build->ProjectId = $this->GetProject()->Id;

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
                SubmissionUtils::add_build($this->Build);
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

            if ($this->Update->Command === '') {
                // If the UpdateCommand was not set, then this was a
                // "version only" update.  This means that CTest only told us
                // what version of the code is being built, not what changed
                // since last time.  In this case we need to query the remote
                // repository to figure out what changed.
                try {
                    Repository::compareCommits($this->Update, $this->GetProject());
                } catch (Exception) {
                    // Do nothing.
                }
            }

            // Compute the update statistics
            $this->Build->ComputeUpdateStatistics();
        } elseif ($name == 'UPDATED' || $name == 'CONFLICTING' || $name == 'MODIFIED') {
            $this->Update->AddFile($this->UpdateFile);
            unset($this->UpdateFile);
        }

        parent::endElement($parser, $name);
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
                case 'CHANGEID':
                    $this->Build->SetPullRequest($data);
                    break;
                case 'ENDTIME':
                    $this->EndTimeStamp = $data;
                    break;
                case 'PATH':
                    $this->Update->Path = $data;
                    break;
                case 'PRIORREVISION':
                    $this->Update->PriorRevision = $data;
                    break;
                case 'REVISION':
                    $this->Update->Revision = $data;
                    break;
                case 'SITE':
                    $sitename = !empty($data) ? $data : '(empty)';
                    $this->Site = Site::firstOrCreate(['name' => $sitename], ['name' => $sitename]);
                    break;
                case 'STARTTIME':
                    $this->StartTimeStamp = $data;
                    break;
                case 'UPDATECOMMAND':
                    $this->Update->Command .= $data;
                    break;
                case 'UPDATERETURNSTATUS':
                    $this->Update->Status .= $data;
                    break;
                case 'UPDATETYPE':
                    $this->Update->Type = $data;
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

    public function GetBuildCollection(): BuildCollection
    {
        $collection = new BuildCollection();
        $collection->add($this->Build);
        return $collection;
    }

    public function GetTopicCollectionForSubscriber(SubscriberInterface $subscriber): TopicCollection
    {
        $collection = new TopicCollection();
        $preferences = $subscriber->getNotificationPreferences();
        if ($preferences->get(NotifyOn::UPDATE_ERROR)) {
            $topic = new UpdateErrorTopic();
            $collection->add($topic);
        }
        return $collection;
    }

    public function GetSubscriptionBuilderCollection(): SubscriptionBuilderCollection
    {
        $collection = (new SubscriptionBuilderCollection())
            ->add(new UserSubscriptionBuilder($this))
            ->add(new CommitAuthorSubscriptionBuilder($this));
        return $collection;
    }

    public function GetCommitAuthors()
    {
        return $this->Build->GetCommitAuthors();
    }

    public function GetBuildGroup(): BuildGroup
    {
        $buildGroup = new BuildGroup();
        $buildGroup->SetId($this->Build->GroupId);
        return $buildGroup;
    }
}
