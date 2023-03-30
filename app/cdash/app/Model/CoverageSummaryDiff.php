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

class CoverageSummaryDiff
{
    public $LocTested;
    public $LocUntested;
    public $BuildId;

    public function Insert(): void
    {
        $db = Database::getInstance();

        $row = $db->executePreparedSingleRow('
                   SELECT COUNT(1) AS c
                   FROM coveragesummarydiff
                   WHERE buildid=?
               ', [intval($this->BuildId)]);

        if (intval($row['c']) > 0) {
            // UPDATE instead of INSERT if a row already exists.
            $db->executePrepared('
                UPDATE coveragesummarydiff
                SET loctested=?, locuntested=?
                WHERE buildid=?
            ', [intval($this->LocTested), intval($this->LocUntested), intval($this->BuildId)]);
        } else {
            $db->executePrepared('
                INSERT INTO coveragesummarydiff (buildid, loctested, locuntested)
                VALUES (?, ?, ?)
            ', [intval($this->BuildId), intval($this->LocTested), intval($this->LocUntested)]);
        }
        add_last_sql_error('CoverageSummary:ComputeDifference');
    }

    /** Return whether or not a CoverageSummaryDiff exists for this build. */
    public function Exists(): bool
    {
        if (!$this->BuildId) {
            return false;
        }

        $db = Database::getInstance();
        $exists_result = $db->executePreparedSingleRow('
                             SELECT COUNT(1) AS e
                             FROM coveragesummarydiff
                             WHERE buildid=?
                         ', [intval($this->BuildId)]);

        return !empty($exists_result);
    }
}
