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

class BuildGroup
{
    const NIGHTLY = 'Nightly';
    const EXPERIMENTAL = 'Experimental';

    private $Id;
    private $ProjectId;
    private $Name;
    private $StartTime;
    private $EndTime;
    private $Description;
    private $SummaryEmail;
    private $Type;
    private $Position;
    private $PDO;

    public function __construct()
    {
        $this->Id = 0;
        $this->Name = '';
        $this->ProjectId = 0;
        $this->StartTime = '1980-01-01 00:00:00';
        $this->EndTime = '1980-01-01 00:00:00';
        $this->AutoRemoveTimeFrame = 0;
        $this->Description = '';
        $this->SummaryEmail = 0;
        $this->IncludeSubProjectTotal = 1;
        $this->EmailCommitters = 0;
        $this->Type = 'Daily';
        $this->Position = 0;

        $this->PDO = Database::getInstance()->getPdo();
    }

    /** Get the id */
    public function GetId()
    {
        return $this->Id;
    }

    /** Set the id.  Also loads remaining data for this
     * buildgroup from the database.
     **/
    public function SetId($id)
    {
        if (!is_numeric($id)) {
            return false;
        }

        $this->Id = $id;

        $row = pdo_single_row_query(
            'SELECT * FROM buildgroup WHERE id=' . qnum($this->Id));
        if (empty($row)) {
            return false;
        }

        $this->FillFromRow($row);
        return true;
    }

    /** Get the Name of the buildgroup */
    public function GetName()
    {
        if (strlen($this->Name) > 0) {
            return $this->Name;
        }

        if ($this->Id < 1) {
            add_log('BuildGroup GetName(): Id not set', 'GetName', LOG_ERR);
            return false;
        }

        $project = pdo_query('SELECT name FROM buildgroup WHERE id=' . qnum($this->Id));
        if (!$project) {
            add_last_sql_error('BuildGroup GetName');
            return false;
        }
        $project_array = pdo_fetch_array($project);
        $this->Name = $project_array['name'];
        return $this->Name;
    }

    /** Set the Name of the buildgroup. */
    public function SetName($name)
    {
        $this->Name = pdo_real_escape_string($name);
        if ($this->ProjectId > 0) {
            $this->Fill();
        }
    }

    /** Get the project id */
    public function GetProjectId()
    {
        return $this->ProjectId;
    }

    /** Set the project id */
    public function SetProjectId($projectid)
    {
        if (is_numeric($projectid)) {
            $this->ProjectId = $projectid;
            if ($this->Name != '') {
                $this->Fill();
            }
            return true;
        }
        return false;
    }

    /** Get/Set the start time */
    public function GetStartTime()
    {
        if ($this->Id < 1) {
            add_log('BuildGroup GetStartTime(): Id not set', 'GetStartTime', LOG_ERR);
            return false;
        }
        return $this->StartTime;
    }

    public function SetStartTime($time)
    {
        $this->StartTime = pdo_real_escape_string($time);
    }

    /** Get/Set the end time */
    public function GetEndTime()
    {
        if ($this->Id < 1) {
            add_log('BuildGroup GetEndTime(): Id not set', 'GetEndTime', LOG_ERR);
            return false;
        }
        return $this->EndTime;
    }

    public function SetEndTime($time)
    {
        $this->EndTime = pdo_real_escape_string($time);
    }

    /** Get/Set the autoremove timeframe */
    public function GetAutoRemoveTimeFrame()
    {
        if ($this->Id < 1) {
            add_log('BuildGroup GetAutoRemoveTimeFrame(): Id not set', 'GetAutoRemoveTimeFrame', LOG_ERR);
            return false;
        }
        return $this->AutoRemoveTimeFrame;
    }

    public function SetAutoRemoveTimeFrame($timeframe)
    {
        if (!is_numeric($timeframe)) {
            return false;
        }
        $this->AutoRemoveTimeFrame = $timeframe;
    }

    /** Get/Set the description */
    public function GetDescription()
    {
        if ($this->Id < 1) {
            add_log('BuildGroup GetDescription(): Id not set', 'GetDescription', LOG_ERR);
            return false;
        }
        return $this->Description;
    }

    public function SetDescription($desc)
    {
        $this->Description = pdo_real_escape_string($desc);
    }

