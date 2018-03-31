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

class ClientJob
{
    public $Id;
    public $ScheduleId;
    public $OsId;
    public $SiteId;
    public $StartDate;
    public $EndDate;
    public $Status;
    public $CMakeId;
    public $CompilerId;
    public $Output;

    /** Get ScheduleId */
    // commenting out until it's actually used
    /*
    function GetScheduleId()
      {
      if(!$this->Id)
        {
        add_log("ClientJob::GetScheduleId()","Id not set");
        return;
        }
      $sys = pdo_query("SELECT scheduleid FROM client_job WHERE id=".qnum($this->Id));
      $row = pdo_fetch_array($sys);
      return $row[0];
      }
    */

    /** Get StartingDate */
    public function GetStartDate()
    {
        if (!$this->Id) {
            add_log('ClientJob::GetStartDate', 'Id not set');
            return;
        }
        $sys = pdo_query('SELECT startdate FROM client_job WHERE id=' . qnum($this->Id));
        $row = pdo_fetch_array($sys);
        return $row[0];
    }

    /** Get End Date */
    public function GetEndDate()
    {
        if (!$this->Id) {
            add_log('ClientJob::GetEndDate', 'Id not set');
            return;
        }
        $sys = pdo_query('SELECT enddate FROM client_job WHERE id=' . qnum($this->Id));
        $row = pdo_fetch_array($sys);
        return $row[0];
    }

    /** Get Status */
    public function GetStatus()
    {
        if (!$this->Id) {
            add_log('ClientJob::GetStatus', 'Id not set');
            return;
        }
        $sys = pdo_query('SELECT status FROM client_job WHERE id=' . qnum($this->Id));
        $row = pdo_fetch_array($sys);
        return $row[0];
    }

    /** Get Site */
    public function GetSite()
    {
        if (!$this->Id) {
            add_log('ClientJob::GetSite', 'Id not set');
            return;
        }
        $sys = pdo_query('SELECT siteid FROM client_job WHERE id=' . qnum($this->Id));
        $row = pdo_fetch_array($sys);
        return $row[0];
    }

    /** Set the job has finished */
    public function SetFinished()
    {
        $now = date('Y-m-d H:i:s');
        $sql = 'UPDATE client_job SET status=' . CDASH_JOB_FINISHED . ",enddate='" . $now . "' WHERE siteid=" . $this->SiteId . ' AND status=' . CDASH_JOB_RUNNING;
        pdo_query($sql);
        add_last_sql_error('ClientJob::SetFinished');
    }

    /** Set the job has failed */
    public function SetFailed()
    {
        $now = date('Y-m-d H:i:s');
        $sql = 'UPDATE client_job SET status=' . CDASH_JOB_FAILED . ",enddate='" . $now . "' WHERE siteid=" . $this->SiteId . ' AND status=' . CDASH_JOB_RUNNING;
        pdo_query($sql);
        add_last_sql_error('ClientJob::SetFailed');
    }

    /** Save a job */
    public function Save()
    {
        $sql = "INSERT INTO client_job (scheduleid,osid,siteid,startdate,enddate,status,output,cmakeid,compilerid)
            VALUES ('" . $this->ScheduleId . "','" . $this->OsId . "','" . $this->SiteId . "','" . $this->StartDate . "','" . $this->EndDate
            . "','" . $this->Status . "','" . $this->Output . "','" . $this->CMakeId . "','" . $this->CompilerId . "')";
        pdo_query($sql);
        $this->Id = pdo_insert_id('client_job');
        add_last_sql_error('ClientJob::Save');
    }

    /** Remove a job */
    public function Remove()
    {
        if (!$this->Id) {
            add_log('ClientJob::Remove', 'Id not set');
            return;
        }
        pdo_query('DELETE FROM client_job WHERE id=' . qnum($this->Id));
        add_last_sql_error('ClientJob::Remove');
    }
}
