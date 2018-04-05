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
    public function Exists()
    {
        // If no id specify return false
        if (!$this->DailyUpdateId || !$this->Filename) {
            return false;
        }

        $query = pdo_query("SELECT count(*) AS c FROM dailyupdatefile WHERE dailyupdateid='" . $this->DailyUpdateId . "' AND filename='" . $this->Filename . "'");
        $query_array = pdo_fetch_array($query);
        if ($query_array['c'] == 0) {
            return false;
        }
        return true;
    }

    /** Save the group */
    public function Save()
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

        if ($this->Exists()) {
            // Update the project
            $query = 'UPDATE dailyupdatefile SET';
            $query .= " checkindate='" . $this->CheckinDate . "'";
            $query .= ",author='" . $this->Author . "'";
            $query .= ",log='" . $this->Log . "'";
            $query .= ",revision='" . $this->Revision . "'";
            $query .= ",priorrevision='" . $this->PriorRevision . "'";
            $query .= " WHERE dailyupdateid='" . $this->DailyUpdateId . "' AND filename='" . $this->Filename . "'";

            if (!pdo_query($query)) {
                add_last_sql_error('DailyUpdateFile Update');
                return false;
            }
        } else {
            if (!pdo_query("INSERT INTO dailyupdatefile (dailyupdateid,filename,checkindate,author,log,revision,priorrevision)
                     VALUES ('$this->DailyUpdateId','$this->Filename','$this->CheckinDate','$this->Author','$this->Log',
                     '$this->Revision','$this->PriorRevision')")
            ) {
                add_last_sql_error('DailyUpdateFile Insert');
                return false;
            }
        }
        return true;
    }
}