    /** Get/Set the email settings for this BuildGroup.
     * 0: project default settings
     * 1: summary email
     * 2: no email
     **/
    public function GetSummaryEmail()
    {
        if ($this->Id < 1) {
            add_log('BuildGroup GetSummaryEmail(): Id not set', 'GetSummaryEmail', LOG_ERR);
            return false;
        }
        return $this->SummaryEmail;
    }

    public function SetSummaryEmail($email)
    {
        if (!is_numeric($email)) {
            return false;
        }
        $this->SummaryEmail = $email;
    }

    /** Get/Set whether or not this group should include subproject total. */
    public function GetIncludeSubProjectTotal()
    {
        if ($this->Id < 1) {
            add_log('BuildGroup GetIncludeSubProjectTotal(): Id not set', 'GetIncludeSubProjectTotal', LOG_ERR);
            return false;
        }
        return $this->IncludeSubProjectTotal;
    }

    public function SetIncludeSubProjectTotal($b)
    {
        if ($b) {
            $this->IncludeSubProjectTotal = 1;
        } else {
            $this->IncludeSubProjectTotal = 0;
        }
    }

    /** Get/Set whether or not committers should be emailed for this group. */
    public function GetEmailCommitters()
    {
        if ($this->Id < 1) {
            add_log('BuildGroup GetEmailCommitters(): Id not set', 'GetEmailCommitters', LOG_ERR);
            return false;
        }
        return $this->EmailCommitters;
    }

    public function SetEmailCommitters($b)
    {
        if ($b) {
            $this->EmailCommitters = 1;
        } else {
            $this->EmailCommitters = 0;
        }
    }

    /** Get/Set the type */
    public function GetType()
    {
        if ($this->Id < 1) {
            add_log('BuildGroup GetType(): Id not set', 'GetType', LOG_ERR);
            return false;
        }
        return $this->Type;
    }

    public function SetType($type)
    {
        $this->Type = pdo_real_escape_string($type);
    }

