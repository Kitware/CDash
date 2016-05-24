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

        if ($stmt->execute(array($this->BuildId))) {
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
        $success = $stmt->execute(array($this->BuildId));
        if (!$stmt->execute()) {
            add_last_sql_error('DynamicAnalysisSummary Remove',
                    0, $this->BuildId);
            return false;
        }
        return true;
    }

    // Insert the DynamicAnalysisSummary
    public function Insert()
    {
        if ($this->BuildId < 1) {
            return false;
        }

        // We only support one such summary per build, so delete any
        // that exists before attempting to insert a new one.
        if ($this->Exists()) {
            $this->Remove();
        }

        $pdo = get_link_identifier()->getPdo();
        $stmt = $pdo->prepare('
                INSERT INTO dynamicanalysissummary
                (buildid, checker, numdefects)
                VALUES (:buildid, :checker, :numdefects)');
        $stmt->bindParam(':buildid', $this->BuildId);
        $stmt->bindParam(':checker', $this->Checker);
        $stmt->bindParam(':numdefects', $this->NumDefects);
        if (!$stmt->execute()) {
            add_last_sql_error('DynamicAnalysisSummary Insert',
                    0, $this->BuildId);
            return false;
        }
        return true;
    }
}
