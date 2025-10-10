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
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** PendingSubmission class */
class PendingSubmissions
{
    public $Build;
    public $NumFiles;
    public $Recheck;
    private $Filled;
    private $PDO;

    public function __construct()
    {
        $this->Build = null;
        $this->NumFiles = 0;
        $this->Recheck = 0;
        $this->Filled = false;
        $this->PDO = Database::getInstance()->getPdo();
    }

    /** Return true if a record already exists for this build. */
    public function Exists(): bool
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
    public function Save(): bool
    {
        if (!$this->Build) {
            Log::error('Build not set', [
                'function' => 'PendingSubmission::Save',
            ]);
            return false;
        }

        DB::beginTransaction();
        if ($this->Exists()) {
            $stmt = $this->PDO->prepare(
                'UPDATE pending_submissions
                SET numfiles = :numfiles,
                    recheck  = :recheck
                WHERE buildid = :buildid');
        } else {
            $stmt = $this->PDO->prepare(
                'INSERT INTO pending_submissions
                (buildid, numfiles, recheck)
                VALUES
                (:buildid, :numfiles, :recheck)');
        }
        $stmt->bindParam(':buildid', $this->Build->Id);
        $stmt->bindParam(':numfiles', $this->NumFiles);
        $stmt->bindParam(':recheck', $this->Recheck);
        if (!pdo_execute($stmt)) {
            DB::rollBack();
            return false;
        }
        DB::commit();
        return true;
    }

    /** Delete this record from the database. */
    public function Delete(): bool
    {
        if (!$this->Build) {
            Log::error('Build not set', [
                'function' => 'PendingSubmission::Delete',
            ]);
            return false;
        }
        if (!$this->Exists()) {
            Log::error('Record does not exist', [
                'function' => 'PendingSubmission::Delete',
            ]);
            return false;
        }

        $stmt = $this->PDO->prepare(
            'DELETE FROM pending_submissions WHERE buildid = ?');
        return pdo_execute($stmt, [$this->Build->Id]);
    }

    public function Fill()
    {
        if ($this->Filled) {
            return true;
        }
        if (!$this->Build) {
            Log::error('Build not set', [
                'function' => 'PendingSubmission::Fill',
            ]);
            return false;
        }

        $stmt = $this->PDO->prepare(
            'SELECT * FROM pending_submissions WHERE buildid = ?');
        if (!pdo_execute($stmt, [$this->Build->Id])) {
            return false;
        }

        $row = $stmt->fetch();
        if (is_array($row)) {
            $this->NumFiles = $row['numfiles'];
            $this->Recheck = $row['recheck'];
        }
        $this->Filled = true;
    }

    /** Get number of pending submissions for a given build. */
    public function GetNumFiles()
    {
        $this->Filled = false;
        $this->Fill();
        return $this->NumFiles;
    }

    /** Get whether or not this build has been scheduled for rechek. */
    public function GetRecheck()
    {
        $this->Filled = false;
        $this->Fill();
        return $this->Recheck;
    }

    // Atomically update an existing pending_submissions record in the database.
    private function AtomicUpdate($caller, $clause): bool
    {
        if (!$this->Build) {
            Log::error('Build not set', [
                'function' => "PendingSubmission::$caller",
            ]);
            return false;
        }

        DB::beginTransaction();
        if (!$this->Exists()) {
            DB::commit();
            return false;
        }

        $stmt = $this->PDO->prepare(
            "UPDATE pending_submissions
            SET $clause
            WHERE buildid = ?");
        try {
            if ($stmt->execute([$this->Build->Id])) {
                DB::commit();
            } else {
                // The UPDATE statement didn't execute cleanly.
                $error_info = $stmt->errorInfo();
                $error = $error_info[2];
                throw new Exception($error);
            }
        } catch (Exception $e) {
            DB::rollBack();
            // Ignore any 'Numeric value out of range' SQL errors.
            if ($this->GetNumFiles() > 0) {
                // Otherwise log the error and return false.
                report($e);
                return false;
            }
        }
        return true;
    }

    public function Increment(): bool
    {
        return $this->AtomicUpdate('Increment', 'numfiles = numfiles + 1');
    }

    public function Decrement(): bool
    {
        return $this->AtomicUpdate('Decrement', 'numfiles = numfiles - 1');
    }

    public function MarkForRecheck(): bool
    {
        return $this->AtomicUpdate('MarkForRecheck', 'recheck = 1');
    }

    public static function GetModelForBuildId($buildid): PendingSubmissions
    {
        $build = new Build();
        $build->Id = $buildid;
        $pendingSubmissions = new PendingSubmissions();
        $pendingSubmissions->Build = $build;
        return $pendingSubmissions;
    }

    public static function RecheckForBuildId($buildid): bool
    {
        $pendingSubmissions = self::GetModelForBuildId($buildid);
        return $pendingSubmissions->MarkForRecheck();
    }

    public static function IncrementForBuildId($buildid): bool
    {
        $pendingSubmissions = self::GetModelForBuildId($buildid);
        return $pendingSubmissions->Increment();
    }
}
