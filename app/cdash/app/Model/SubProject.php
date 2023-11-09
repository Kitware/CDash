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

use App\Models\Project as EloquentProject;
use App\Models\SubProject as EloquentSubProject;
use CDash\Database;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Main subproject class */
class SubProject
{
    private string $Name = '';
    private int $Id = 0;
    private int $ProjectId = 0;
    private int $GroupId = 0;
    private string $Path = '';
    private int $Position = 0;

    /** Function to get the id */
    public function GetId(): int
    {
        return $this->Id;
    }

    /**
     * Function to set the id.  Also loads remaining data for this
     * subproject from the database.
     */
    public function SetId($id): void
    {
        if (!is_numeric($id)) {
            abort(500, 'Invalid SubProject ID.');
        }

        $this->Id = (int) $id;

        $subproject = EloquentSubProject::where('endtime', Carbon::create(1980))->find($id);

        if ($subproject === null) {
            return;
        }

        $this->Name = $subproject->name;
        $this->ProjectId = $subproject->projectid;
        $this->GroupId = $subproject->groupid;
        $this->Path = $subproject->path;
        $this->Position = $subproject->position;
    }

    /** Function to set the project id */
    public function SetProjectId($projectid): void
    {
        if (!is_numeric($projectid)) {
            abort(500, 'Invalid SubProject ID.');
        }

        $this->ProjectId = (int) $projectid;
        if ($this->Name != '') {
            $this->Fill();
        }
    }

