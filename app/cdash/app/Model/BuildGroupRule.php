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

use App\Models\BuildGroup;
use App\Models\BuildGroupRule as EloquentBuildGroupRule;
use CDash\Database;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class BuildGroupRule
{
    public $BuildName = '';
    public $BuildType = '';
    public $EndTime = '1980-01-01 00:00:00';
    public $Expected = 0;
    public $GroupId = 0;
    public $ParentGroupId = 0;
    public $ProjectId = 0;
    public $SiteId = 0;
    public $StartTime = '1980-01-01 00:00:00';

    public function __construct(?Build $build = null)
    {
        if ($build !== null) {
            $this->BuildType = $build->Type;
            $this->BuildName = $build->Name;
            $this->SiteId = $build->SiteId;
            $this->GroupId = $build->GroupId;
            $this->ProjectId = $build->ProjectId;
        }
    }

    /** Check if the rule already exists */
    public function Exists(): bool
    {
        // If no group id specified return false.
        if (!$this->GroupId) {
            return false;
        }

        return EloquentBuildGroupRule::where([
            'groupid' => $this->GroupId,
            'parentgroupid' => $this->ParentGroupId,
            'buildtype' => (string) $this->BuildType,
            'buildname' => $this->BuildName,
            'siteid' => $this->SiteId,
            'endtime' => $this->EndTime,
        ])->exists();
    }

    /** Insert this rule into the database. */
    public function Save(): bool
    {
        if (!$this->GroupId) {
            Log::error('GroupId not set', [
                'function' => 'BuildGroupRule::Save',
            ]);
            return false;
        }

        if (!$this->Exists()) {
            EloquentBuildGroupRule::create([
                'groupid' => $this->GroupId,
                'parentgroupid' => $this->ParentGroupId,
                'buildtype' => (string) $this->BuildType,
                'buildname' => $this->BuildName,
                'siteid' => $this->SiteId,
                'expected' => $this->Expected,
                'starttime' => $this->StartTime,
                'endtime' => $this->EndTime,
            ]);

            return true;
        }
        return false;
    }

    public function SetExpected(): bool
    {
        // Insert a new row if one doesn't already exist for this rule.
        if (!$this->Exists()) {
            return $this->Save();
        }

        // Otherwise update an existing row.
        EloquentBuildGroupRule::where([
            'groupid' => $this->GroupId,
            // Are we missing parentgroupid?
            'buildtype' => (string) $this->BuildType,
            'buildname' => $this->BuildName,
            'siteid' => $this->SiteId,
            'endtime' => '1980-01-01 00:00:00',
        ])->update([
            'expected' => $this->Expected,
        ]);

        return true;
    }

    public function GetExpected(): int
    {
        return EloquentBuildGroupRule::firstWhere([
            'groupid' => $this->GroupId,
            // Are we missing parentgroupid?
            'buildtype' => (string) $this->BuildType,
            'buildname' => $this->BuildName,
            'siteid' => $this->SiteId,
            'endtime' => '1980-01-01 00:00:00',
        ])->expected ?? 0;
    }

    /** Delete a rule */
    public function Delete($soft = true): void
    {
        if ($soft) {
            $this->SoftDelete();
        } else {
            $this->HardDelete();
        }
    }

    /** Soft delete (mark a build rule as finished). */
    private function SoftDelete(): void
    {
        EloquentBuildGroupRule::where([
            'groupid' => $this->GroupId,
            'parentgroupid' => $this->ParentGroupId,
            'buildtype' => (string) $this->BuildType,
            'buildname' => $this->BuildName,
            'siteid' => $this->SiteId,
            'endtime' => '1980-01-01 00:00:00',
        ])->update([
            'endtime' => Carbon::now(),
        ]);
    }

    /** Hard delete (remove a build rule from the database). */
    private function HardDelete(): void
    {
        EloquentBuildGroupRule::where([
            'groupid' => $this->GroupId,
            'parentgroupid' => $this->ParentGroupId,
            'buildtype' => (string) $this->BuildType,
            'buildname' => $this->BuildName,
            'siteid' => $this->SiteId,
            'expected' => $this->Expected,
            'starttime' => $this->StartTime,
            'endtime' => $this->EndTime,
        ])->delete();
    }

    /** Soft delete all active previous versions of this rule. */
    public function SoftDeleteExpiredRules($now): void
    {
        $groupids = BuildGroup::where('projectid', (int) $this->ProjectId)
            ->pluck('id')
            ->toArray();
        EloquentBuildGroupRule::whereIn('groupid', $groupids)
            ->where([
                'buildtype' => $this->BuildType,
                'buildname' => $this->BuildName,
                'siteid' => $this->SiteId,
                'endtime' => '1980-01-01 00:00:00',
            ])->update([
                'endtime' => $now,
            ]);
    }

    /** Change the group that this rule points to. */
    public function ChangeGroup($newgroupid): void
    {
        EloquentBuildGroupRule::where([
            'groupid' => $this->GroupId,
            'buildtype' => $this->BuildType,
            'buildname' => $this->BuildName,
            'siteid' => $this->SiteId,
            'endtime' => '1980-01-01 00:00:00',
        ])->update([
            'groupid' => $newgroupid,
        ]);

        $query_params = [
            ':newgroupid' => $newgroupid,
            ':groupid' => $this->GroupId,
            ':buildtype' => $this->BuildType,
            ':buildname' => $this->BuildName,
            ':siteid' => $this->SiteId];

        $PDO = Database::getInstance();

        // Move any builds that follow this rule to the new group.
        $stmt = $PDO->prepare(
            'UPDATE build2group SET groupid = :newgroupid
            WHERE groupid = :groupid AND
                  buildid IN
                  (SELECT id FROM build WHERE siteid = :siteid    AND
                                              name   = :buildname AND
                                              type   = :buildtype)');
        $PDO->execute($stmt, $query_params);
    }

    public static function DeleteExpiredRulesForProject($projectid, $cutoff_date): void
    {
        $groupids = BuildGroup::where('projectid', (int) $projectid)
            ->pluck('id')
            ->toArray();
        EloquentBuildGroupRule::whereIn('groupid', $groupids)
            ->where('endtime', '!=', '1980-01-01 00:00:00')
            ->where('endtime', '<', $cutoff_date)
            ->delete();
    }
}
