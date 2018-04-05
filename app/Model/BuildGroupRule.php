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

class BuildGroupRule
{
    public $BuildType;
    public $BuildName;
    public $SiteId;
    public $Expected;
    public $StartTime;
    public $EndTime;
    public $GroupId;

    public function __construct()
    {
        $this->StartTime = '1980-01-01 00:00:00';
        $this->EndTime = '1980-01-01 00:00:00';
    }

    /** Check if the rule already exists */
    public function Exists()
    {
        // If no id specify return false
        if (empty($this->GroupId) || empty($this->BuildType)
            || empty($this->BuildName) || empty($this->SiteId)
        ) {
            return false;
        }

        $query = pdo_query("SELECT count(*) AS c FROM build2grouprule WHERE
                        groupid='" . $this->GroupId . "' AND buildtype='" . $this->BuildType . "'
                        AND buildname='" . $this->BuildName . "'
                        AND siteid='" . $this->SiteId . "'
                        AND starttime='" . $this->StartTime . "'
                        AND endtime='" . $this->EndTime . "'"
        );
        $query_array = pdo_fetch_array($query);
        if ($query_array['c'] == 0) {
            return false;
        }
        return true;
    }

    /** Save the goup position */
    public function Add()
    {
        if (empty($this->GroupId) || empty($this->BuildType)
            || empty($this->BuildName) || empty($this->SiteId) || empty($this->Expected)
        ) {
            return false;
        }

        if (!$this->Exists()) {
            if (!pdo_query("INSERT INTO build2grouprule (groupid,buildtype,buildname,siteid,expected,starttime,endtime)
                     VALUES ('$this->GroupId','$this->BuildType','$this->BuildName','$this->SiteId','$this->Expected','$this->StartTime','$this->EndTime')")
            ) {
                add_last_sql_error('BuildGroupRule Insert()');
                return false;
            }
            return true;
        }
        return false;
    }
}
