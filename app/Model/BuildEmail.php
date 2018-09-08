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
use CDash\Log;
use CDash\Messaging\Notification\Email\EmailMessage;

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
     * @param $userId
     * @param $buildId
     * @param $category
     * @return BuildEmail
     */
    public static function GetBuildEmailForUser($userId, $buildId, $category)
    {
        $buildEmail = new BuildEmail();

        $sql = 'SELECT time FROM buildemail WHERE userid=:u AND buildid=:b AND category=:c LIMIT 1';
        $db = Database::getInstance();
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':u', $userId);
        $stmt->bindParam(':b', $buildId);
        $stmt->bindParam(':c', $category);
        if ($db->execute($stmt)) {
            $count = $stmt->rowCount();
            if ($count) {
                $time = $stmt->fetchColumn(0);
                $buildEmail
                    ->SetSent(true)
                    ->SetUserId($userId)
                    ->SetBuildId($buildId)
                    ->SetTime($time);
            }
        }
        return $buildEmail;
    }

    /**
     * @param $buildId
     * @param $category
     * @return BuildEmailCollection
     */
    public static function GetBuildEmailSent($buildId, $category)
    {
        $collection = new BuildEmailCollection();
        $sql = '
            SELECT
                buildemail.*,
                user.email,
            FROM buildemail 
            JOIN user ON user.id=buildemail.userid
            WHERE buildemail.buildid=:b 
            AND buildemail.category=:c';
        $db = Database::getInstance();
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':b', $buildId);
        $stmt->bindParam(':c', $category);

        if ($db->execute($stmt)) {
            foreach ($stmt->fetchAll(\PDO::FETCH_OBJ) as $row) {
                $email = new BuildEmail();
                $email
                    ->SetBuildId($buildId)
                    ->SetCategory($category)
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
            $msg = "Cannot save " . __CLASS__ .
                ": Missing required fields [" . implode(', ', $missing) . "]";
            $e = new \Exception($msg);
            Log::getInstance()->error($e);
            return false;
        }

        $sql = 'INSERT INTO buildemail (`userid`, `buildid`, `category`) VALUE (:u, :b, :c)';
        $db = Database::getInstance();
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':u', $this->UserId);
        $stmt->bindParam(':b', $this->BuildId);
        $stmt->bindParam(':c', $this->Category);
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
     * @return bool
     */
    public function WasSent()
    {
        return $this->Sent;
    }

    /**
     * @return int
     */
    public function GetUserId()
    {
        return $this->UserId;
    }

    /**
     * @return int
     */
    public function GetBuildId()
    {
        return $this->BuildId;
    }

    /**
     * @return int
     */
    public function GetCategory()
    {
        return $this->Category;
    }

    /**
     * @return string
     */
    public function GetTime()
    {
        return $this->Time;
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
    public function SetTime($time)
    {
        $this->Time = $time;
        return $this;
    }
}
