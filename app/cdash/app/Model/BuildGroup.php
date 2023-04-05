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
use Illuminate\Support\Facades\DB;
use CDash\Model\BuildGroupRule;
use Illuminate\Support\Facades\Log;

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

        $this->PDO = Database::getInstance();
    }

    /** Get the id */
    public function GetId(): int
    {
        return intval($this->Id);
    }

    /** Set the id.  Also loads remaining data for this
     * buildgroup from the database.
     **/
    public function SetId($id): bool
    {
        if (!is_numeric($id)) {
            return false;
        }

        $this->Id = intval($id);

        $row = $this->PDO->executePreparedSingleRow('SELECT * FROM buildgroup WHERE id=?', [$this->Id]);
        if (empty($row)) {
            return false;
        }

        $this->FillFromRow($row);
        return true;
    }

    /** Get the Name of the buildgroup */
    public function GetName(): string|false
    {
        if (strlen($this->Name) > 0) {
            return $this->Name;
        }

        if ($this->Id < 1) {
            add_log('BuildGroup GetName(): Id not set', 'GetName', LOG_ERR);
            return false;
        }

        $project = $this->PDO->executePreparedSingleRow('SELECT name FROM buildgroup WHERE id=?', [$this->Id]);
        if (!$project) {
            add_last_sql_error('BuildGroup GetName');
            return false;
        }
        $this->Name = $project['name'];
        return $this->Name;
    }

    /** Set the Name of the buildgroup. */
    public function SetName($name): void
    {
        $this->Name = $name ?? '';
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
    public function SetProjectId($projectid): bool
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

    public function SetAutoRemoveTimeFrame($timeframe): bool
    {
        if (!is_numeric($timeframe)) {
            return false;
        }
        $this->AutoRemoveTimeFrame = $timeframe;
        return true;
    }

    /** Get/Set the description */
    public function GetDescription(): string|false
    {
        if ($this->Id < 1) {
            add_log('BuildGroup GetDescription(): Id not set', 'GetDescription', LOG_ERR);
            return false;
        }
        return $this->Description;
    }

    public function SetDescription(string $desc): void
    {
        $this->Description = $desc;
    }

    /** Get/Set the email settings for this BuildGroup.
     * 0: project default settings
     * 1: summary email
     * 2: no email
     **/
    public function GetSummaryEmail()
    {
        if ($this->Id < 1) {
            Log::error("BuildGroup GetSummaryEmail(): Id not set");
            return false;
        }
        return $this->SummaryEmail;
    }

    public function SetSummaryEmail($email): bool
    {
        if (!is_numeric($email)) {
            return false;
        }
        $this->SummaryEmail = $email;
        return true;
    }

    /** Get/Set whether or not this group should include subproject total. */
    public function GetIncludeSubProjectTotal(): int|false
    {
        if ($this->Id < 1) {
            add_log('BuildGroup GetIncludeSubProjectTotal(): Id not set', 'GetIncludeSubProjectTotal', LOG_ERR);
            return false;
        }
        return intval($this->IncludeSubProjectTotal);
    }

    public function SetIncludeSubProjectTotal($b): void
    {
        if ($b) {
            $this->IncludeSubProjectTotal = 1;
        } else {
            $this->IncludeSubProjectTotal = 0;
        }
    }

    /**
     * Returns true if the current BuildGroup is configured to email actionable builds items
     * to email addresses belonging to those persons who executed the commit (vs. the acutal
     * author).
     */
    public function isNotifyingCommitters(): bool
    {
        // TODO: (williamjallen) is the double negation here intentional?
        return !!$this->GetEmailCommitters();
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

    public function SetType($type): void
    {
        $this->Type = $type ?? '';
    }

    /** Populate the ivars of an existing buildgroup.
     * Called automatically once name & projectid are set.
     **/
    public function Fill(): bool
    {
        if ($this->Name == '' || $this->ProjectId == 0) {
            add_log(
                "Name='" . $this->Name . "' or ProjectId='" . $this->ProjectId . "' not set",
                'BuildGroup::Fill',
                LOG_WARNING);
            return false;
        }

        $db = Database::getInstance();

        $row = $db->executePreparedSingleRow('
                   SELECT *
                   FROM buildgroup
                   WHERE
                       projectid=?
                       AND name=?
               ', [intval($this->ProjectId), $this->Name]);

        if (empty($row)) {
            return false;
        }

        $this->FillFromRow($row);
        return true;
    }

    /** Helper function for filling in a buildgroup instance */
    public function FillFromRow($row): void
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
    public function GetPosition(): int|false
    {
        if ($this->Position > 0) {
            return intval($this->Position);
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

        $this->Position = intval($position);
        return $this->Position;
    }

    /** Get the next position available for that group */
    public function GetNextPosition(): int
    {
        $query = $this->PDO->executePreparedSingleRow("
                     SELECT bg.position
                     FROM buildgroupposition AS bg, buildgroup AS g
                     WHERE
                         bg.buildgroupid=g.id
                         AND g.projectid=?
                          AND bg.endtime='1980-01-01 00:00:00'
                     ORDER BY bg.position DESC
                     LIMIT 1
                 ", [$this->ProjectId]);
        if (!empty($query)) {
            return intval($query['position']) + 1;
        }
        return 1;
    }

    /** Check if the group already exists */
    public function Exists(): bool
    {
        // If no id specify return false
        if (!$this->Id || !$this->ProjectId) {
            return false;
        }

        $query = $this->PDO->executePreparedSingleRow('
                     SELECT count(*) AS c
                     FROM buildgroup
                     WHERE id=? AND projectid=?
                 ', [$this->Id, $this->ProjectId]);
        add_last_sql_error('BuildGroup:Exists', $this->ProjectId);
        if (!$query || intval($query['c']) === 0) {
            return false;
        }
        return true;
    }

    /** Save the group */
    public function Save(): bool
    {
        if ($this->Exists()) {
            // Update the project
            $query = $this->PDO->executePrepared('
                         UPDATE buildgroup
                         SET
                             name=?,
                             projectid=?,
                             starttime=?,
                             endtime=?,
                             autoremovetimeframe=?,
                             description=?,
                             summaryemail=?,
                             includesubprojectotal=?,
                             emailcommitters=?,
                             type=?
                         WHERE id=?
                     ', [
                         $this->Name,
                         $this->ProjectId,
                         $this->StartTime,
                         $this->EndTime,
                         $this->AutoRemoveTimeFrame,
                         $this->Description,
                         $this->SummaryEmail,
                         $this->IncludeSubProjectTotal,
                         $this->EmailCommitters,
                         $this->Type,
                         $this->Id
                     ]);

            if ($query === false) {
                add_last_sql_error('BuildGroup:Update', $this->ProjectId);
                return false;
            }
        } else {
            $id = '';
            $values = [];
            if ($this->Id > 0) {
                $id = 'id,';
                $values[] = $this->Id;
            }
            $values = array_merge($values, [
                $this->Name,
                $this->ProjectId,
                $this->StartTime,
                $this->EndTime,
                $this->AutoRemoveTimeFrame,
                $this->Description,
                $this->SummaryEmail,
                $this->IncludeSubProjectTotal,
                $this->EmailCommitters,
                $this->Type
            ]);

            $prepared_array = $this->PDO->createPreparedArray(count($values));
            $query = $this->PDO->executePrepared("
                         INSERT INTO buildgroup (
                              $id
                              name,
                              projectid,
                              starttime,
                              endtime,
                              autoremovetimeframe,
                              description,
                              summaryemail,
                              includesubprojectotal,
                              emailcommitters,
                              type
                         )
                         VALUES $prepared_array
                     ", $values);

            if ($query === false) {
                add_last_sql_error('Buildgroup Insert', $this->ProjectId);
                return false;
            }

            if (!$this->Id) {
                $this->Id = pdo_insert_id('buildgroup');
            }

            // Insert the default position for this group
            // Find the position for this group
            $position = $this->GetNextPosition();
            $this->PDO->executePrepared('
                INSERT INTO buildgroupposition
                    (buildgroupid, position, starttime, endtime)
                VALUES
                    (?, ?, ?, ?)
            ', [$this->Id, $position, $this->StartTime, $this->EndTime]);
        }
        return true;
    }

    /** Delete this BuildGroup. */
    public function Delete(): bool
    {
        if (!$this->Exists()) {
            return false;
        }

        // We delete all the build2grouprule associated with the group
        $this->PDO->executePrepared('DELETE FROM build2grouprule WHERE groupid=?', [$this->Id]);

        // We delete the buildgroup
        $this->PDO->executePrepared('DELETE FROM buildgroup WHERE id=?', [$this->Id]);

        // Restore the builds that were associated with this group
        $oldbuilds = $this->PDO->executePrepared('
                         SELECT id, type
                         FROM build
                         WHERE id IN (
                             SELECT buildid AS id
                             FROM build2group
                             WHERE groupid=?
                         )
                     ', [$this->Id]);
        echo pdo_error();

        foreach ($oldbuilds as $oldbuilds_array) {
            // Move the builds
            $buildid = $oldbuilds_array['id'];
            $buildtype = $oldbuilds_array['type'];

            // Find the group corresponding to the build type
            $query = $this->PDO->executePrepared('
                         SELECT id
                         FROM buildgroup
                         WHERE name=? AND projectid=?
                     ', [$buildtype, $this->ProjectId]);

            if (empty($query)) {
                $query = $this->PDO->executePrepared("
                             SELECT id
                             FROM buildgroup
                             WHERE name='Experimental' AND projectid=?
                         ", [$this->ProjectId]);
            }
            echo pdo_error();
            $grouptype = $query['id'];

            $this->PDO->executePrepared('
                UPDATE build2group
                SET groupid=?
                WHERE buildid=?
            ', [$grouptype, $buildid]);
            echo pdo_error();
        }

        // Delete the buildgroupposition and update the position
        // of the other groups.
        $this->PDO->executePrepared('DELETE FROM buildgroupposition WHERE buildgroupid=?', [$this->Id]);
        $buildgroupposition = $this->PDO->executePrepared('
                                  SELECT bg.buildgroupid
                                  FROM buildgroupposition AS bg, buildgroup AS g
                                  WHERE g.projectid=? AND bg.buildgroupid=g.id
                                  ORDER BY bg.position ASC
                              ', [strval($this->ProjectId)]);

        $p = 1;
        foreach ($buildgroupposition as $buildgroupposition_array) {
            // TODO: (williamjallen) Refactor this to make a constant number of queries
            $buildgroupid = $buildgroupposition_array['buildgroupid'];
            $this->PDO->executePrepared('
                UPDATE buildgroupposition
                SET position=?
                WHERE buildgroupid=?
            ', [$p, $buildgroupid]);
            $p++;
        }

        return true;
    }

    public function GetGroupIdFromRule($build): int
    {
        $name = $build->Name;
        $type = $build->Type;
        $siteid = $build->SiteId;
        $starttime = $build->StartTime;
        $projectid = $build->ProjectId;

        // Insert the build into the proper group
        // 1) Check if we have any build2grouprules for this build
        $rule_row = DB::table('build2grouprule')
            ->join('buildgroup', 'buildgroup.id', '=', 'build2grouprule.groupid')
            ->where('buildgroup.projectid', '=', $projectid)
            ->where('build2grouprule.buildtype', '=', $type)
            ->where('build2grouprule.siteid', '=', $siteid)
            ->where('build2grouprule.buildname', '=', $name)
            ->where('build2grouprule.starttime', '<', $starttime)
            ->where(function ($query) use ($starttime) {
                $query->where('build2grouprule.endtime', '=', '1980-01-01 00:00:00')
                      ->orWhere('build2grouprule.endtime', '>', $starttime);
            })->first();
        if ($rule_row) {
            return intval($rule_row->groupid);
        }

        // 2) Check for buildname-based groups
        $name_rule_row = DB::table('build2grouprule')
            ->join('buildgroup', 'buildgroup.id', '=', 'build2grouprule.groupid')
            ->where('buildgroup.projectid', '=', $projectid)
            ->where('build2grouprule.buildtype', '=', $type)
            ->where('build2grouprule.siteid', '=', -1)
            ->whereRaw("'$name' LIKE build2grouprule.buildname")
            ->where('build2grouprule.starttime', '<', $starttime)
            ->where(function ($query) use ($starttime) {
                $query->where('build2grouprule.endtime', '=', '1980-01-01 00:00:00')
                      ->orWhere('build2grouprule.endtime', '>', $starttime);
            })
            ->orderByRaw('LENGTH(build2grouprule.buildname) DESC')
            ->first();
        if ($name_rule_row) {
            return intval($name_rule_row->groupid);
        }

        // If we reach this far, none of the rules matched.
        // Just use the default group for the build type.
        $default_rule_row = DB::table('buildgroup')
            ->where('name', '=', $type)
            ->where('projectid', '=', $projectid)
            ->first();
        if ($default_rule_row) {
            return intval($default_rule_row->id);
        }

        // If the group does not exist we assign it to Experimental.
        $experimental_rule_row = DB::table('buildgroup')
            ->where('name', '=', 'Experimental')
            ->where('projectid', '=', $projectid)
            ->first();
        if ($experimental_rule_row) {
            return intval($experimental_rule_row->id);
        }
        return 0;
    }

    // Return an array of currently active BuildGroups
    // given a projectid and a starting datetime string.
    public static function GetBuildGroups($projectid, $begin): array
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

    // Get the active rules for this build group.
    public function GetRules(): array|false
    {
        $stmt = $this->PDO->prepare("
                SELECT * FROM build2grouprule
                WHERE groupid = :groupid AND
                      endtime = '1980-01-01 00:00:00'");
        if (!$this->PDO->execute($stmt, [':groupid' => $this->Id])) {
            return false;
        }
        $rules = [];
        while ($row = $stmt->fetch()) {
            $rule = new BuildGroupRule();
            $rule->FillFromRow($row, $this->ProjectId);
            $rules[] = $rule;
        }
        return $rules;
    }
}
