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

class BuildGroupPosition
{
    public $Position;
    public $StartTime;
    public $EndTime;
    public $GroupId;

    public function __construct()
    {
        $this->StartTime = '1980-01-01 00:00:00';
        $this->EndTime = '1980-01-01 00:00:00';
        $this->Position = 1;
    }

    /** Check if the position already exists */
    public function Exists()
    {
        // If no id specify return false
        if (!$this->GroupId) {
            return false;
        }

        $db = Database::getInstance();
        $query = $db->executePreparedSingleRow('
                     SELECT count(*) AS c
                     FROM buildgroupposition
                     WHERE
                         buildgroupid=?
                         AND position=?
                         AND starttime=?
                         AND endtime=?
                     ', [intval($this->GroupId), intval($this->Position), $this->StartTime, $this->EndTime]);
        return intval($query['c']) !== 0;
    }

    /** Save the goup position */
    public function Add()
    {
        if ($this->Exists()) {
            return false;
        }

        $db = Database::getInstance();
        $query = $db->executePrepared('
                     INSERT INTO buildgroupposition (buildgroupid, position, starttime, endtime)
                     VALUES (?, ?, ?, ?)
                 ', [intval($this->GroupId), intval($this->Position), $this->StartTime, $this->EndTime]);
        if ($query === false) {
            add_last_sql_error('BuildGroupPosition Insert()');
            return false;
        }
        return true;
    }
}
