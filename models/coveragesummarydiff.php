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

class coveragesummarydiff
{
    public $LocTested;
    public $LocUntested;
    public $BuildId;

    public function Insert()
    {
        $row = pdo_single_row_query(
            "SELECT COUNT(1) FROM coveragesummarydiff
                WHERE buildid=" . qnum($this->BuildId));
        if ($row[0] > 0) {
            // UPDATE instead of INSERT if a row already exists.
            pdo_query(
                "UPDATE coveragesummarydiff SET
                    loctested=" . qnum($this->LocTested) . ",
                    locuntested=" . qnum($this->LocUntested) . "
                    WHERE buildid=" . qnum($this->BuildId));
        } else {
            pdo_query(
                "INSERT INTO coveragesummarydiff
                    (buildid,loctested,locuntested)
                    VALUES
                    (" . qnum($this->BuildId) . "," . qnum($this->LocTested) . "," . qnum($this->LocUntested) . ")");
        }
        add_last_sql_error("CoverageSummary:ComputeDifference");
    }
}
