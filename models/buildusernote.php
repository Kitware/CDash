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

// It is assumed that appropriate headers should be included before including this file
class BuildUserNote
{
    public $UserId;
    public $Note;
    public $TimeStamp;
    public $Status;
    public $BuildId;

    // Insert in the database
    public function Insert()
    {
        if (!isset($this->BuildId) || $this->BuildId < 1) {
            add_log('BuildId is not set', 'BuildUserNote::Insert()', LOG_ERR);
            return false;
        }

        if (!isset($this->UserId) || $this->UserId < 1) {
            add_log('UserId is not set', 'BuildUserNote::Insert()', LOG_ERR,
                    0, $this->BuildId);
            return false;
        }

        if (!isset($this->Note)) {
            add_log('Note is not set', 'BuildUserNote::Insert()', LOG_ERR,
                    0, $this->BuildId);
            return false;
        }

        if (!isset($this->TimeStamp)) {
            add_log('TimeStamp is not set', 'BuildUserNote::Insert()', LOG_ERR,
                    0, $this->BuildId);
            return false;
        }

        if (!isset($this->Status)) {
            add_log('Status is not set', 'BuildUserNote::Insert()', LOG_ERR,
                    0, $this->BuildId);
            return false;
        }

        $pdo = get_link_identifier()->getPdo();
        $stmt = $pdo->prepare(
                'INSERT INTO buildnote
                (buildid, userid, note, timestamp, status)
                VALUES
                (:buildid, :userid, :TextNote, :now, :Status)');
        $stmt->bindParam(':buildid', $this->BuildId);
        $stmt->bindParam(':userid', $this->UserId);
        $stmt->bindParam(':TextNote', $this->Note);
        $stmt->bindParam(':now', $this->TimeStamp);
        $stmt->bindParam(':Status', $this->Status);
        if (!pdo_execute($stmt)) {
            return false;
        }

        return true;
    }

    // Get JSON representation of this object.
    public function marshal()
    {
        require_once 'models/user.php';
        $pdo = get_link_identifier()->getPdo();
        $marshaledNote = array();

        $user = new User();
        $user->Id = $this->UserId;
        $marshaledNote['user'] = $user->GetName();

        $timestamp = strtotime($this->TimeStamp . ' UTC');
        $marshaledNote['date'] = date('H:i:s T', $timestamp);

        $status = '';
        switch ($this->Status) {
            case 0:
                $status = '[note]';
                break;
            case 1:
                $status = '[fix in progress]';
                break;
            case 2:
                $status = '[fixed]';
                break;
        }
        $marshaledNote['status'] = $status;

        $marshaledNote['text'] = $this->Note;
        return $marshaledNote;
    }

    // Get all the user notes for a given build.
    public static function getNotesForBuild($buildid)
    {
        if (!$buildid) {
            return;
        }

        $pdo = get_link_identifier()->getPdo();
        $stmt = $pdo->prepare(
            'SELECT * FROM buildnote WHERE buildid=? ORDER BY timestamp ASC');
        pdo_execute($stmt, [$buildid]);

        $notes = array();
        while ($row = $stmt->fetch()) {
            $note = new BuildUserNote();
            $note->BuildId = $buildid;
            $note->UserId = $row['userid'];
            $note->Note = $row['note'];
            $note->TimeStamp = $row['timestamp'];
            $note->Status = $row['status'];
            $notes[] = $note;
        }
        return $notes;
    }
}
