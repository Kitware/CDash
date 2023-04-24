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
use CDash\Messaging\Notification\NotificationInterface;
use Illuminate\Support\Facades\Log;

/**
 * Class BuildEmail
 * @package CDash\Model
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
     *
     * @param EmailMessage $notification
     */
    public static function SaveNotification(EmailMessage $message)
    {
        $collection = $message->getBuildEmailCollection();
        /** @var BuildEmail $email */
        foreach ($collection as $emails) {
            foreach ($emails as $email) {
                $email->Save();
            }
        }
    }

    /**
     * Logs the status (sent, or not sent) of a notification. Given a testing configuration
     * this method will log the email in a manner expected by some of the older CDash tests.
     *
     * @param NotificationInterface $notification
     * @param $sent
     */
    public static function Log(NotificationInterface $notification, $sent)
    {
        if (config('app.debug')) {
            Log::debug($notification->getRecipient());
            Log::debug($notification->getSubject());
            Log::debug($notification->getBody());
        } else {
            $status = $sent ? 'SENT' : 'NOT SENT';
            $class_name = get_class($notification);
            $slash_pos = strrpos($class_name, '\\');
            $pos = $slash_pos ? $slash_pos + 1 : 0;
            $notification_type = substr($class_name, $pos);
            $title = $notification->getSubject();
            $recipient = $notification->getRecipient();
            $message = "[{$status}] {$notification_type} titled, '{$title}' to {$recipient}";
            Log::info($message);
        }
    }

    /**
     * Returns a collection of emails sent given a build and category.
     *
     * @param $buildId
     * @param $category
     * @return BuildEmailCollection
     */
    public static function GetEmailSentForBuild($buildId)
    {
        $collection = new BuildEmailCollection();
        $userTable = qid('user');
        $sql = "
            SELECT
                buildemail.*,
                u.email
            FROM buildemail
            JOIN $userTable u ON u.id=buildemail.userid
            WHERE buildemail.buildid=:b";

        $db = Database::getInstance();
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':b', $buildId);

        if ($db->execute($stmt)) {
            foreach ($stmt->fetchAll(\PDO::FETCH_OBJ) as $row) {
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
     *
     * @return bool
     */
    public function Save()
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

    /**
     * @return mixed
     */
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

    /**
     * @param $email
     * @return $this
     */
    public function SetEmail($email)
    {
        $this->Email = $email;
        return $this;
    }

    /**
     * @param $exists
     * @return $this
     */
    public function SetSent($exists)
    {
        $this->Sent = $exists;
        return $this;
    }

    /**
     * @param $category
     * @return $this
     */
    public function SetCategory($category)
    {
        $this->Category = $category;
        return $this;
    }

    /**
     * @param $userId
     * @return $this
     */
    public function SetUserId($userId)
    {
        $this->UserId = $userId;
        return $this;
    }

    /**
     * @param $buildId
     * @return $this
     */
    public function SetBuildId($buildId)
    {
        $this->BuildId = $buildId;
        return $this;
    }

    /**
     * @param $time
     * @return $this
     */
    public function SetTime($time = null)
    {
        if (is_null($time)) {
            $time = date('Y-m-d H:i:s');
        }
        $this->Time = $time;
        return $this;
    }
}
