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

use CDash\Collection\BuildEmailCollection;
use CDash\Database;
use CDash\Messaging\Notification\Email\EmailMessage;
use Illuminate\Support\Facades\Log;
use PDO;

/**
 * Class BuildEmail
 */
class BuildEmail
{
    protected $BuildId;
    protected $Category;
    protected $Email;
    protected $Sent;
    protected $Time;
    protected $UserId;
    private $RequiredFields = ['BuildId', 'UserId', 'Category'];

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
        $sql = '
            SELECT
                buildemail.*,
                u.email
            FROM buildemail
            JOIN users u ON u.id=buildemail.userid
            WHERE buildemail.buildid=:b';

        $db = Database::getInstance();
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':b', $buildId);

        if ($db->execute($stmt)) {
            foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $row) {
                $email = new BuildEmail();
                $email
                    ->SetBuildId($buildId)
                    ->SetCategory($row->category)
                    ->SetEmail($row->email)
                    ->SetUserId($row->userid)
                    ->SetTime($row->time)
                    ->SetSent(true);
                $collection->add($email);
            }
        }
        return $collection;
    }

    /**
     * BuildEmail constructor.
     */
    public function __construct()
    {
        $this->Sent = false;
    }

    /**
     * Saves a record of the current BuildEmail having been sent.
     */
    public function Save(): bool
    {
        $missing = [];
        foreach ($this->RequiredFields as $field) {
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

        $this->SetTime();

        $sql = 'INSERT INTO buildemail (userid, buildid, category, time) VALUES (:u, :b, :c, :t)';
        $db = Database::getInstance();
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':u', $this->UserId);
        $stmt->bindParam(':b', $this->BuildId);
        $stmt->bindParam(':c', $this->Category);
        $stmt->bindParam(':t', $this->Time);
        return $db->execute($stmt);
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
    public function SetSent($exists): static
    {
        $this->Sent = $exists;
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

    /**
     * @return $this
     */
    public function SetTime($time = null): static
    {
        if (is_null($time)) {
            $time = date('Y-m-d H:i:s');
        }
        $this->Time = $time;
        return $this;
    }
}
