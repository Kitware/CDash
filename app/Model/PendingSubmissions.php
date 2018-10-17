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
namespace CDash\Model;

use CDash\Database;
use CDash\Model\Build;

/** PendingSubmission class */
class PendingSubmissions
{
    public $Build;
    public $NumFiles;
    private $PDO;

    public function __construct()
    {
        $this->Build = null;
        $this->NumFiles = 0;
        $this->PDO = Database::getInstance()->getPdo();
    }

    /** Return true if a record already exists for this build. */
    public function Exists()
    {
        if (!$this->Build) {
            return false;
        }
        $stmt = $this->PDO->prepare(
            'SELECT COUNT(*) FROM pending_submissions
            WHERE buildid = :buildid');
        $params = [':buildid' => $this->Build->Id];
        pdo_execute($stmt, $params);
        if ($stmt->fetchColumn() > 0) {
            return true;
        }
        return false;
    }

    /** Insert a new record in the database or update an existing one. */
    public function Save()
    {
        if (!$this->Build) {
            add_log('Build not set', 'PendingSubmission::Save', LOG_ERR);
            return false;
        }

        $this->PDO->beginTransaction();
        if ($this->Exists()) {
            $stmt = $this->PDO->prepare(
                'UPDATE pending_submissions
                SET numfiles = :numfiles
                WHERE buildid = :buildid');
        } else {
            $stmt = $this->PDO->prepare(
                'INSERT INTO pending_submissions
                (buildid, numfiles)
                VALUES
                (:buildid, :numfiles)');
        }
        $stmt->bindParam(':buildid', $this->Build->Id);
        $stmt->bindParam(':numfiles', $this->NumFiles);
        if (!pdo_execute($stmt)) {
            $this->PDO->rollBack();
            return false;
        }
        $this->PDO->commit();
        return true;
    }

    /** Delete this record from the database. */
    public function Delete()
    {
        if (!$this->Build) {
            add_log('Build not set', 'PendingSubmission::Delete', LOG_ERR);
            return false;
        }
        if (!$this->Exists()) {
            add_log('Record does not exist', 'PendingSubmission::Delete',
                    LOG_ERR);
            return false;
        }

        $stmt = $this->PDO->prepare(
            'DELETE FROM pending_submissions WHERE buildid = ?');
        return pdo_execute($stmt, [$this->Build->Id]);
    }

    /** Get number of pending submissions for a given build. */
    public function GetNumFiles()
    {
        if (!$this->Build) {
            add_log('Build not set', 'PendingSubmission::GetNumFiles', LOG_ERR);
            return false;
        }

        $stmt = $this->PDO->prepare(
            'SELECT numfiles FROM pending_submissions WHERE buildid = ?');
        if (!pdo_execute($stmt, [$this->Build->Id])) {
            return false;
        }

        $row = $stmt->fetch();
        $this->NumFiles = $row['numfiles'];
        return $this->NumFiles;
    }

    // Increase or decrease the number of pending submissions for a build.
    private function IncrementOrDecrement($caller)
    {
        if (!$this->Build) {
            add_log('Build not set', "PendingSubmission::$caller", LOG_ERR);
            return false;
        }

        $this->PDO->beginTransaction();
        if (!$this->Exists()) {
            $this->PDO->commit();
            return false;
        }

        if ($caller === 'Increment') {
            $operator = '+';
        } else {
            $operator = '-';
        }

        $stmt = $this->PDO->prepare(
            "UPDATE pending_submissions
            SET numfiles = numfiles $operator 1
            WHERE buildid = ?");
        pdo_execute($stmt, [$this->Build->Id]);
        $this->PDO->commit();
    }
    public function Increment()
    {
        $this->IncrementOrDecrement('Increment');
    }
    public function Decrement()
    {
        $this->IncrementOrDecrement('Decrement');
    }
    public static function IncrementForBuildId($buildid)
    {
        $build = new Build();
        $build->Id = $buildid;
        $pendingSubmissions = new PendingSubmissions();
        $pendingSubmissions->Build = $build;
        $pendingSubmissions->Increment();
    }
}
