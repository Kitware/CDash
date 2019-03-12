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

class BuildGroupRule
{
    public $BuildType;
    public $BuildName;
    public $SiteId;
    public $Expected;
    public $StartTime;
    public $EndTime;
    public $GroupId;
    private $PDO;

    public function __construct()
    {
        $this->BuildType = '';
        $this->BuildName = '';
        $this->SiteId = 0;
        $this->Expected = 0;
        $this->StartTime = '1980-01-01 00:00:00';
        $this->EndTime = '1980-01-01 00:00:00';
        $this->GroupId = 0;

        $this->PDO = Database::getInstance();
    }

    /** Check if the rule already exists */
    public function Exists()
    {
        // If no id specified return false.
        if (empty($this->GroupId) || empty($this->BuildType)
            || empty($this->BuildName) || empty($this->SiteId)
        ) {
            return false;
        }

        $stmt = $this->PDO->prepare(
            'SELECT count(*) AS c FROM build2grouprule
            WHERE groupid = :groupid AND
                  buildtype = :buildtype AND
                  buildname = :buildname AND
                  siteid = :siteid AND
                  starttime = :starttime AND
                  endtime = :endtime');
        $query_params = [
            ':groupid'   => $this->GroupId,
            ':buildtype' =>  $this->BuildType,
            ':buildname' => $this->BuildName,
            ':siteid'    =>  $this->SiteId,
            ':starttime' => $this->StartTime,
            ':endtime'   => $this->EndTime
        ];

        $this->PDO->execute($stmt, $query_params);
        if ($stmt->fetchColumn() == 0) {
            return false;
        }
        return true;
    }

    /** Save the rule */
    public function Add()
    {
        if (empty($this->GroupId) || empty($this->BuildType)
            || empty($this->BuildName) || empty($this->SiteId) || empty($this->Expected)
        ) {
            return false;
        }

        if (!$this->Exists()) {
            $stmt = $this->PDO->prepare(
                'INSERT INTO build2grouprule
                    (groupid, buildtype, buildname, siteid, expected,
                     starttime, endtime)
                 VALUES
                    (:groupid, :buildtype, :buildname, :siteid, :expected,
                     :starttime, :endtime)');
            $query_params = [
                ':groupid'   => $this->GroupId,
                ':buildtype' => $this->BuildType,
                ':buildname' => $this->BuildName,
                ':siteid'    => $this->SiteId,
                ':expected'  => $this->Expected,
                ':starttime' => $this->StartTime,
                ':endtime'   => $this->EndTime
            ];
            return $this->PDO->execute($stmt, $query_params);
        }
        return false;
    }

    /** Delete a rule */
    public function Delete()
    {
        $stmt = $this->PDO->prepare(
                'DELETE FROM build2grouprule
                WHERE groupid = :groupid AND
                      buildtype = :buildtype AND
                      buildname = :buildname AND
                      siteid = :siteid AND
                      expected = :expected AND
                      starttime = :starttime AND
                      endtime = :endtime');
        $query_params = [
            ':groupid'   => $this->GroupId,
            ':buildtype' => $this->BuildType,
            ':buildname' => $this->BuildName,
            ':siteid'    => $this->SiteId,
            ':expected'  => $this->Expected,
            ':starttime' => $this->StartTime,
            ':endtime'   => $this->EndTime
        ];
        return $this->PDO->execute($stmt, $query_params);
    }
}
