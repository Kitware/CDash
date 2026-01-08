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

use App\Models\BuildGroup as EloquentBuildGroup;
use CDash\Database;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BuildGroup
{
    public const NIGHTLY = 'Nightly';
    public const EXPERIMENTAL = 'Experimental';

    private int $Position = 0;
    /** @var EloquentBuildGroup */
    private $eloquent_model;

    public function __construct()
    {
        $this->eloquent_model = new EloquentBuildGroup([
            'projectid' => 0,
            'name' => '',
            'starttime' => Carbon::create(1980),
            'endtime' => Carbon::create(1980),
            'description' => '',
            'summaryemail' => 0,
            'type' => 'Daily',
            'includesubprojectotal' => 1,
            'emailcommitters' => 0,
        ]);
    }

    /** Get the id */
    public function GetId(): int
    {
        return $this->eloquent_model->id ?? 0;
    }

    /**
     * Set the id.  Also loads remaining data for this
     * buildgroup from the database.
     */
    public function SetId($id): bool
    {
        if (!is_numeric($id)) {
            return false;
        }

        $model = EloquentBuildGroup::find((int) $id);
        if ($model === null) {
            return false;
        }

        $this->eloquent_model = $model;

        return true;
    }

    /** Get the Name of the buildgroup */
    public function GetName(): string|false
    {
        if (strlen($this->eloquent_model->name) > 0) {
            return $this->eloquent_model->name;
        }

        if (!isset($this->eloquent_model->id)) {
            Log::error('BuildGroup GetName(): Id not set');
            return false;
        }

        return $this->eloquent_model->name;
    }

    /** Set the Name of the buildgroup. */
    public function SetName(string $name): void
    {
        $this->eloquent_model->name = $name;
        if ($this->eloquent_model->projectid > 0) {
            $this->Fill();
        }
    }

    /** Get the project id */
    public function GetProjectId(): int
    {
        return $this->eloquent_model->projectid;
    }

    /** Set the project id */
    public function SetProjectId($projectid): bool
    {
        if (!is_numeric($projectid)) {
            return false;
        }

        $this->eloquent_model->projectid = (int) $projectid;
        if (strlen($this->eloquent_model->name) > 0) {
            $this->Fill();
        }
        return true;
    }

    /** Get/Set the start time */
    public function GetStartTime(): Carbon|false
    {
        if (!isset($this->eloquent_model->id)) {
            Log::error('BuildGroup GetStartTime(): Id not set');
            return false;
        }
        return $this->eloquent_model->starttime;
    }

    /** Get/Set the autoremove timeframe */
    public function GetAutoRemoveTimeFrame(): int|false
    {
        if ($this->eloquent_model->autoremovetimeframe === null) {
            Log::error('BuildGroup GetAutoRemoveTimeFrame(): property not set.');
            return false;
        }
        return $this->eloquent_model->autoremovetimeframe;
    }

    public function SetAutoRemoveTimeFrame(int $timeframe): void
    {
        $this->eloquent_model->autoremovetimeframe = $timeframe;
    }

    /** Get/Set the description */
    public function GetDescription(): string|false|null
    {
        if (!isset($this->eloquent_model->id)) {
            Log::error('BuildGroup GetDescription(): Id not set');
            return false;
        }
        return $this->eloquent_model->description;
    }

    public function SetDescription(string $description): void
    {
        $this->eloquent_model->description = $description;
    }

    /** Get/Set the email settings for this BuildGroup.
     * 0: project default settings
     * 1: summary email
     * 2: no email
     **/
    public function GetSummaryEmail()
    {
        if (!isset($this->eloquent_model->id)) {
            Log::error('BuildGroup GetSummaryEmail(): Id not set');
            return false;
        }
        return $this->eloquent_model->summaryemail;
    }

    public function SetSummaryEmail(int $email): bool
    {
        if ($email < 0 || $email > 2) {
            return false;
        }
        $this->eloquent_model->summaryemail = $email;
        return true;
    }

    /** Get/Set whether or not this group should include subproject total. */
    public function GetIncludeSubProjectTotal(): int|false
    {
        if (!isset($this->eloquent_model->id)) {
            Log::error('BuildGroup GetIncludeSubProjectTotal(): Id not set');
            return false;
        }
        return $this->eloquent_model->includesubprojectotal;
    }

    public function SetIncludeSubProjectTotal(int $b): void
    {
        $this->eloquent_model->includesubprojectotal = $b > 0 ? 1 : 0;
    }

    /**
     * Returns true if the current BuildGroup is configured to email actionable builds items
     * to email addresses belonging to those persons who executed the commit (vs. the acutal
     * author).
     */
    public function isNotifyingCommitters(): bool
    {
        return (bool) $this->GetEmailCommitters();
    }

    /** Get/Set whether or not committers should be emailed for this group. */
    public function GetEmailCommitters(): int|false
    {
        if (!isset($this->eloquent_model->id)) {
            Log::error('BuildGroup GetEmailCommitters(): Id not set');
            return false;
        }
        return $this->eloquent_model->emailcommitters;
    }

    public function SetEmailCommitters($b): void
    {
        $this->eloquent_model->emailcommitters = $b ? 1 : 0;
    }

    /** Get/Set the type */
    public function GetType(): string|false
    {
        if (!isset($this->eloquent_model->id)) {
            Log::error('BuildGroup GetType(): Id not set');
            return false;
        }
        return $this->eloquent_model->type;
    }

    public function SetType(string $type): void
    {
        $this->eloquent_model->type = $type;
    }

    /**
     * Populate the ivars of an existing buildgroup.
     * Called automatically once name & projectid are set.
     */
    private function Fill(): bool
    {
        if (strlen($this->eloquent_model->name) === 0 || $this->eloquent_model->projectid === 0) {
            Log::warning("Name='{$this->eloquent_model->name}' or ProjectId='{$this->eloquent_model->projectid}' not set.");
            return false;
        }

        $model = EloquentBuildGroup::where([
            'projectid' => $this->eloquent_model->projectid,
            'name' => $this->eloquent_model->name,
        ])->first();

        if ($model === null) {
            return false;
        }

        $this->eloquent_model = $model;

        return true;
    }

    /** Get/Set this BuildGroup's position (the order it should appear in) */
    public function GetPosition(): int|false
    {
        if ($this->Position > 0) {
            return $this->Position;
        }

        if (!isset($this->eloquent_model->id)) {
            Log::error('BuildGroup GetPosition(): Id not set');
            return false;
        }

        $position = (int) (DB::select('
            SELECT position FROM buildgroupposition
            WHERE buildgroupid = ?
            ORDER BY position DESC LIMIT 1
        ', [$this->eloquent_model->id])[0]->position ?? -1);

        if ($position === -1) {
            Log::error("BuildGroup GetPosition(): no position found for buildgroup #{$this->eloquent_model->id}!");
            return false;
        }

        $this->Position = $position;
        return $this->Position;
    }

    /** Get the next position available for that group */
    private function GetNextPosition(): int
    {
        return 1 + (int) (DB::select("
            SELECT bg.position
            FROM
                buildgroupposition AS bg,
                buildgroup AS g
            WHERE
                bg.buildgroupid=g.id
                AND g.projectid=?
                AND bg.endtime='1980-01-01 00:00:00'
            ORDER BY bg.position DESC
            LIMIT 1
            ", [$this->eloquent_model->projectid])[0]->position ?? 0);
    }

    /** Check if the group already exists */
    public function Exists(): bool
    {
        return isset($this->eloquent_model->id) && $this->eloquent_model->exists();
    }

    /** Save the group */
    public function Save(): bool
    {
        if ($this->Exists()) {
            $this->eloquent_model->save();
        } else {
            $this->eloquent_model->save();

            // Insert the default position for this group
            // Find the position for this group
            $position = $this->GetNextPosition();
            $this->eloquent_model->positions()->create([
                'position' => $position,
                'starttime' => $this->eloquent_model->starttime,
                'endtime' => $this->eloquent_model->endtime,
            ]);
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
        $this->eloquent_model->rules()->delete();

        // Restore the builds that were associated with this group
        $oldbuilds = $this->eloquent_model->builds()->get();

        /** @var \App\Models\Build $oldbuild */
        foreach ($oldbuilds as $oldbuild) {
            // Find the group corresponding to the build type

            /** @var ?EloquentBuildGroup $newGroup */
            $newGroup = $this->eloquent_model->project?->buildgroups()->where([
                'name' => $oldbuild->type,
            ])->first();

            if ($newGroup === null) {
                $newGroup = $this->eloquent_model->project?->buildgroups()->where([
                    'name' => 'Experimental',
                ])->first();
            }

            $newGroup?->builds()->attach($oldbuild);
        }

        // We delete the buildgroup
        $this->eloquent_model->delete();

        // Delete the buildgroupposition and update the position
        // of the other groups.
        DB::delete('DELETE FROM buildgroupposition WHERE buildgroupid=?', [$this->eloquent_model->id]);
        $buildgroupposition = DB::select('
                                  SELECT bg.buildgroupid
                                  FROM buildgroupposition AS bg, buildgroup AS g
                                  WHERE g.projectid=? AND bg.buildgroupid=g.id
                                  ORDER BY bg.position ASC
                              ', [$this->eloquent_model->projectid]);

        $p = 1;
        foreach ($buildgroupposition as $buildgroupposition_array) {
            // TODO: (williamjallen) Refactor this to make a constant number of queries
            $buildgroupid = $buildgroupposition_array->buildgroupid;
            DB::update('
                UPDATE buildgroupposition
                SET position=?
                WHERE buildgroupid=?
            ', [$p, $buildgroupid]);
            $p++;
        }

        return true;
    }

    public function GetGroupIdFromRule(Build $build): int
    {
        $starttime = $build->StartTime;

        // Insert the build into the proper group
        // 1) Check if we have any build2grouprules for this build
        $rule_row = DB::table('build2grouprule')
            ->join('buildgroup', 'buildgroup.id', '=', 'build2grouprule.groupid')
            ->where('buildgroup.projectid', '=', $build->ProjectId)
            ->where('build2grouprule.buildtype', '=', $build->Type)
            ->where('build2grouprule.siteid', '=', $build->SiteId)
            ->where('build2grouprule.buildname', '=', $build->Name)
            ->where('build2grouprule.starttime', '<', $build->StartTime)
            ->where(function ($query) use ($starttime): void {
                $query->where('build2grouprule.endtime', '=', '1980-01-01 00:00:00')
                      ->orWhere('build2grouprule.endtime', '>', $starttime);
            })->first();
        if ($rule_row !== null) {
            return (int) $rule_row->groupid;
        }

        // 2) Check for buildname-based groups
        $name_rule_row = DB::table('build2grouprule')
            ->join('buildgroup', 'buildgroup.id', '=', 'build2grouprule.groupid')
            ->where('buildgroup.projectid', '=', $build->ProjectId)
            ->where('build2grouprule.buildtype', '=', $build->Type)
            ->where('build2grouprule.siteid', '=', -1)
            ->whereRaw("'{$build->Name}' LIKE build2grouprule.buildname")
            ->where('build2grouprule.starttime', '<', $build->StartTime)
            ->where(function ($query) use ($starttime): void {
                $query->where('build2grouprule.endtime', '=', '1980-01-01 00:00:00')
                      ->orWhere('build2grouprule.endtime', '>', $starttime);
            })
            ->orderByRaw('LENGTH(build2grouprule.buildname) DESC')
            ->first();
        if ($name_rule_row !== null) {
            return (int) $name_rule_row->groupid;
        }

        // If we reach this far, none of the rules matched.
        // Just use the default group for the build type.
        $default_model = EloquentBuildGroup::where([
            'name' => $build->Type,
            'projectid' => $build->ProjectId,
        ])->first();
        if ($default_model !== null) {
            return $default_model->id;
        }

        return EloquentBuildGroup::where([
            'name' => 'Experimental',
            'projectid' => (int) $build->ProjectId,
        ])->first()->id ?? 0;
    }

    /**
     * Return an array of currently active BuildGroups
     * given a projectid and a starting datetime string.
     */
    public static function GetBuildGroups($projectid, $begin): array
    {
        $buildgroups = [];

        $stmt = DB::select("
            SELECT bg.id, bg.name, bgp.position
            FROM buildgroup AS bg
            LEFT JOIN buildgroupposition AS bgp ON (bgp.buildgroupid = bg.id)
            WHERE bg.projectid = ? AND
                  bg.starttime < ? AND
                  (bg.endtime > ? OR bg.endtime='1980-01-01 00:00:00')
        ", [$projectid, $begin, $begin]);

        foreach ($stmt as $row) {
            $buildgroup = new self();
            $buildgroup->SetId((int) $row->id);
            $buildgroup->SetName($row->name);
            // TODO: Clean this up.  Position is a *private* member...
            $buildgroup->Position = (int) $row->position;
            $buildgroups[] = $buildgroup;
        }

        return $buildgroups;
    }

    /** Get the active rules for this build group. */
    public function GetRules(): array|false
    {
        $rules = [];
        /** @var \App\Models\BuildGroupRule $eloquent_rule */
        foreach ($this->eloquent_model->rules()->active()->get() as $eloquent_rule) {
            $rule = new BuildGroupRule();
            $rule->ProjectId = $this->eloquent_model->projectid;
            $rule->BuildName = $eloquent_rule->buildname;
            $rule->BuildType = $eloquent_rule->buildtype;
            $rule->EndTime = $eloquent_rule->endtime;
            $rule->Expected = $eloquent_rule->expected;
            $rule->GroupId = $eloquent_rule->groupid;
            $rule->ParentGroupId = $eloquent_rule->parentgroupid;
            $rule->SiteId = $eloquent_rule->siteid;
            $rule->StartTime = $eloquent_rule->starttime;
            $rules[] = $rule;
        }
        return $rules;
    }
}