    /** Delete a subproject */
    public function Delete($keephistory = true): void
    {
        if ($this->Id === 0) {
            abort(500, 'SubProject ID not set.');
        }

        // If there is no build in the subproject we remove
        $count = (int) DB::select('
            SELECT count(*) AS c
            FROM subproject2build
            WHERE subprojectid = ?
        ', [$this->Id])[0]->c;
        if ($count === 0) {
            $keephistory = false;
        }

        $subproject = EloquentSubProject::findOrFail($this->Id);

        // Regardless of whether or not we're performing a "soft delete",
        // we should remove any dependencies on this subproject.
        DB::delete('DELETE FROM subproject2subproject WHERE dependsonid=?', [intval($this->Id)]);

        if (!$keephistory) {
            DB::delete('DELETE FROM subproject2build WHERE subprojectid=?', [intval($this->Id)]);
            $subproject->children()->detach();
            $subproject->delete();
        } else {
            $subproject->update([
                'endtime' => Carbon::now()->setTimezone('UTC'),
            ]);
        }
    }

    /** Return if a subproject exists */
    public function Exists(): bool
    {
        // If no id specify return false
        if ($this->Id === 0) {
            return false;
        }

        return EloquentSubProject::where([
            'id' => $this->Id,
            'endtime' => Carbon::create(1980),
        ])->exists();
    }

    /**
     * Save the subproject in the database
     */
    public function Save(): void
    {
        // Assign it to the default group if necessary.
        if ($this->GroupId === 0) {
            $groupid_query = DB::select('
                SELECT id
                FROM subprojectgroup
                WHERE
                    projectid = ?
                    AND is_default = 1
            ', [$this->ProjectId])[0] ?? [];
            if ($groupid_query !== []) {
                $this->GroupId = (int) $groupid_query->id;
            }
        }

        // Trim the name.
        $this->Name = trim($this->Name);

        $subproject = EloquentSubProject::updateOrCreate(
            [
                'name' => $this->Name,
                'projectid' => $this->ProjectId,
                'endtime' => Carbon::create(1980),
            ], [
                // These attributes should always be updated
                'groupid' => $this->GroupId,
                'position' => $this->Position,
                'path' => $this->Path,
            ]
        );

        if ($subproject->wasRecentlyCreated) {
            $subproject->save([
                // We only set the start time if this is a new subproject
                'starttime' => Carbon::now()->setTimezone('UTC'),
            ]);
        }

        $this->Id = $subproject->id;
    }

    /** Get the Name of the subproject */
    public function GetName(): string
    {
        if ($this->Name !== '') {
            return $this->Name;
        }

        if ($this->Id === 0) {
            abort(500, 'SubProject ID not set.');
        }

        // Sets the other properties too...
        $this->SetId($this->Id);

        return $this->Name;
    }

    /** Set the Name of the subproject. */
    public function SetName(string $name): void
    {
        $this->Name = $name;
        if ($this->ProjectId > 0) {
            $this->Fill();
        }
    }

    /**
     * Populate the ivars of an existing subproject.
     * Called automatically once name & projectid are set.
     */
    public function Fill(): void
    {
        if ($this->Name === '' || $this->ProjectId === 0) {
            abort(500, "Name='" . $this->Name . "' or ProjectId='" . $this->ProjectId . "' not set");
        }

        /** @var EloquentSubProject|null $subproject */
        $subproject = EloquentProject::findOrFail($this->ProjectId)
            ->subprojects()
            ->where(['name' => $this->Name])
            ->first();

        if ($subproject === null) {
            return;
        }

        $this->Id = $subproject->id;
        $this->GroupId = $subproject->groupid;
        $this->Path = $subproject->path;
        $this->Position = $subproject->position;
    }

    /** Get the group that this subproject belongs to. */
    public function GetGroupId(): int
    {
        if ($this->Id === 0) {
            abort(500, 'SubProject ID not set.');
        }

        // Use the cached value if it's already set...
        if ($this->GroupId > 0) {
            return $this->GroupId;
        }

        // This fills the model for us...
        $this->SetId($this->Id);

        return $this->GroupId;
    }

    /** Function to set this subproject's group. */
    public function SetGroup(string $groupName): bool
    {
        $db = Database::getInstance();
        $row = $db->executePreparedSingleRow("
                   SELECT id
                   FROM subprojectgroup
                   WHERE
                       name=?
                       AND endtime='1980-01-01 00:00:00'
               ", [$groupName]);

        if (empty($row)) {
            // Create the group if it doesn't exist yet.
            $subprojectGroup = new SubProjectGroup();
            $subprojectGroup->SetName($groupName);
            $subprojectGroup->SetProjectId($this->ProjectId);
            if ($subprojectGroup->Save() === false) {
                return false;
            }
            $this->GroupId = $subprojectGroup->GetId();
            return true;
        }
        $this->GroupId = intval($row['id']);
        return true;
    }

    /** Get/Set this SubProject's path. */
    public function GetPath(): string
    {
        return $this->Path;
    }

    public function SetPath(string $path): void
    {
        $this->Path = $path;
    }

    /** Get/Set this SubProject's position. */
    public function GetPosition(): int
    {
        return $this->Position;
    }

    public function SetPosition(int $position): void
    {
        $this->Position = $position;
    }

    /** Get the last submission of the subproject*/
    public function GetLastSubmission(): string|false
    {
        if (!config('cdash.show_last_submission')) {
            return false;
        }

        if ($this->Id < 1) {
            return false;
        }

        // This query is overconstrained, but the extra constraints greatly improve query performance in practice
        $project = DB::select('
            SELECT max(build.starttime) as starttime
            FROM
                build,
                subproject2build,
                build2group,
                buildgroup,
                subproject,
                project
            WHERE
                subproject.id = ?
                AND subproject.projectid = project.id
                AND build.projectid = project.id
                AND subproject2build.subprojectid = subproject.id
                AND subproject2build.buildid = build.id
                AND build2group.buildid = build.id
                AND build2group.groupid = buildgroup.id
                AND buildgroup.includesubprojectotal = 1
                AND buildgroup.projectid = project.id
        ', [$this->Id]);

        if ($project === []) {
            return false;
        }

        return date(FMT_DATETIMESTD, strtotime($project[0]->starttime . 'UTC'));
    }

    /**
     * Encapsulate common logic for build queries in this class.
     *
     * Use caution when calling this function.  The $extraCriteria argument will be inserted
     * directly into the SQL, which potentially leaves us open to SQL injection if user-controllable
     * variables are inserted into the query string.
     */
    public function CommonBuildQuery($startUTCdate, $endUTCdate, bool $allSubProjects): array|false
    {
        if (!$allSubProjects && $this->Id === 0) {
            return false;
        }

        $extraSelect = '';
        $extraWhere = '';
        $params = [];
        if ($allSubProjects) {
            $extraSelect = 'subprojectid, ';
        } else {
            $extraWhere = 'subprojectid = ? AND ';
            $params[] = $this->Id;
        }

        $query = "
            SELECT
                $extraSelect
                SUM(CASE WHEN b.configurewarnings > 0 THEN 1 END) AS nconfigurewarnings,
                SUM(CASE WHEN b.configureerrors > 0 THEN 1 END) AS nconfigureerrors,
                SUM(CASE WHEN b.configureerrors = 0 AND b.configurewarnings = 0 THEN 1 END) AS npassingconfigures,
                SUM(CASE WHEN b.buildwarnings > 0 THEN 1 END) AS nbuildwarnings,
                SUM(CASE WHEN b.builderrors > 0 THEN 1 END) AS nbuilderrors,
                SUM(CASE WHEN b.builderrors = 0 AND b.buildwarnings = 0 THEN 1 END) AS npassingbuilds,
                SUM(CASE WHEN b.testpassed > 0 THEN b.testpassed END) AS ntestspassed,
                SUM(CASE WHEN b.testfailed > 0 THEN b.testfailed END) AS ntestsfailed,
                SUM(CASE WHEN b.testnotrun > 0 THEN b.testnotrun END) AS ntestsnotrun
            FROM build b
            JOIN build2group b2g ON (b2g.buildid = b.id)
            JOIN buildgroup bg ON (bg.id = b2g.groupid)
            JOIN subproject2build sp2b ON (sp2b.buildid = b.id)
            WHERE
                $extraWhere
                b.projectid = ? AND
                b.starttime > ? AND
                b.starttime <= ? AND
                bg.includesubprojectotal = 1";
        $params = array_merge($params, [intval($this->ProjectId), $startUTCdate, $endUTCdate]);
        if ($allSubProjects) {
            $query .= ' GROUP BY subprojectid';
        }

        $db = Database::getInstance();
        $project = $db->executePrepared($query, $params);

        if ($project === false) {
            add_last_sql_error("SubProject CommonBuildQuery");
            return false;
        }
        if ($allSubProjects) {
            $project_array = [];
            foreach ($project as $row) {
                $project_array[intval($row['subprojectid'])] = $row;
            }
            return $project_array;
        } else {
            return $project[0];
        }
    }

    /**
     * Get the subprojectids of the subprojects depending on this one.
     *
     * TODO: This should return a list of subprojects instead of a list of IDs.
     *
     * @return array<int>
     */
    public function GetDependencies(?string $date = null): array
    {
        if ($this->Id === 0) {
            abort(500, "SubProject ID not set.");
        }

        // If not set, the date is now
        if ($date !== null) {
            $date = Carbon::parse($date);
        }

        $subprojects = EloquentSubProject::findOrFail($this->Id)
            ->children($date)
            ->get();

        $ids = [];
        /** @var EloquentSubProject $subproject */
        foreach ($subprojects as $subproject) {
            $ids[] = $subproject->id;
        }
        return $ids;
    }

    /** Add a dependency */
    public function AddDependency(int $subprojectid): void
    {
        if ($this->Id === 0) {
            abort(500, "SubProject ID not set.");
        }

        // If the dependency already exists, exit early
        $dependency_exists = EloquentSubProject::findOrFail($this->Id)
            ->children()
            ->where('id', $subprojectid)
            ->exists();
        if ($dependency_exists) {
            return;
        }

        // Add the dependency
        EloquentSubProject::findOrFail($this->Id)
            ->children()
            ->attach($subprojectid, [
                'starttime' => Carbon::now()->setTimezone('UTC'),
                'endtime' => Carbon::create(1980),
            ]);
    }

    /** Remove a dependency */
    public function RemoveDependency(int $subprojectid): void
    {
        if ($this->Id === 0) {
            abort(500, 'SubProject ID not set.');
        }

        EloquentSubProject::findOrFail($this->Id)
            ->children()
            ->updateExistingPivot($subprojectid, [
                'endtime' => Carbon::now()->setTimezone('UTC'),
            ]);
    }

    /**
     * Return a subproject object for a given file path and projectid.
     *
     * TODO: Move this somewhere else...
     */
    public static function GetSubProjectFromPath(string $path, int $projectid): SubProject|null
    {
        $query = DB::select("
            SELECT id
            FROM subproject
            WHERE
                projectid = ?
                AND endtime = '1980-01-01 00:00:00'
                AND path != ''
                AND ? LIKE CONCAT('%', path, '%')
        ", [$projectid, $path]);

        if ($query === []) {
            add_log(
                "No SubProject found for '$path'", 'GetSubProjectFromPath',
                LOG_INFO, $projectid, 0);
            return null;
        }
        $subproject = new SubProject();
        $subproject->SetId((int) $query[0]->id);
        return $subproject;
    }
}
