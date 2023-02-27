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

/** BuildErrorDiff */
class BuildErrorDiff
{
    public $BuildId;
    public $Type;
    public $DifferencePositive;
    public $DifferenceNegative;

    /** Return if exists */
    public function Exists(): bool
    {
        if (!$this->BuildId || !is_numeric($this->BuildId)) {
            echo 'BuildErrorDiff::Save(): BuildId not set<br>';
            return false;
        }

        if (!$this->Type || !is_numeric($this->Type)) {
            echo 'BuildErrorDiff::Save(): Type not set<br>';
            return false;
        }

        $db = Database::getInstance();

        $query = $db->executePreparedSingleRow('
                     SELECT count(*) AS c
                     FROM builderrordiff
                     WHERE buildid=? AND type=?
                 ', [intval($this->BuildId), $this->Type]);
        if (intval($query['c']) > 0) {
            return true;
        }
        return false;
    }

    // Save in the database
    public function Save(): bool
    {
        if (!$this->BuildId || !is_numeric($this->BuildId)) {
            echo 'BuildErrorDiff::Save(): BuildId not set<br>';
            return false;
        }

        if (!$this->Type || !is_numeric($this->Type)) {
            echo 'BuildErrorDiff::Save(): Type not set<br>';
            return false;
        }

        if (!$this->DifferencePositive || !is_numeric($this->DifferencePositive)) {
            echo 'BuildErrorDiff::Save(): DifferencePositive not set<br>';
            return false;
        }

        if (!$this->DifferenceNegative || !is_numeric($this->DifferenceNegative)) {
            echo 'BuildErrorDiff::Save(): DifferenceNegative not set<br>';
            return false;
        }

        $db = Database::getInstance();
        if ($this->Exists()) {
            // Update
            $query = $db->executePrepared('
                         UPDATE builderrordiff
                         SET
                             difference_positive=?,
                             difference_negative=?
                         WHERE
                             buildid=?
                             AND type=?
                     ', [
                         intval($this->DifferencePositive),
                         intval($this->DifferenceNegative),
                         intval($this->BuildId),
                         intval($this->Type)
                     ]);

            if ($query === false) {
                add_last_sql_error('BuildErrorDiff Update', 0, $this->BuildId);
                return false;
            }
        } else {
            // insert

            $query = $db->executePrepared('
                         INSERT INTO builderrordiff (
                             buildid,
                             type,
                             difference_positive,
                             difference_negative
                         )
                         VALUES (?, ?, ?, ?)
                     ', [
                         intval($this->BuildId),
                         intval($this->Type),
                         intval($this->DifferencePositive),
                         intval($this->DifferenceNegative)
                     ]);

            if ($query === false) {
                add_last_sql_error('BuildErrorDiff Create', 0, $this->BuildId);
                return false;
            }
        }
        return true;
    }
}
