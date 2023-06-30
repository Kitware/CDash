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

class BuildUpdateFile
{
    public $Filename;
    public $CheckinDate;
    public $Author;
    public $Email;
    public $Committer;
    public $CommitterEmail;
    public $Log;
    public $Revision;
    public $PriorRevision;
    public $Status; //MODIFIED | CONFLICTING | UPDATED
    public $UpdateId;

    // Insert the update
    public function Insert(): bool
    {
        if (strlen($this->UpdateId) == 0) {
            abort(500, 'BuildUpdateFile:Insert UpdateId not set');
        }

        // Sometimes the checkin date is not found in that case we put the usual date
        if ($this->CheckinDate === null || $this->CheckinDate === 'Unknown') {
            $this->CheckinDate = '1980-01-01';
        }

        if (strtotime($this->CheckinDate) === false && is_numeric($this->CheckinDate)) {
            $this->CheckinDate = date(FMT_DATETIME, $this->CheckinDate);
        } elseif (strtotime($this->CheckinDate) !== false) {
            $this->CheckinDate = date(FMT_DATETIME, strtotime($this->CheckinDate));
        } else {
            $this->CheckinDate = '1980-01-01';
        }

        $db = Database::getInstance();

        $query = $db->executePrepared('
                     INSERT INTO updatefile (
                         updateid,
                         filename,
                         checkindate,
                         author,
                         email,
                         log,
                         revision,
                         priorrevision,
                         status,
                         committer,
                         committeremail
                     )
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ', [
                     intval($this->UpdateId),
                     $this->Filename ?? '',
                     $this->CheckinDate ?? '',
                     $this->Author ?? '',
                     $this->Email ?? '',
                     $this->Log ?? '',
                     $this->Revision ?? '',
                     $this->PriorRevision ?? '',
                     $this->Status ?? '',
                     $this->Committer ?? '',
                     $this->CommitterEmail ?? ''
                 ]);

        if ($query === false) {
            add_last_sql_error('BuildUpdateFile Insert', 0, $this->UpdateId);
            return false;
        }

        return true;
    }
}
