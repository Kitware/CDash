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

namespace CDash\Model;

use App\Models\Build;
use CDash\Collection\BuildEmailCollection;
use CDash\Messaging\Notification\Email\EmailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Class BuildEmail
 */
class BuildEmail
{
    protected $BuildId;
    protected $Category;
    protected $Email;
    protected bool $Sent = false;
    protected $UserId;

    /**
     * Saves a EmailMessage's BuildEmailCollection to the database. The BuildEmailCollection
     * is the collection of all emails sent for a given CTest submission, e.g. Build, Test,
     * etc.
     */
    public static function SaveNotification(EmailMessage $message): void
    {
        $collection = $message->getBuildEmailCollection();
        /* @var BuildEmail $email */
        foreach ($collection as $emails) {
            foreach ($emails as $email) {
                $email->Save();
            }
        }
    }

    /**
     * Returns a collection of emails sent given a build and category.
     */
    public static function GetEmailSentForBuild($buildId): BuildEmailCollection
    {
        $collection = new BuildEmailCollection();

        $build = Build::find((int) $buildId);
        if ($build !== null) {
            /* @var \App\Models\BuildEmail $email */
            foreach ($build->emails as $sent_email) {
                $email = new BuildEmail();
                $email
                    ->SetBuildId($buildId)
                    ->SetCategory($sent_email->category)
                    ->SetEmail($sent_email->user->email)
                    ->SetUserId($sent_email->user->id)
                    ->SetSent();
                $collection->add($email);
            }
        }

        return $collection;
    }

    /**
     * Saves a record of the current BuildEmail having been sent.
     */
    protected function Save(): bool
    {
        $missing = [];
        foreach (['BuildId', 'UserId', 'Category'] as $field) {
            if (!$this->$field) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            $missing_str = implode(', ', $missing);
            $msg = "Missing: {$missing_str}; cannot save BuildEmail for {$this->Email}.";
            Log::info($msg);
            return false;
        }

        Build::findOrFail((int) $this->BuildId)->emails()->create([
            'userid' => (int) $this->UserId,
            'category' => (int) $this->Category,
            'time' => Carbon::now(),
        ]);

        return true;
    }

    public function GetEmail()
    {
        return $this->Email;
    }

    /**
     * @return int
     */
    public function GetCategory()
    {
        return $this->Category;
    }

    public function SetEmail($email): static
    {
        $this->Email = $email;
        return $this;
    }

    /**
     * @return $this
     */
    protected function SetSent(): static
    {
        $this->Sent = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function SetCategory($category): static
    {
        $this->Category = $category;
        return $this;
    }

    /**
     * @return $this
     */
    public function SetUserId($userId): static
    {
        $this->UserId = $userId;
        return $this;
    }

    /**
     * @return $this
     */
    public function SetBuildId($buildId): static
    {
        $this->BuildId = $buildId;
        return $this;
    }
}
