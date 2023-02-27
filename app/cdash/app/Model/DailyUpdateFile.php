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

class DailyUpdateFile
{
    public $Filename;
    public $CheckinDate;
    public $Author;
    public $Log;
    public $Revision;
    public $PriorRevision;
    public $DailyUpdateId;

    /** Check if exists */
    public function Exists(): bool
    {
        // If no id specify return false
        if (!$this->DailyUpdateId || !$this->Filename) {
            return false;
        }

        $db = Database::getInstance();

        $query = $db->executePreparedSingleRow('
                     SELECT count(*) AS c
                     FROM dailyupdatefile
                     WHERE dailyupdateid=? AND filename=?
                 ', [$this->DailyUpdateId, $this->Filename]);
        if (intval($query['c']) === 0) {
            return false;
        }
        return true;
    }

    /** Save the group */
    public function Save(): bool
    {
        if (!$this->DailyUpdateId) {
            echo 'DailyUpdateFile::Save(): DailyUpdateId not set!';
            return false;
        }

        if (!$this->Filename) {
            echo 'DailyUpdateFile::Save(): Filename not set!';
            return false;
        }

        if (!$this->CheckinDate) {
            echo 'DailyUpdateFile::Save(): CheckinDate not set!';
            return false;
        }

        $db = Database::getInstance();

        if ($this->Exists()) {
            // Update the project
            $query = $db->executePrepared('
                         UPDATE dailyupdatefile
                         SET
                             checkindate=?,
                             author=?,
                             log=?,
                             revision=?,
                             priorrevision=?
                         WHERE
                             dailyupdateid=?
                             AND filename=?
                     ', [
                         $this->CheckinDate,
                         $this->Author,
                         $this->Log,
                         $this->Revision,
                         $this->PriorRevision,
                         $this->DailyUpdateId,
                         $this->Filename
                     ]);

            if ($query === false) {
                add_last_sql_error('DailyUpdateFile Update');
                return false;
            }
        } else {
            $query = $db->executePrepared('
                         INSERT INTO dailyupdatefile (
                             dailyupdateid,
                             filename,
                             checkindate,
                             author,
                             log,
                             revision,
                             priorrevision
                         )
                         VALUES (?, ?, ?, ?, ?, ?, ?)
                     ', [
                         $this->DailyUpdateId,
                         $this->Filename,
                         $this->CheckinDate,
                         $this->Author,
                         $this->Log,
                         $this->Revision,
                         $this->PriorRevision
                     ]);

            if ($query === false) {
                add_last_sql_error('DailyUpdateFile Insert');
                return false;
            }
        }
        return true;
    }
}
