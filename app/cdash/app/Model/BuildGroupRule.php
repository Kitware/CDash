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
    public $BuildName;
    public $BuildType;
    public $EndTime;
    public $Expected;
    public $GroupId;
    public $ParentGroupId;
    public $ProjectId;
    public $SiteId;
    public $StartTime;

    private $PDO;

    public function __construct(Build $build = null)
    {
        if (!is_null($build)) {
            $this->BuildType = $build->Type;
            $this->BuildName = $build->Name;
            $this->SiteId = $build->SiteId;
            $this->GroupId = $build->GroupId;
            $this->ProjectId = $build->ProjectId;
        } else {
            $this->BuildType = '';
            $this->BuildName = '';
            $this->SiteId = 0;
            $this->GroupId = 0;
            $this->ProjectId = 0;
        }
        $this->Expected = 0;
        $this->StartTime = '1980-01-01 00:00:00';
        $this->EndTime = '1980-01-01 00:00:00';
        $this->ParentGroupId = 0;

        $this->PDO = Database::getInstance();
    }

    /** Check if the rule already exists */
    public function Exists()
    {
        // If no group id specified return false.
        if (!$this->GroupId) {
            return false;
        }

        $stmt = $this->PDO->prepare(
            'SELECT count(*) AS c FROM build2grouprule
            WHERE groupid       = :groupid AND
                  parentgroupid = :parentgroupid AND
                  buildtype     = :buildtype AND
                  buildname     = :buildname AND
                  siteid        = :siteid AND
                  endtime       = :endtime');
        $query_params = [
            ':groupid'       => $this->GroupId,
            ':parentgroupid' => $this->ParentGroupId,
            ':buildtype'     => $this->BuildType,
            ':buildname'     => $this->BuildName,
            ':siteid'        => $this->SiteId,
            ':endtime'       => $this->EndTime,
        ];

        $this->PDO->execute($stmt, $query_params);
        if ($stmt->fetchColumn() == 0) {
            return false;
        }
        return true;
    }

    /** Insert this rule into the database. */
    public function Save()
    {
        if (!$this->GroupId) {
            add_log('GroupId not set', 'BuildGroupRule::Save', LOG_ERR);
            return false;
        }

        if (!$this->Exists()) {
            $stmt = $this->PDO->prepare(
                'INSERT INTO build2grouprule
                    (groupid, parentgroupid, buildtype, buildname, siteid,
                     expected, starttime, endtime)
                 VALUES
                    (:groupid, :parentgroupid, :buildtype, :buildname, :siteid,
                     :expected, :starttime, :endtime)');
            $query_params = [
                ':groupid'       => $this->GroupId,
                ':parentgroupid' => $this->ParentGroupId,
                ':buildtype'     => $this->BuildType,
                ':buildname'     => $this->BuildName,
                ':siteid'        => $this->SiteId,
                ':expected'      => $this->Expected,
                ':starttime'     => $this->StartTime,
                ':endtime'       => $this->EndTime,
            ];
            return $this->PDO->execute($stmt, $query_params);
        }
        return false;
    }

    public function SetExpected()
    {
        // Insert a new row if one doesn't already exist for this rule.
        if (!$this->Exists()) {
            return $this->Save();
        }

        // Otherwise update an existing row.
        $stmt = $this->PDO->prepare(
            "UPDATE build2grouprule SET expected = :expected
            WHERE groupid   = :groupid AND
                  buildtype = :buildtype AND
                  buildname = :buildname AND
                  siteid    = :siteid AND
                  endtime   = '1980-01-01 00:00:00'");
        return $this->PDO->execute($stmt, [
                ':expected'  => $this->Expected,
                ':groupid'   => $this->GroupId,
                ':buildtype' => $this->BuildType,
                ':buildname' => $this->BuildName,
                ':siteid'    => $this->SiteId]);
    }

    public function GetExpected()
    {
        $stmt = $this->PDO->prepare(
            'SELECT expected FROM build2grouprule
            WHERE groupid   = :groupid   AND
                  buildtype = :buildtype AND
                  buildname = :buildname AND
                  siteid    = :siteid    AND
                  endtime   = :endtime');
        $this->PDO->execute($stmt, [
            ':groupid'   => $this->GroupId,
            ':buildtype' => $this->BuildType,
            ':buildname' => $this->BuildName,
            ':siteid'    => $this->SiteId,
            ':endtime'   => $this->EndTime]);
        return $stmt->fetchColumn() ? 1 : 0;
    }

    /** Delete a rule */
    public function Delete($soft = true)
    {
        if ($soft) {
            return $this->SoftDelete();
        } else {
            return $this->HardDelete();
        }
    }

    /** Soft delete (mark a build rule as finished). */
    private function SoftDelete()
    {
        $now = gmdate(FMT_DATETIME);
        $stmt = $this->PDO->prepare(
            'UPDATE build2grouprule
            SET endtime = :endtime
            WHERE groupid       = :groupid AND
                  parentgroupid = :parentgroupid AND
                  buildtype     = :buildtype AND
                  buildname     = :buildname AND
                  siteid        = :siteid AND
                  endtime       = :begin_epoch');
        $query_params = [
            ':endtime'         => $now,
            ':groupid'         => $this->GroupId,
            ':parentgroupid'   => $this->ParentGroupId,
            ':buildtype'       => $this->BuildType,
            ':buildname'       => $this->BuildName,
            ':siteid'          => $this->SiteId,
            ':begin_epoch'     => '1980-01-01 00:00:00',
        ];
        return $this->PDO->execute($stmt, $query_params);
    }

    /** Hard delete (remove a build rule from the database). */
    private function HardDelete()
    {
        $stmt = $this->PDO->prepare(
            'DELETE FROM build2grouprule
                WHERE groupid       = :groupid AND
                      parentgroupid = :parentgroupid AND
                      buildtype     = :buildtype AND
                      buildname     = :buildname AND
                      siteid        = :siteid AND
                      expected      = :expected AND
                      starttime     = :starttime AND
                      endtime       = :endtime');
        $query_params = [
            ':groupid'         => $this->GroupId,
            ':parentgroupid'   => $this->ParentGroupId,
            ':buildtype'       => $this->BuildType,
            ':buildname'       => $this->BuildName,
            ':siteid'          => $this->SiteId,
            ':expected'        => $this->Expected,
            ':starttime'       => $this->StartTime,
            ':endtime'         => $this->EndTime,
        ];
        return $this->PDO->execute($stmt, $query_params);
    }

    /** Soft delete all active previous versions of this rule. */
    public function SoftDeleteExpiredRules($now)
    {
        $stmt = $this->PDO->prepare(
            "UPDATE build2grouprule
            SET endtime = :endtime
            WHERE buildtype = :buildtype AND
                  buildname = :buildname AND
                  siteid    = :siteid AND
                  endtime   = '1980-01-01 00:00:00' AND
                  groupid IN
                      (SELECT id FROM buildgroup WHERE projectid = :projectid)");
        $this->PDO->execute($stmt, [
                ':endtime'   => $now,
                ':projectid' => $this->ProjectId,
                ':buildtype' => $this->BuildType,
                ':buildname' => $this->BuildName,
                ':siteid'    => $this->SiteId]);
    }

    /** Change the group that this rule points to. */
    public function ChangeGroup($newgroupid)
    {
        $stmt = $this->PDO->prepare(
            "UPDATE build2grouprule SET groupid = :newgroupid
            WHERE groupid   = :groupid   AND
                  buildtype = :buildtype AND
                  buildname = :buildname AND
                  siteid    = :siteid    AND
                  endtime   = '1980-01-01 00:00:00'");

        $query_params = [
            ':newgroupid' => $newgroupid,
            ':groupid'    => $this->GroupId,
            ':buildtype'  => $this->BuildType,
            ':buildname'  => $this->BuildName,
            ':siteid'     => $this->SiteId];

        $this->PDO->execute($stmt, $query_params);

        // Move any builds that follow this rule to the new group.
        $stmt = $this->PDO->prepare(
            'UPDATE build2group SET groupid = :newgroupid
            WHERE groupid = :groupid AND
                  buildid IN
                  (SELECT id FROM build WHERE siteid = :siteid    AND
                                              name   = :buildname AND
                                              type   = :buildtype)');
        $this->PDO->execute($stmt, $query_params);
    }

    public static function DeleteExpiredRulesForProject($projectid, $cutoff_date)
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "DELETE FROM build2grouprule
            WHERE groupid IN
                (SELECT id FROM buildgroup WHERE projectid = :projectid)
            AND endtime != '1980-01-01 00:00:00'
            AND endtime < :endtime");
        $query_params = [
            ':projectid' => $projectid,
            ':endtime'   => $cutoff_date,
        ];
        $db->execute($stmt, $query_params);
    }

    // Populate this object with a row from the database and a projectid.
    public function FillFromRow($row, $projectid)
    {
        $this->BuildName = $row['buildname'];
        $this->BuildType = $row['buildtype'];
        $this->EndTime = $row['endtime'];
        $this->Expected = $row['expected'];
        $this->GroupId = $row['groupid'];
        $this->ParentGroupId = $row['parentgroupid'];
        $this->ProjectId = $projectid;
        $this->SiteId = $row['siteid'];
        $this->StartTime = $row['starttime'];
    }
}
