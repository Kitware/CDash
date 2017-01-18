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

class DynamicAnalysisSummary
{
    public $BuildId;
    public $Checker;
    private $NumDefects;
    private $PDO;

    public function __construct()
    {
        $this->BuildId = 0;
        $this->Checker = '';
        $this->NumDefects = 0;
        $this->PDO = get_link_identifier()->getPdo();
    }

    /** Add defects to the summary */
    public function AddDefects($defects)
    {
        $this->NumDefects += $defects;
    }

    /** Check if a summary already exists for this build. */
    public function Exists()
    {
        if ($this->BuildId < 1) {
            return false;
        }
        $stmt = $this->PDO->prepare("
                SELECT COUNT(*) AS c FROM dynamicanalysissummary
                WHERE buildid = ?");
        if (pdo_execute($stmt, [$this->BuildId])) {
            $row = $stmt->fetch();
            if ($row['c'] > 0) {
                return true;
            }
        }
        return false;
    }

    /** Remove the dynamic analysis summary for this build. */
    public function Remove()
    {
        if ($this->BuildId < 1) {
            return false;
        }
        if (!$this->Exists()) {
            return false;
        }

        $stmt = $this->PDO->prepare('
            DELETE FROM dynamicanalysissummary WHERE buildid = ?');
        return pdo_execute($stmt, [$this->BuildId]);
    }

    // Insert the DynamicAnalysisSummary
    public function Insert($append=false)
    {
        if ($this->BuildId < 1) {
            return false;
        }

        $this->PDO->beginTransaction();

        $stmt = $this->PDO->prepare('
                INSERT INTO dynamicanalysissummary
                (buildid, checker, numdefects)
                VALUES (:buildid, :checker, :numdefects)');
        $error_name = 'DynamicAnalysisSummary Insert';

        if ($this->Exists()) {
            if ($append) {
                // Load the existing results for this build.
                $stmt = $this->PDO->prepare("
                    SELECT checker, numdefects FROM dynamicanalysissummary
                    WHERE buildid = ? FOR UPDATE");
                pdo_execute($stmt, [$this->BuildId]);
                $row = $stmt->fetch();
                if (!$row) {
                    $this->PDO->rollBack();
                    return false;
                }
                $this->Checker = $row['checker'];
                $this->NumDefects += $row['numdefects'];

                // Prepare an UPDATE statement (rather than our default INSERT).
                $stmt = $this->PDO->prepare('
                        UPDATE dynamicanalysissummary
                        SET checker=:checker, numdefects=:numdefects
                        WHERE buildid=:buildid');
                $error_name = 'DynamicAnalysisSummary Update';
            } else {
                // We only support one such summary per build, so if we're
                // not append we delete any row that exists for this build
                // before attempting to insert a new one.
                $this->Remove();
            }
        }

        $stmt->bindParam(':buildid', $this->BuildId);
        $stmt->bindParam(':checker', $this->Checker);
        $stmt->bindParam(':numdefects', $this->NumDefects);
        if (!pdo_execute($stmt)) {
            $this->PDO->rollBack();
            return false;
        }
        $this->PDO->commit();
        return true;
    }
}