    /** Populate the ivars of an existing buildgroup.
     * Called automatically once name & projectid are set.
     **/
    public function Fill()
    {
        if ($this->Name == '' || $this->ProjectId == 0) {
            add_log(
                "Name='" . $this->Name . "' or ProjectId='" . $this->ProjectId . "' not set",
                'BuildGroup::Fill',
                LOG_WARNING);
            return false;
        }

        $row = pdo_single_row_query(
            'SELECT * FROM buildgroup
       WHERE projectid=' . qnum($this->ProjectId) . " AND name='$this->Name'");

        if (empty($row)) {
            return false;
        }

        $this->FillFromRow($row);
        return true;
    }

    /** Helper function for filling in a buildgroup instance */
    public function FillFromRow($row)
    {
        $this->Id = $row['id'];
        $this->Name = $row['name'];
        $this->ProjectId = $row['projectid'];
        $this->StartTime = $row['starttime'];
        $this->EndTime = $row['endtime'];
        $this->AutoRemoveTimeFrame = $row['autoremovetimeframe'];
        $this->Description = $row['description'];
        $this->SummaryEmail = $row['summaryemail'];
        $this->IncludeSubProjectTotal = $row['includesubprojectotal'];
        $this->EmailCommitters = $row['emailcommitters'];
        $this->Type = $row['type'];
    }

    /** Get/Set this BuildGroup's position (the order it should appear in) */
    public function GetPosition()
    {
        if ($this->Position > 0) {
            return $this->Position;
        }

        if ($this->Id < 1) {
            add_log('BuildGroup GetPosition(): Id not set', 'GetPosition', LOG_ERR);
            return false;
        }

        $stmt = $this->PDO->prepare('
            SELECT position FROM buildgroupposition
            WHERE buildgroupid = :id
            ORDER BY position DESC LIMIT 1');
        pdo_execute($stmt, [':id' => $this->Id]);
        $position = $stmt->fetchColumn();

        if (!$position) {
            add_log(
                "BuildGroup GetPosition(): no position found for buildgroup # $this->Id !",
                'GetPosition',
                LOG_ERR);
            return false;
        }

        $this->Position = $position;
        return $this->Position;
    }

    public function SetPosition(BuildGroupPosition $position)
    {
        $position->GroupId = $this->Id;
        $position->Add();
    }

    /** Get the next position available for that group */
    public function GetNextPosition()
    {
        $query = pdo_query("SELECT bg.position FROM buildgroupposition as bg,buildgroup as g
                        WHERE bg.buildgroupid=g.id AND g.projectid='" . $this->ProjectId . "'
                        AND bg.endtime='1980-01-01 00:00:00'
                        ORDER BY bg.position DESC LIMIT 1");
        if (pdo_num_rows($query) > 0) {
            $query_array = pdo_fetch_array($query);
            return $query_array['position'] + 1;
        }
        return 1;
    }

    /** Check if the group already exists */
    public function Exists()
    {
        // If no id specify return false
        if (!$this->Id || !$this->ProjectId) {
            return false;
        }

        $query = pdo_query("SELECT count(*) AS c FROM buildgroup WHERE id='" . $this->Id . "' AND projectid='" . $this->ProjectId . "'");
        add_last_sql_error('BuildGroup:Exists', $this->ProjectId);
        $query_array = pdo_fetch_array($query);
        if ($query_array['c'] == 0) {
            return false;
        }
        return true;
    }

    /** Save the group */
    public function Save()
    {
        if ($this->Exists()) {
            // Update the project
            $query = "
        UPDATE buildgroup SET
          name='$this->Name',
          projectid='$this->ProjectId',
          starttime='$this->StartTime',
          endtime='$this->EndTime',
          autoremovetimeframe='$this->AutoRemoveTimeFrame',
          description='$this->Description',
          summaryemail='$this->SummaryEmail',
          includesubprojectotal='$this->IncludeSubProjectTotal',
          emailcommitters='$this->EmailCommitters',
          type='$this->Type'
        WHERE id='$this->Id'";
            if (!pdo_query($query)) {
                add_last_sql_error('BuildGroup:Update', $this->ProjectId);
                return false;
            }
        } else {
            $id = '';
            $idvalue = '';
            if ($this->Id > 0) {
                $id = 'id,';
                $idvalue = "'" . $this->Id . "',";
            }

            $query = '
        INSERT INTO buildgroup
          (' . $id . 'name,projectid,starttime,endtime,autoremovetimeframe,
           description,summaryemail,includesubprojectotal,emailcommitters, type)
        VALUES
          (' . $idvalue . "'$this->Name','$this->ProjectId','$this->StartTime',
           '$this->EndTime','$this->AutoRemoveTimeFrame','$this->Description',
           '$this->SummaryEmail','$this->IncludeSubProjectTotal',
           '$this->EmailCommitters','$this->Type')";

            if (!pdo_query($query)) {
                add_last_sql_error('Buildgroup Insert', $this->ProjectId);
                return false;
            }

            if (!$this->Id) {
                $this->Id = pdo_insert_id('buildgroup');
            }

            // Insert the default position for this group
            // Find the position for this group
            $position = $this->GetNextPosition();
            pdo_query("
        INSERT INTO buildgroupposition
          (buildgroupid,position,starttime,endtime)
        VALUES
          ('$this->Id','$position','$this->StartTime','$this->EndTime')");
        }
        return true;
    }

    /** Delete this BuildGroup. */
    public function Delete()
    {
        if (!$this->Exists()) {
            return false;
        }

        // We delete all the build2grouprule associated with the group
        pdo_query("DELETE FROM build2grouprule WHERE groupid='$this->Id'");

        // We delete the buildgroup
        pdo_query("DELETE FROM buildgroup WHERE id='$this->Id'");

        // Restore the builds that were associated with this group
        $oldbuilds = pdo_query("
      SELECT id,type FROM build WHERE id IN
        (SELECT buildid AS id FROM build2group WHERE groupid='$this->Id')");
        echo pdo_error();
        while ($oldbuilds_array = pdo_fetch_array($oldbuilds)) {
            // Move the builds
            $buildid = $oldbuilds_array['id'];
            $buildtype = $oldbuilds_array['type'];

            // Find the group corresponding to the build type
            $query = pdo_query("
        SELECT id FROM buildgroup
        WHERE name='$buildtype' AND projectid='$this->ProjectId'");
            if (pdo_num_rows($query) == 0) {
                $query = pdo_query("
          SELECT id FROM buildgroup
          WHERE name='Experimental' AND projectid='$this->ProjectId'");
            }
            echo pdo_error();
            $grouptype_array = pdo_fetch_array($query);
            $grouptype = $grouptype_array['id'];

            pdo_query("
        UPDATE build2group SET groupid='$grouptype' WHERE buildid='$buildid'");
            echo pdo_error();
        }

        // Delete the buildgroupposition and update the position
        // of the other groups.
        pdo_query("DELETE FROM buildgroupposition WHERE buildgroupid='$this->Id'");
        $buildgroupposition = pdo_query("
      SELECT bg.buildgroupid FROM buildgroupposition AS bg, buildgroup AS g
      WHERE g.projectid='$this->ProjectId' AND bg.buildgroupid=g.id
      ORDER BY bg.position ASC");

        $p = 1;
        while ($buildgroupposition_array = pdo_fetch_array($buildgroupposition)) {
            $buildgroupid = $buildgroupposition_array['buildgroupid'];
            pdo_query("
        UPDATE buildgroupposition SET position='$p'
        WHERE buildgroupid='$buildgroupid'");
            $p++;
        }
    }

    public function GetGroupIdFromRule($build)
    {
        $name = $build->Name;
        $type = $build->Type;
        $siteid = $build->SiteId;
        $starttime = $build->StartTime;
        $projectid = $build->ProjectId;

        // Insert the build into the proper group
        // 1) Check if we have any build2grouprules for this build
        $build2grouprule = pdo_query("SELECT b2g.groupid FROM build2grouprule AS b2g, buildgroup as bg
                                  WHERE b2g.buildtype='$type' AND b2g.siteid='$siteid' AND b2g.buildname='$name'
                                  AND (b2g.groupid=bg.id AND bg.projectid='$projectid')
                                  AND '$starttime'>b2g.starttime
                                  AND ('$starttime'<b2g.endtime OR b2g.endtime='1980-01-01 00:00:00')");

        if (pdo_num_rows($build2grouprule) > 0) {
            $build2grouprule_array = pdo_fetch_array($build2grouprule);
            return $build2grouprule_array['groupid'];
        }

        // 2) Check for buildname-based groups
        $build2grouprule = pdo_query("SELECT b2g.groupid FROM build2grouprule AS b2g, buildgroup as bg
                                 WHERE b2g.buildtype='$type' AND b2g.siteid='-1' AND '$name' LIKE b2g.buildname
                                 AND (b2g.groupid=bg.id AND bg.projectid='$projectid')
                                 AND '$starttime'>b2g.starttime
                                 AND ('$starttime'<b2g.endtime OR b2g.endtime='1980-01-01 00:00:00')
                                 ORDER BY LENGTH(b2g.buildname) DESC");

        if (pdo_num_rows($build2grouprule) > 0) {
            $build2grouprule_array = pdo_fetch_array($build2grouprule);
            return $build2grouprule_array['groupid'];
        }

        // If we reach this far, none of the rules matched.
        // Just use the default group for the build type.
        $buildgroup = pdo_query("SELECT id FROM buildgroup WHERE name='$type' AND projectid='$projectid'");
        if (pdo_num_rows($buildgroup) == 0) {
            // if the group does not exist we assign it to experimental

            $buildgroup = pdo_query("SELECT id FROM buildgroup WHERE name='Experimental' AND projectid='$projectid'");
        }
        $buildgroup_array = pdo_fetch_array($buildgroup);
        return $buildgroup_array['id'];
    }

    // Return an array of currently active BuildGroups
    // given a projectid and a starting datetime string.
    public static function GetBuildGroups($projectid, $begin)
    {
        $pdo = Database::getInstance();
        $buildgroups = [];

        $stmt = $pdo->prepare("
            SELECT bg.id, bg.name, bgp.position
            FROM buildgroup AS bg
            LEFT JOIN buildgroupposition AS bgp ON (bgp.buildgroupid = bg.id)
            WHERE bg.projectid = :projectid AND
                  bg.starttime < :begin AND
                  (bg.endtime > :begin OR bg.endtime='1980-01-01 00:00:00')");

        $pdo->execute($stmt, [':projectid' => $projectid, ':begin' => $begin]);
        while ($row = $stmt->fetch()) {
            $buildgroup = new BuildGroup();
            $buildgroup->Id = $row['id'];
            $buildgroup->Name = $row['name'];
            $buildgroup->Position = $row['position'];
            $buildgroups[] = $buildgroup;
        }

        return $buildgroups;
    }
}
