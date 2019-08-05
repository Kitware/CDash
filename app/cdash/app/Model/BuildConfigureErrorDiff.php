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

/** BuildConfigureErrorDiff class */
class BuildConfigureErrorDiff
{
    public $Type;
    public $Difference;
    public $BuildId;

    /** Return if exists */
    public function Exists()
    {
        $query = pdo_query('SELECT count(*) AS c FROM configureerrordiff WHERE buildid=' . qnum($this->BuildId));
        $query_array = pdo_fetch_array($query);
        if ($query_array['c'] > 0) {
            return true;
        }
        return false;
    }

    /** Save in the database */
    public function Save()
    {
        if (!$this->BuildId) {
            echo 'BuildConfigureErrorDiff::Save(): BuildId not set';
            return false;
        }

        if ($this->Exists()) {
            // Update
            $query = 'UPDATE configureerrordiff SET';
            $query .= ' type=' . qnum($this->Type);
            $query .= ',difference=' . qnum($this->Difference);
            $query .= ' WHERE buildid=' . qnum($this->BuildId);
            if (!pdo_query($query)) {
                add_last_sql_error('BuildConfigureErrorDiff:Update', 0, $this->BuildId);
                return false;
            }
        } else {
            // insert
            $db = Database::getInstance();
            $sql = 'INSERT INTO configureerrordiff (buildid, type, difference) VALUES (:id, :type, :diff)';
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':id', $this->BuildId);
            $stmt->bindValue(':type', $this->Type);
            $stmt->bindValue(':diff', $this->Difference);

            if (!$db->execute($stmt)) {
                add_last_sql_error('BuildConfigureErrorDiff:Create', 0, $this->BuildId);
                return false;
            }
        }
        return true;
    }
}
