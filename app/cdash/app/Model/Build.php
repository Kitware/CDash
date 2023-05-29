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

include_once 'include/common.php';
include_once 'include/ctestparserutils.php';
include_once 'include/repository.php';

use App\Models\Test;
use App\Services\TestingDay;
use App\Services\TestDiffService;

use CDash\Collection\BuildEmailCollection;
use CDash\Collection\DynamicAnalysisCollection;
use CDash\Config;
use CDash\Database;
use PDO;

class Build
{
    const TYPE_ERROR = 0;
    const TYPE_WARN = 1;
    const STATUS_NEW = 1;

    const PARENT_BUILD = -1;
    const STANDALONE_BUILD = 0;

    public $Id;
    public $SiteId;
    public $ProjectId;
    private $ParentId;
    public $Uuid;
    private $Stamp;
    public $Name;
    public $Type;
    public $Generator;
    public $StartTime;
    public $EndTime;
    public $SubmitTime;
    public $Command;
    public $Log;
    public $Information;
    public $BuildErrorCount;
    public $TestFailedCount;

    // For the moment we accept only one group per build
    public $GroupId;

    public $Errors;
    public $ErrorDiffs;
    public $Failures;
    public $MissingTests;

    public $SubProjectId;
    public $SubProjectName;
    public $Append;
    public $Done;

    // Only the build.xml has information about errors and warnings
    // when the InsertErrors is false the build is created but not the errors and warnings
    public $InsertErrors;

    // Used to comment on pull/merge requests when something goes wrong
    // with this build.
    private $PullRequest;

    // Used to mark whether this object already has its fields set.
    public $Filled;

    // Not set by FillFromId(), but cached the first time they are
    // computed.
    public $BeginningOfDay;
    public $EndOfDay;

    private $TestCollection;
    private $PDO;
    private $Site;
    private $BuildUpdate;
    private $Project;
    private $CommitAuthors;
    private $ActionableType;
    private $BuildConfigure;
    private $LabelCollection;
    private $DynamicAnalysisCollection;
    private $BuildEmailCollection;

    // TODO: ErrorDiffs appears to be no longer used?
    private $ErrorDifferences;

    /**
     * Build constructor.
     */
    public function __construct()
    {
        $this->Append = false;
        $this->Command = '';
        $this->EndTime = '1980-01-01 00:00:00';
        $this->Errors = [];
        $this->ErrorDiffs = [];
        $this->Failures = [];
        $this->Filled = false;
        $this->Generator = '';
        $this->InsertErrors = true;
        $this->Log = '';
        $this->Name = '';
        $this->ParentId = 0;
        $this->ProjectId = 0;
        $this->PullRequest = '';
        $this->SiteId = 0;
        $this->Stamp = '';
        $this->StartTime = '1980-01-01 00:00:00';
        $this->SubmitTime = '1980-01-01 00:00:00';
        $this->Type = '';
        $this->Uuid = '';
        $this->TestCollection = collect();
        $this->CommitAuthors = [];

        $this->LabelCollection = collect();

        $this->PDO = Database::getInstance()->getPdo();
    }

    public function IsParentBuild() : bool
    {
        return $this->ParentId == -1;
    }

    /**
     * @param $error
     */
    public function AddError($error)
    {
        $error->BuildId = $this->Id;
        $this->Errors[] = $error;
    }

    /**
     * @param $label
     */
    public function AddLabel($label)
    {
        $label->BuildId = $this->Id;
        $this->LabelCollection->put($label->Text, $label);
    }

    /**
     * @param $stamp
     */
    public function SetStamp(string $stamp) : void
    {
        $this->Stamp = $stamp;
        if (strlen($this->Type) == 0) {
            $this->Type = extract_type_from_buildstamp($this->Stamp);
        }
    }

    public function GetStamp() : string
    {
        return $this->Stamp;
    }

    /** Set the subproject id */
    public function SetSubProject($subproject)
    {
        if (!empty($this->SubProjectId)) {
            return $this->SubProjectId;
        }

        if (empty($subproject)) {
            return false;
        }

        if (empty($this->ProjectId)) {
            add_log('ProjectId not set' . $subproject, 'Build::SetSubProject', LOG_ERR,
                $this->ProjectId, $this->Id,
                ModelType::BUILD, $this->Id);
            return false;
        }

        $this->SubProjectName = $subproject;

        $stmt = $this->PDO->prepare(
            "SELECT id FROM subproject WHERE name = ? AND projectid = ? AND
            endtime='1980-01-01 00:00:00'");
        if (!pdo_execute($stmt, [$subproject, $this->ProjectId])) {
            return false;
        }

        $label = new Label;
        $label->Text = $subproject;
        $this->AddLabel($label);

        // Add this subproject as a label on the parent build.
        $this->SetParentId($this->LookupParentBuildId());
        if ($this->ParentId > 0) {
            $parent = new Build();
            $parent->Id = $this->ParentId;
            $parent->AddLabel($label);
            $parent->InsertLabelAssociations();
        }

        $subprojectid = $stmt->fetchColumn();
        if ($subprojectid !== false) {
            $this->SubProjectId = $subprojectid;
            return $this->SubProjectId;
        }

        // If the subproject wasn't found, add it here.
        // A proper Project.xml file will still need to be uploaded later to
        // load dependency data.
        $subProject = new SubProject();
        $subProject->SetProjectId($this->ProjectId);
        $subProject->SetName($subproject);
        $subProject->Save();

        // Insert the label too.
        $Label = new Label;
        $Label->Text = $subProject->GetName();
        $Label->Insert();

        add_log('New subproject detected: ' . $subproject, 'Build::SetSubProject',
            LOG_INFO, $this->ProjectId, $this->Id, ModelType::BUILD, $this->Id);
        return true;
    }

    /** Return the subproject name */
    public function GetSubProjectName()
    {
        if (empty($this->Id)) {
            return false;
        }

        if (!empty($this->SubProjectName)) {
            return $this->SubProjectName;
        }

        $stmt = $this->PDO->prepare(
            'SELECT sp.name FROM subproject sp
            JOIN subproject2build sp2b ON sp.id = sp2b.subprojectid
            WHERE sp2b.buildid = ?');
        if (!pdo_execute($stmt, [$this->Id])) {
            return false;
        }

        $subproject_name = $stmt->fetchColumn();
        if ($subproject_name !== false) {
            $this->SubProjectName = $subproject_name;
            return $this->SubProjectName;
        }
        return false;
    }

    /**
     * Record the total execution time of all the tests performed by this build.
     **/
    public function SaveTotalTestsTime($update_parent = true)
    {
        if (!$this->Exists()) {
            return false;
        }

        // Calculate how much processor time was spent running this build's
        // tests.
        $total_proc_time = 0.0;
        foreach ($this->TestCollection as $test) {
            $exec_time = (double)$test->time;
            $num_procs = 1.0;
            foreach ($test->measurements as $measurement) {
                if ($measurement->name == 'Processors') {
                    $num_procs *= $measurement->value;
                    break;
                }
            }
            $total_proc_time += ($exec_time * $num_procs);
        }

        $this->UpdateBuildTestTime($total_proc_time);

        if (!$update_parent) {
            return true;
        }

        // If this is a child build, add this exec time
        // to the parent's value.
        $this->SetParentId($this->LookupParentBuildId());
        if ($this->ParentId > 0) {
            $parent = new Build();
            $parent->Id = $this->ParentId;
            $parent->UpdateBuildTestTime($total_proc_time);
        }
    }

    /**
     * Insert or update a record in the buildtesttime table.
     **/
    protected function UpdateBuildTestTime($test_exec_time)
    {
        \DB::transaction(function () use ($test_exec_time) {
            $buildtesttime_row =
            \DB::table('buildtesttime')
                ->where('buildid', $this->Id)
                ->lockForUpdate()
                ->first();
            if ($buildtesttime_row) {
                // Add to the running total if an entry already exists for this build.
                \DB::table('buildtesttime')
                    ->where('buildid', $this->Id)
                    ->update(['time' => $test_exec_time + $buildtesttime_row->time]);
            } else {
                // Otherwise insert a new record.
                \DB::table('buildtesttime')->insert(
                    ['buildid' => $this->Id, 'time' => $test_exec_time]);
            }
        }, 5);
    }

    /** Update the end time */
    public function UpdateEndTime($end_time)
    {
        if (!$this->Id || !is_numeric($this->Id)) {
            return false;
        }

        $stmt = $this->PDO->prepare(
            'UPDATE build SET endtime = ? WHERE id = ?');
        if (!pdo_execute($stmt, [$end_time, $this->Id])) {
            return false;
        }
        return true;
    }

    /**
     * @param $buildid
     * @return bool|mixed
     */
    public function QuerySubProjectId($buildid)
    {
        $stmt = $this->PDO->prepare(
            'SELECT sp.id FROM subproject sp
            JOIN subproject2build sp2b ON sp.id = sp2b.subprojectid
            WHERE sp2b.buildid = ?');
        if (!pdo_execute($stmt, [$buildid])) {
            return false;
        }
        return $stmt->fetchColumn();
    }

    /** Fill the current build information from the buildid */
    public function FillFromId($buildid)
    {
        if ($this->Filled) {
            // Already filled, no need to do it again.
            return false;
        }

        $stmt = $this->PDO->prepare(
            'SELECT
                projectid,
                starttime,
                endtime,
                submittime,
                siteid,
                name,
                stamp,
                type,
                parentid,
                done,
                builderrors,
                testfailed,
                generator,
                command
            FROM build
            WHERE id = ?');

        if (!pdo_execute($stmt, [$buildid])) {
            return false;
        }

        $build_array = $stmt->fetch();
        if (!is_array($build_array)) {
            return false;
        }

        $this->Name = $build_array['name'];
        $this->SetStamp($build_array['stamp']);
        $this->Type = $build_array['type'];
        $this->StartTime = $build_array['starttime'];
        $this->EndTime = $build_array['endtime'];
        $this->SubmitTime = $build_array['submittime'];
        $this->SiteId = $build_array['siteid'];
        $this->ProjectId = $build_array['projectid'];
        $this->SetParentId($build_array['parentid']);
        $this->Done = $build_array['done'];
        $this->Generator = $build_array['generator'];
        $this->Command = $build_array['command'];
        $this->BuildErrorCount = $build_array['builderrors'];
        $this->TestFailedCount = $build_array['testfailed'];

        $subprojectid = $this->QuerySubProjectId($buildid);
        if ($subprojectid) {
            $this->SubProjectId = $subprojectid;
        }

        $stmt = $this->PDO->prepare(
            'SELECT groupid FROM build2group WHERE buildid = ?');
        pdo_execute($stmt, [$buildid]);
        $this->GroupId = $stmt->fetchColumn();
        $this->Filled = true;
    }

    /**
     * @param Build $build
     * @param array $optional_values
     */
    public static function MarshalResponseArray($build, $optional_values = []) : array
    {
        $response = [
            'id' => $build->Id,
            'buildid' => $build->Id,
            'siteid' => $build->SiteId,
            'name' => $build->Name,
            'buildname' => $build->Name,
            'stamp' => $build->Stamp,
            'projectid' => $build->ProjectId,
            'starttime' => $build->StartTime,
            'endtime' => $build->EndTime,
            'groupid' => $build->GroupId,
            'group' => $build->Type,
        ];

        if ($build->GetSubProjectName()) {
            $response['subproject'] = $build->SubProjectName;
        }

        return array_merge($response, $optional_values);
    }

    /** Get the previous build id. */
    public function GetPreviousBuildId(int|null $previous_parentid = null) : int
    {
        if (!$this->Id) {
            return 0;
        }
        $this->FillFromId($this->Id);

        $previous_clause =
            "AND starttime < :starttime ORDER BY starttime DESC";
        $values_to_bind = [':starttime' => $this->StartTime];
        return $this->GetRelatedBuildId($previous_clause, $values_to_bind,
            $previous_parentid);
    }

    /** Get the next build id. */
    public function GetNextBuildId(int|null $next_parentid = null) : int
    {
        if (!$this->Id) {
            return 0;
        }
        $this->FillFromId($this->Id);

        $next_clause = "AND starttime > :starttime ORDER BY starttime";
        $values_to_bind = [':starttime' => $this->StartTime];
        return $this->GetRelatedBuildId($next_clause, $values_to_bind,
            $next_parentid);
    }

    /** Get the most recent build id. */
    public function GetCurrentBuildId(int|null $current_parentid = null) : int
    {
        if (!$this->Id) {
            return 0;
        }
        $this->FillFromId($this->Id);

        $current_clause = 'ORDER BY starttime DESC';
        return $this->GetRelatedBuildId($current_clause, [], $current_parentid);
    }

    /** Private helper function to encapsulate the common parts of
     * Get{Previous,Next,Current}BuildId()
     * @param array<string, string> $extra_values_to_bind
     **/
    private function GetRelatedBuildId(string $which_build_criteria,
        array $extra_values_to_bind = [],
        int|null $related_parentid = null) : int
    {
        $related_build_criteria =
            'WHERE siteid = :siteid
            AND type = :type
            AND name = :name
            AND projectid = :projectid';

        $values_to_bind = [
            ':siteid' => $this->SiteId,
            ':type' => $this->Type,
            ':name' => $this->Name,
            ':projectid' => $this->ProjectId];

        // Take subproject into account, such that if there is one, then the
        // previous build must be associated with the same subproject...
        //
        if ($this->SubProjectId && !$related_parentid) {
            // Look up the related parent.  This makes it easy to find the
            // corresponding child build.
            $stmt = $this->PDO->prepare(
                "SELECT id FROM build
                $related_build_criteria
                AND build.parentid = " . Build::PARENT_BUILD . "
                AND build.id != :parentid
                $which_build_criteria
                LIMIT 1");

            foreach (array_merge($values_to_bind, $extra_values_to_bind)
                     as $parameter => $value) {
                $stmt->bindValue($parameter, $value);
            }
            $stmt->bindValue(':parentid', $this->GetParentId());
            if (!pdo_execute($stmt)) {
                return 0;
            }
            $related_parentid = $stmt->fetchColumn();
            if (!$related_parentid) {
                return 0;
            }
        }

        $subproj_table = '';
        $subproj_criteria = '';
        $parent_criteria = '';

        // If we know the parent of the build we're looking for, use that as our
        // search criteria rather than matching site, name, type, and project.
        if ($related_parentid) {
            $related_build_criteria = 'WHERE parentid = :parentid';
            $values_to_bind = [':parentid' => $related_parentid];
        }

        if ($this->SubProjectId) {
            $subproj_table =
                'INNER JOIN subproject2build AS sp2b ON (build.id=sp2b.buildid)';
            $subproj_criteria =
                'AND sp2b.subprojectid = :subprojectid';
            $values_to_bind['subprojectid'] = $this->SubProjectId;
        }
        if ($this->ParentId == Build::PARENT_BUILD) {
            // Only search for other parents.
            $parent_criteria = 'AND build.parentid = ' . Build::PARENT_BUILD;
        }

        $stmt = $this->PDO->prepare("
            SELECT id FROM build
            $subproj_table
            $related_build_criteria
            $subproj_criteria
            $parent_criteria
            $which_build_criteria
            LIMIT 1");

        foreach (array_merge($values_to_bind, $extra_values_to_bind)
                 as $parameter => $value) {
            $stmt->bindValue($parameter, $value);
        }
        if (!pdo_execute($stmt)) {
            return 0;
        }

        $related_buildid = $stmt->fetchColumn();
        if (!$related_buildid) {
            return 0;
        }
        return (int)$related_buildid;
    }

    /**
     * Return the errors that have been resolved from this build.
     * @todo This doesn't support getting resolved build errors across parent builds.
     **/
    public function GetResolvedBuildErrors($type)
    {
        // This returns an empty result if there was no previous build
        $stmt = $this->PDO->prepare(
            'SELECT * FROM
             (SELECT * FROM builderror
              WHERE buildid = ? AND type = ?) AS builderrora
             LEFT JOIN
             (SELECT crc32 AS crc32b FROM builderror
              WHERE buildid = ? AND type = ?) AS builderrorb
              ON builderrora.crc32=builderrorb.crc32b
             WHERE builderrorb.crc32b IS NULL');
        pdo_execute($stmt,
            [$this->GetPreviousBuildId(), $type, $this->Id, $type]);
        return $stmt;
    }

    /**
     * Returns all errors, including warnings, from the database, caches, and
     * returns the filtered results
     */
    public function GetErrors(array $propertyFilters = [], int $fetchStyle = PDO::FETCH_ASSOC) : array|false
    {
        // This needs to take into account that this build may be a parent build
        if (!$this->Errors) {
            if (!$this->Id) {
                add_log('BuildId not set', 'Build::GetErrors', LOG_WARNING);
                return false;
            }

            if ($this->IsParentBuild()) {
                $this->Errors = $this->GetErrorsForChildren($fetchStyle);
            } else {
                $buildErrors = new BuildError();
                $buildErrors->BuildId = $this->Id;
                $this->Errors = $buildErrors->GetErrorsForBuild($fetchStyle);
            }
        }
        return $this->PropertyFilter($this->Errors, $propertyFilters);
    }

    /**
     * Returns all failures (errors), including warnings, for current build
     *
     * @param int $fetchStyle
     */
    public function GetFailures(array $propertyFilters = [], int $fetchStyle = PDO::FETCH_ASSOC) : array|false
    {
        // This needs to take into account that this build may be a parent build
        if (!$this->Failures) {
            if (!$this->Id) {
                add_log('BuildId not set', 'Build::GetFailures', LOG_WARNING);
                return false;
            }

            if ($this->isParentBuild()) {
                $this->Failures = $this->GetFailuresForChildren($fetchStyle);
            } else {
                $buildFailure = new BuildFailure();
                $buildFailure->BuildId = $this->Id;
                $this->Failures = $buildFailure->GetFailuresForBuild($fetchStyle);
            }
        }
        return $this->PropertyFilter($this->Failures, $propertyFilters);
    }

    /**
     * Apply filter to rows
     *
     * @param array $filters
     */
    protected function PropertyFilter(array $rows, array $filters) : array
    {
        return array_filter($rows, function ($row) use ($filters) {
            foreach ($filters as $prop => $value) {
                if (is_object($row)) {
                    if (!property_exists($row, $prop)) {
                        add_log("Cannot filter on {$prop}: property does not exist", 'Build::PropertyFilter', LOG_WARNING);
                        continue;
                    }

                    if ($row->$prop != $value) {
                        return false;
                    }
                } elseif (is_array($row)) {
                    if (!array_key_exists($prop, $row)) {
                        add_log("Cannot filter on {$prop}: property does not exist", 'Build::PropertyFilter', LOG_WARNING);
                        continue;
                    }

                    if ($row[$prop] != $value) {
                        return false;
                    }
                }
            }
            return true;
        });
    }

    /**
     * Get build failures (with details) that occurred in the most recent build
     * but NOT this build.
     * @todo This doesn't support getting resolved build failures across parent builds.
     **/
    public function GetResolvedBuildFailures($type)
    {
        $currentFailuresQuery =
            'SELECT bf.detailsid FROM buildfailure AS bf
             LEFT JOIN buildfailuredetails AS bfd ON (bf.detailsid=bfd.id)
             WHERE bf.buildid = :id AND bfd.type = :type';

        $stmt = $this->PDO->prepare(
            "SELECT bf.id, bfd.language, bf.sourcefile, bfd.targetname,
                    bfd.outputfile, bfd.outputtype, bf.workingdirectory,
                    bfd.stderror, bfd.stdoutput, bfd.exitcondition
            FROM buildfailure AS bf
            LEFT JOIN buildfailuredetails AS bfd ON (bfd.id=bf.detailsid)
            WHERE bf.buildid = :previousid
            AND bfd.type = :type
            AND bfd.id NOT IN ($currentFailuresQuery)"
        );
        $stmt->bindValue(':id', $this->Id);
        $stmt->bindValue(':type', $type);
        $stmt->bindValue(':previousid', $this->GetPreviousBuildId());
        pdo_execute($stmt);
        return $stmt;
    }

    /**
     * @return bool|\PDOStatement
     */
    public function GetConfigures()
    {
        if ($this->IsParentBuild()) {
            // Count how many separate configure rows are associated with
            // this parent build.
            $configures_stmt = $this->PDO->prepare('
                SELECT DISTINCT c.id FROM configure c
                JOIN build2configure b2c ON b2c.configureid = c.id
                JOIN build b ON b.id = b2c.buildid
                WHERE b.parentid = ?');
            pdo_execute($configures_stmt, [$this->Id]);
            $configure_rows = $configures_stmt->fetchAll();
            if (count($configure_rows) > 1) {
                // Each SubProject build has its own configure row.
                $stmt = $this->PDO->prepare('
                    SELECT sp.name subprojectname, sp.id subprojectid, c.*,
                           b.configureerrors, b.configurewarnings
                    FROM configure c
                    JOIN build2configure b2c ON b2c.configureid = c.id
                    JOIN subproject2build sp2b ON sp2b.buildid = b2c.buildid
                    JOIN subproject sp ON sp.id = sp2b.subprojectid
                    JOIN build b ON b.id = b2c.buildid
                    WHERE b.parentid = ?');
            } else {
                // One configure row is shared by all the SubProjects.
                $stmt = $this->PDO->prepare('
                    SELECT c.*, b.configureerrors, b.configurewarnings
                    FROM configure c
                    JOIN build2configure b2c ON b2c.configureid = c.id
                    JOIN build b ON b.id = b2c.buildid
                    WHERE c.id = ? LIMIT 1');
                pdo_execute($stmt, [$configure_rows[0]['id']]);
                return $stmt;
            }
        } else {
            $stmt = $this->PDO->prepare('
                SELECT c.*, b.configureerrors, b.configurewarnings
                FROM configure c
                JOIN build2configure b2c ON b2c.configureid = c.id
                JOIN build b ON b.id = b2c.buildid
                WHERE b2c.buildid = ?');
        }
        pdo_execute($stmt, [$this->Id]);
        return $stmt;
    }

    /** Get the build id from its name */
    public function GetIdFromName($subproject)
    {
        // Make sure subproject name and id fields are set:
        //
        $this->SetSubProject($subproject);

        $stmt = null;
        $params = [$this->ProjectId, $this->SiteId, $this->Name, $this->Stamp];

        if ($this->SubProjectId != 0) {
            $stmt = $this->PDO->prepare(
                'SELECT id FROM build
                JOIN subproject2build ON subproject2build.buildid = build.id
                WHERE projectid = ? AND siteid = ? AND name = ? AND
                      stamp = ? AND subprojectid = ?');
            $params[] = $this->SubProjectId;
        } else {
            $stmt = $this->PDO->prepare(
                'SELECT id FROM build
                WHERE projectid = ? AND siteid = ? AND name = ? AND stamp = ?
                AND parentid IN (0, -1)');
        }
        pdo_execute($stmt, $params);
        $id = $stmt->fetchColumn();
        if ($id > 0) {
            $this->Id = $id;
            return $this->Id;
        }

        return 0;
    }

    public function InsertLabelAssociations() : bool
    {
        if (!$this->Id) {
            add_log('No Build::Id - cannot call $label->Insert...', 'Build::InsertLabelAssociations', LOG_ERR,
                $this->ProjectId, $this->Id,
                ModelType::BUILD, $this->Id);
            return false;
        }

        if ($this->LabelCollection->isEmpty()) {
            return true;
        }

        foreach ($this->LabelCollection as $label) {
            $label->BuildId = $this->Id;
            $label->Insert();
        }
        return true;
    }

    /** Return if exists */
    public function Exists()
    {
        if (!$this->Id) {
            return false;
        }
        $stmt = $this->PDO->prepare('SELECT COUNT(*) FROM build WHERE id = ?');
        if (!pdo_execute($stmt, [$this->Id])) {
            return false;
        }
        return $stmt->fetchColumn() > 0;
    }

    // Save in the database
    public function Save()
    {
        // Compute the number of errors and warnings.
        // This speeds up the display of the main table.
        $nbuilderrors = -1;
        $nbuildwarnings = -1;
        if ($this->InsertErrors) {
            $nbuilderrors = 0;
            $nbuildwarnings = 0;
            foreach ($this->Errors as $error) {
                if ($error->Type == 0) {
                    $nbuilderrors++;
                } else {
                    $nbuildwarnings++;
                }
            }
        }

        if (!$this->Exists()) {
            // Insert the build.
            $build_created = $this->AddBuild($nbuilderrors, $nbuildwarnings);
            if (!$this->Id) {
                // Error creating build.
                return false;
            }

            if (!$build_created) {
                // Another process created this build before us.
                // Call UpdateBuild().
                // This also sets ParentId if an existing parent was found.
                $this->UpdateBuild($this->Id, $nbuilderrors, $nbuildwarnings);

                // Does the parent still need to be created?
                if ($this->SubProjectName && $this->ParentId < 1) {
                    if (!$this->CreateParentBuild(
                        $nbuilderrors, $nbuildwarnings)) {
                        // Someone else created the parent after we called
                        // UpdateBuild(this->Id,...).
                        // In this case we also need to manually update
                        // the parent as well.
                        $this->UpdateBuild($this->ParentId,
                            $nbuilderrors, $nbuildwarnings);
                    }
                }
                // Now that the existing build and its parent (if any) have
                // been updated we can return early.
                return true;
            }
        } else {
            // Build already exists.
            // Update this build and its parent (if necessary).
            $this->UpdateBuild($this->Id, $nbuilderrors, $nbuildwarnings);
        }

        // Add errors/warnings
        foreach ($this->Errors as $error) {
            $error->BuildId = $this->Id;
            $error->Insert();
        }

        // Add ErrorDiff
        foreach ($this->ErrorDiffs as $diff) {
            $diff->BuildId = $this->Id;
            $diff->Insert();
        }

        // Add label associations regardless of how Build::Save gets called:
        //
        $this->InsertLabelAssociations();

        // Should we post build errors to a pull request?
        if (!empty($this->PullRequest)) {
            $hasErrors = false;
            foreach ($this->Errors as $error) {
                if ($error->Type == 0) {
                    $hasErrors = true;
                    break;
                }
            }

            if ($hasErrors) {
                $message = "$this->Name experienced errors";
                $config = Config::getInstance();
                $url = $config->getBaseUrl() .
                    "/viewBuildError.php?buildid=$this->Id";
                $this->NotifyPullRequest($message, $url);
            }
        }
        return true;
    }

    /** Helper function for test number accessors. */
    private function GetNumberOfTestsByField($field)
    {
        if ($field != 'testpassed' && $field != 'testfailed' &&
            $field != 'testnotrun') {
            return false;
        }
        $stmt = $this->PDO->prepare(
            "SELECT $field FROM build WHERE id = ?");
        if (!pdo_execute($stmt, [$this->Id])) {
            return false;
        }
        $numTests = $stmt->fetchColumn();
        if ($numTests === false) {
            return 0;
        }
        if ($numTests < 0) {
            return 0;
        }
        return $numTests;
    }

    /** Get number of failed tests */
    public function GetNumberOfFailedTests()
    {
        return $this->GetNumberOfTestsByField('testfailed');
    }

    /** Get number of passed tests */
    public function GetNumberOfPassedTests()
    {
        return $this->GetNumberOfTestsByField('testpassed');
    }

    /** Get number of not run tests */
    public function GetNumberOfNotRunTests()
    {
        return $this->GetNumberOfTestsByField('testnotrun');
    }

    /** Update the test numbers */
    public function UpdateTestNumbers($numberTestsPassed, $numberTestsFailed, $numberTestsNotRun)
    {
        if (!is_numeric($numberTestsPassed) || !is_numeric($numberTestsFailed) || !is_numeric($numberTestsNotRun)) {
            return;
        }

        $this->TestFailedCount = $numberTestsFailed;

        // If this is a subproject build, we also have to update its parents test numbers.
        $newFailed = $numberTestsFailed - $this->GetNumberOfFailedTests();
        $newNotRun = $numberTestsNotRun - $this->GetNumberOfNotRunTests();
        $newPassed = $numberTestsPassed - $this->GetNumberOfPassedTests();
        $this->SetParentId($this->LookupParentBuildId());
        $this->UpdateParentTestNumbers($newFailed, $newNotRun, $newPassed);

        // Update this build's test numbers.
        $stmt = $this->PDO->prepare(
            'UPDATE build SET testnotrun = ?, testfailed = ?, testpassed = ?
            WHERE id = ?');
        if (!pdo_execute($stmt,
            [$numberTestsNotRun, $numberTestsFailed, $numberTestsPassed,
                $this->Id])) {
            return false;
        }

        // Should we should post test failures to a pull request?
        if (!empty($this->PullRequest) && $numberTestsFailed > 0) {
            $message = "$this->Name experienced failing tests";
            $config = Config::getInstance();
            $url = $config->getBaseUrl() .
                "/viewTest.php?onlyfailed&buildid=$this->Id";
            $this->NotifyPullRequest($message, $url);
        }
    }

    /**
     * Get missing tests' names relative to previous build
     * @return array<int, string>
     */
    public function GetMissingTests() : array
    {
        if (!$this->MissingTests) {
            $this->MissingTests = [];

            if (!$this->Id) {
                add_log('BuildId is not set', 'Build::GetMissingTests', LOG_ERR,
                    $this->ProjectId, $this->Id, ModelType::BUILD, $this->Id);
                return $this->MissingTests;
            }

            $previous_build_tests = [];
            $current_build_tests = [];

            $previous_build = $this->GetPreviousBuildId();

            $sql = "SELECT DISTINCT B.id, B.name FROM build2test A
                LEFT JOIN test B
                  ON A.testid=B.id
                WHERE A.buildid=?
                ORDER BY B.name
             ";

            $query = $this->PDO->prepare($sql);

            pdo_execute($query, [$previous_build]);
            foreach ($query->fetchAll(PDO::FETCH_OBJ) as $test) {
                $previous_build_tests[$test->id] = $test->name;
            }

            pdo_execute($query, [$this->Id]);
            foreach ($query->fetchAll(PDO::FETCH_OBJ) as $test) {
                $current_build_tests[$test->id] = $test->name;
            }
            $this->MissingTests = array_diff($previous_build_tests, $current_build_tests);
        }

        return $this->MissingTests;
    }

    /**
     * Gut the number of missing tests relative to previous build
     **/
    public function GetNumberOfMissingTests() : int
    {
        if (!is_array($this->MissingTests)) {
            // feels clumsy but necessary for testing :( (for the time being)
            $this->MissingTests = $this->GetMissingTests();
        }

        return count($this->MissingTests);
    }

    /**
     * Get this build's tests that match the supplied WHERE clause.
     * @return array<mixed>|false
     */
    private function GetTests(string $criteria, int $maxitems = 0) : array|false
    {
        if (!$this->Id) {
            add_log('BuildId is not set', 'Build::GetTests', LOG_ERR,
                $this->ProjectId, $this->Id, ModelType::BUILD, $this->Id);
            return false;
        }

        $limit_clause = '';
        if ($maxitems > 0) {
            $limit_clause = "LIMIT $maxitems";
        }

        $sql = "
            SELECT t.name, b2t.id AS buildtestid, b2t.details
            FROM test t
            JOIN build2test b2t ON t.id = b2t.testid
            WHERE b2t.buildid = :buildid
            AND $criteria
            ORDER BY t.id
            $limit_clause";

        $query = $this->PDO->prepare($sql);
        $query->bindParam(':buildid', $this->Id);

        if (!pdo_execute($query)) {
            return [];
        }
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get this build's tests that passed.
     * @return array<mixed>|false
     */
    public function GetPassedTests(int $maxitems = 0) : array|false
    {
        $criteria = "b2t.status = 'passed'";
        return $this->GetTests($criteria, $maxitems);
    }

    /**
     * Get this build's tests that failed but did not timeout.
     * @return array<mixed>|false
     */
    public function GetFailedTests(int $maxitems = 0) : array|false
    {
        $criteria = "b2t.status = 'failed'";
        return $this->GetTests($criteria, $maxitems);
    }

    /**
     * Get this build's tests that failed the time status check.
     * @return array<mixed>|false
     */
    public function GetFailedTimeStatusTests(int $maxitems = 0, int $max_time_status = 3) : array|false
    {
        $criteria = "b2t.timestatus > $max_time_status";
        return $this->GetTests($criteria, $maxitems);
    }

    /**
     * Get this build's tests whose details indicate a timeout.
     * @return array<mixed>|false
     */
    public function GetTimedoutTests(int $maxitems = 0) : array|false
    {
        $criteria = "b2t.status = 'failed' AND b2t.details LIKE '%%Timeout%%'";
        return $this->GetTests($criteria, $maxitems);
    }

    /**
     * Get this build's tests whose status is "Not Run" and whose details
     * is not 'Disabled'.
     * @return array<mixed>|false
     */
    public function GetNotRunTests(int $maxitems = 0) : array|false
    {
        $criteria = "b2t.status = 'notrun' AND b2t.details != 'Disabled'";
        return $this->GetTests($criteria, $maxitems);
    }

    /** Get the errors differences for the build */
    public function GetErrorDifferences()
    {
        if (!$this->Id) {
            add_log('BuildId is not set', 'Build::GetErrorDifferences', LOG_ERR,
                $this->ProjectId, $this->Id, ModelType::BUILD, $this->Id);
            return false;
        }

        $diff = [];

        $stmt = $this->PDO->prepare(
            'SELECT build.id,
                    builderrordiff.type AS builderrortype,
                    builderrordiff.difference_positive AS builderrorspositive,
                    builderrordiff.difference_negative AS builderrorsnegative,
                    configureerrordiff.type AS configureerrortype,
                    configureerrordiff.difference AS configureerrors,
                    testdiff.type AS testerrortype,
                    testdiff.difference_positive AS testerrorspositive,
                    testdiff.difference_negative AS testerrorsnegative
              FROM build
              LEFT JOIN builderrordiff ON builderrordiff.buildid=build.id
              LEFT JOIN configureerrordiff ON configureerrordiff.buildid=build.id
              LEFT JOIN testdiff ON testdiff.buildid=build.id
              WHERE build.id = ?');
        if (!pdo_execute($stmt, [$this->Id])) {
            return false;
        }

        while ($query_array = $stmt->fetch()) {
            if ($query_array['builderrortype'] == 0) {
                $diff['builderrorspositive'] = $query_array['builderrorspositive'];
                $diff['builderrorsnegative'] = $query_array['builderrorsnegative'];
            } else {
                $diff['buildwarningspositive'] = $query_array['builderrorspositive'];
                $diff['buildwarningsnegative'] = $query_array['builderrorsnegative'];
            }

            if ($query_array['configureerrortype'] == 0) {
                $diff['configureerrors'] = $query_array['configureerrors'];
            } else {
                $diff['configurewarnings'] = $query_array['configureerrors'];
            }

            if ($query_array['testerrortype'] == 2) {
                $diff['testpassedpositive'] = $query_array['testerrorspositive'];
                $diff['testpassednegative'] = $query_array['testerrorsnegative'];
            } elseif ($query_array['testerrortype'] == 1) {
                $diff['testfailedpositive'] = $query_array['testerrorspositive'];
                $diff['testfailednegative'] = $query_array['testerrorsnegative'];
            } elseif ($query_array['testerrortype'] == 0) {
                $diff['testnotrunpositive'] = $query_array['testerrorspositive'];
                $diff['testnotrunnegative'] = $query_array['testerrorsnegative'];
            }
        }

        // If some of the errors are not set default to zero
        $variables = ['builderrorspositive', 'builderrorsnegative',
            'buildwarningspositive', 'buildwarningsnegative',
            'configureerrors', 'configurewarnings',
            'testpassedpositive', 'testpassednegative',
            'testfailedpositive', 'testfailednegative',
            'testnotrunpositive', 'testnotrunnegative'];
        foreach ($variables as $var) {
            if (!isset($diff[$var])) {
                $diff[$var] = 0;
            }
        }
        return $diff;
    }

    /** Compute the build errors differences */
    public function ComputeDifferences()
    {
        if (!$this->Id) {
            add_log('BuildId is not set', 'Build::ComputeDifferences', LOG_ERR,
                $this->ProjectId, $this->Id,
                ModelType::BUILD, $this->Id);
            return false;
        }

        $previousbuildid = $this->GetPreviousBuildId();
        if ($previousbuildid == 0) {
            return;
        }
        compute_error_difference($this->Id, $previousbuildid, 0); // errors
        compute_error_difference($this->Id, $previousbuildid, 1); // warnings
    }

    /** Compute the difference in configure warnings between this build and the
     *  previous one.
     *  TODO: we should probably also do configure errors here too.
     */
    public function ComputeConfigureDifferences()
    {
        if (!$this->Id) {
            \Log::error('Buildid is not set');
            return false;
        }

        $previousbuildid = $this->GetPreviousBuildId();
        if ($previousbuildid == 0) {
            return;
        }

        // Look up the number of configure warnings for this build
        // and the previous one.
        $nwarnings = \DB::table('build')->where('id', $this->Id)->first()->configurewarnings;
        $npreviouswarnings = \DB::table('build')->where('id', $previousbuildid)->first()->configurewarnings;

        \DB::transaction(function () use ($nwarnings, $npreviouswarnings) {
            // Check if a diff already exists for this build.
            $existing_diff_row = \DB::table('configureerrordiff')
                ->where('buildid', $this->Id)
                ->lockForUpdate()
                ->first();

            $existing_diff = 0;
            if ($existing_diff_row) {
                $existing_diff = $existing_diff_row->difference;
            }

            // Don't log if no diff.
            $warningdiff = $nwarnings - $npreviouswarnings;
            if ($warningdiff == 0 && $existing_diff == 0) {
                return;
            }

            // UPDATE or INSERT a new record as necessary.
            if ($existing_diff_row) {
                \DB::table('configureerrordiff')
                    ->where('buildid', $this->Id)
                    ->update(['difference' => $warningdiff]);
            } else {
                \DB::table('configureerrordiff')->insertOrIgnore([
                    ['buildid' => $this->Id, 'difference' => $warningdiff],
                ]);
            }
        }, 5);
    }

    /** Compute the test timing as a weighted average of the previous test.
     *  Also compute the difference in tests between builds.
     *  We do that in one shot for speed reasons. */
    public function ComputeTestTiming()
    {
        if (!$this->Id) {
            add_log('BuildId is not set', 'Build::ComputeTestTiming', LOG_ERR,
                $this->ProjectId, $this->Id, ModelType::BUILD, $this->Id);
            return false;
        }

        if (!$this->ProjectId) {
            add_log('ProjectId is not set', 'Build::ComputeTestTiming', LOG_ERR,
                $this->ProjectId, $this->Id, ModelType::BUILD, $this->Id);
            return false;
        }

        // Find the previous build
        $previousbuildid = $this->GetPreviousBuildId();
        if ($previousbuildid == 0) {
            return false;
        }

        // Record test differences from the previous build.
        // (+/- number of tests that failed, etc.)
        TestDiffService::computeDifferences($this);

        $project_stmt = $this->PDO->prepare(
            'SELECT testtimestd, testtimestdthreshold, testtimemaxstatus
            FROM project WHERE id = ?');
        if (!pdo_execute($project_stmt, [$this->ProjectId])) {
            return false;
        }

        // The weight of the current test compared to the previous mean/std
        // (this defines a window).
        $weight = 0.3;
        // Whether or not this build has any tests that failed
        // the time status check.
        $testtimestatusfailed = 0;

        $project_array = $project_stmt->fetch();
        $projecttimestd = $project_array['testtimestd'];
        $projecttimestdthreshold = $project_array['testtimestdthreshold'];
        $projecttestmaxstatus = $project_array['testtimemaxstatus'];

        // Get the tests performed by the previous build.
        $previous_tests_stmt = $this->PDO->prepare(
            'SELECT b2t.id AS buildtestid, b2t.testid, t.name
            FROM build2test b2t
            JOIN test t ON t.id = b2t.testid
            WHERE b2t.buildid = ?');
        if (!pdo_execute($previous_tests_stmt, [$previousbuildid])) {
            return false;
        }

        $testarray = [];
        while ($row = $previous_tests_stmt->fetch()) {
            $test = [];
            $test['buildtestid'] = $row['buildtestid'];
            $test['id'] = $row['testid'];
            $test['name'] = $row['name'];
            $testarray[] = $test;
        }

        // Loop through the tests performed by this build.
        $tests_stmt = $this->PDO->prepare(
            'SELECT b2t.id AS buildtestid, b2t.time, b2t.testid, t.name,
                    b2t.status, b2t.timestatus
            FROM build2test b2t
            JOIN test t ON b2t.testid = t.id
            WHERE b2t.buildid = ?');
        if (!pdo_execute($tests_stmt, [$this->Id])) {
            return false;
        }
        while ($row = $tests_stmt->fetch()) {
            $testtime = $row['time'];
            $buildtestid = $row['buildtestid'];
            $testid = $row['testid'];
            $teststatus = $row['status'];
            $testname = $row['name'];
            $previousbuildtestid = 0;
            $timestatus = $row['timestatus'];

            foreach ($testarray as $test) {
                if ($test['name'] == $testname) {
                    $previousbuildtestid = $test['buildtestid'];
                    break;
                }
            }

            if ($previousbuildtestid > 0) {
                $previous_test_stmt = $this->PDO->prepare(
                    'SELECT timemean, timestd, timestatus FROM build2test
                    WHERE id = ?');
                if (!pdo_execute($previous_test_stmt, [$previousbuildtestid])) {
                    continue;
                }

                $previoustest_array = $previous_test_stmt->fetch();
                $previoustimemean = $previoustest_array['timemean'];
                $previoustimestd = $previoustest_array['timestd'];
                $previoustimestatus = $previoustest_array['timestatus'];

                if ($teststatus == 'passed') {
                    // if the current test passed

                    // Check the current status
                    if ($previoustimestd < $projecttimestdthreshold) {
                        $previoustimestd = $projecttimestdthreshold;
                    }

                    if ($testtime > $previoustimemean + $projecttimestd * $previoustimestd) {
                        // only do positive std

                        $timestatus = $previoustimestatus + 1; // flag
                    } else {
                        $timestatus = 0; // reset the time status to 0
                    }

                    if ($timestatus > 0 && $timestatus <= $projecttestmaxstatus) {
                        // if we are currently detecting the time changed we should use previous mean std

                        $timemean = $previoustimemean;
                        $timestd = $previoustimestd;
                    } else {
                        // Update the mean and std
                        $timemean = (1 - $weight) * $previoustimemean + $weight * $testtime;
                        $timestd = sqrt((1 - $weight) * $previoustimestd * $previoustimestd + $weight * ($testtime - $timemean) * ($testtime - $timemean));
                    }
                } else {
                    // the test failed so we just replicate the previous test time

                    $timemean = $previoustimemean;
                    $timestd = $previoustimestd;
                    $timestatus = 0;
                }
            } else {
                // the test doesn't exist

                $timestd = 0;
                $timestatus = 0;
                $timemean = $testtime;
            }

            $buildtest = \App\Models\BuildTest::find($buildtestid);
            $buildtest->timestatus = $timestatus;

            // TODO: remove these cast-to-strings after upgrading to Laravel 6.x.
            // https://github.com/laravel/framework/issues/23850
            $buildtest->timemean = "$timemean";
            $buildtest->timestd = "$timestd";

            \DB::transaction(function () use ($buildtest) {
                $buildtest->save();
            });

            if ($timestatus >= $projecttestmaxstatus) {
                $testtimestatusfailed++;
            }
        }

        $stmt = $this->PDO->prepare(
            'UPDATE build SET testtimestatusfailed = ? WHERE id = ?');
        if (!pdo_execute($stmt, [$testtimestatusfailed, $this->Id])) {
            return false;
        }
        return true;
    }

    /** Compute the user statistics */
    public function ComputeUpdateStatistics()
    {
        if (!$this->Id) {
            add_log('Id is not set', 'Build::ComputeUpdateStatistics', LOG_ERR,
                $this->ProjectId, $this->Id, ModelType::BUILD, $this->Id);
            return false;
        }

        if (!$this->ProjectId) {
            add_log('ProjectId is not set', 'Build::ComputeUpdateStatistics', LOG_ERR, 0, $this->Id);
            return false;
        }

        $previousbuildid = $this->GetPreviousBuildId();
        if ($previousbuildid < 1) {
            // Nothing to compare the current results against.
            return false;
        }

        // Find how the number of errors, warnings and test failures have changed.
        $previousbuild = new Build();
        $previousbuild->Id = $previousbuildid;

        $errordiff = $this->GetNumberOfErrors() -
            $previousbuild->GetNumberOfErrors();
        $warningdiff = $this->GetNumberOfWarnings() -
            $previousbuild->GetNumberOfWarnings();
        $testdiff =
            ($this->GetNumberOfFailedTests() + $this->GetNumberOfNotRunTests()) -
            ($previousbuild->GetNumberOfFailedTests() + $previousbuild->GetNumberOfNotRunTests());

        // Find the number of authors that contributed to this changeset.
        $nauthors_stmt = $this->PDO->prepare(
            'SELECT count(author) FROM
                    (SELECT uf.author FROM updatefile AS uf
                     JOIN build2update AS b2u ON (b2u.updateid=uf.updateid)
                     WHERE b2u.buildid=? GROUP BY author)
                 AS test');
        pdo_execute($nauthors_stmt, [$this->Id]);
        $nauthors_array = $nauthors_stmt->fetch();
        $nauthors = $nauthors_array[0];

        $newbuild = 1;
        $previousauthor = '';
        // Record user statistics for each updated file.
        $updatefiles_stmt = $this->PDO->prepare(
            "SELECT author,email,checkindate,filename FROM updatefile AS uf
            JOIN build2update AS b2u ON b2u.updateid=uf.updateid
            WHERE b2u.buildid=? AND checkindate>'1980-01-01T00:00:00'
            ORDER BY author ASC, checkindate ASC");
        pdo_execute($updatefiles_stmt, [$this->Id]);

        while ($updatefiles_array = $updatefiles_stmt->fetch()) {
            $checkindate = $updatefiles_array['checkindate'];
            $author = $updatefiles_array['author'];
            $filename = $updatefiles_array['filename'];
            $email = $updatefiles_array['email'];
            $warnings = 0;
            $errors = 0;
            $tests = 0;

            // cache the author, email results
            $this->CommitAuthors = array_unique(array_merge($this->CommitAuthors, [$author, $email]));

            if ($author != $previousauthor) {
                $newbuild = 1;
            }
            $previousauthor = $author;

            if ($warningdiff > 1) {
                $warnings = $this->FindRealErrors('WARNING', $author, $this->Id, $filename);
            } elseif ($warningdiff < 0) {
                $warnings = $this->FindRealErrors('WARNING', $author, $previousbuildid, $filename) * -1;
            }
            if ($errordiff > 1) {
                $errors = $this->FindRealErrors('ERROR', $author, $this->Id, $filename);
            } elseif ($errordiff < 0) {
                $errors = $this->FindRealErrors('ERROR', $author, $previousbuildid, $filename) * -1;
            }
            if ($nauthors > 1) {
                // When multiple authors contribute to a changeset it is
                // too difficult to determine which modified file caused a
                // change in test behavior.
                $tests = 0;
            } else {
                $tests = $testdiff;
            }

            $this->AddUpdateStatistics($author, $email, $checkindate, $newbuild,
                $warnings, $errors, $tests);

            $warningdiff -= $warnings;
            $errordiff -= $errors;
            $testdiff -= $tests;
            $newbuild = 0;
        }
        return true;
    }

    /** Helper function for AddUpdateStatistics */
    private function AddUpdateStatistics($author, $email, $checkindate, $firstbuild,
        $warningdiff, $errordiff, $testdiff)
    {
        // Find user by email address.
        $user = new User();
        $userid = $user->GetIdFromEmail($email);
        if (!$userid) {
            // Find user by author name.
            $stmt = $this->PDO->prepare(
                'SELECT up.userid FROM user2project AS up
                JOIN user2repository AS ur ON (ur.userid=up.userid)
                WHERE up.projectid=:projectid
                AND (ur.credential=:author OR ur.credential=:email)
                AND (ur.projectid=0 OR ur.projectid=:projectid)');
            $stmt->bindParam(':projectid', $this->ProjectId);
            $stmt->bindParam(':author', $author);
            $stmt->bindParam(':email', $email);
            pdo_execute($stmt);
            $row = $stmt->fetch();
            if (!$row) {
                // Unable to find user, return early.
                return;
            }
            $userid = $row['userid'];
        }

        $totalbuilds = 0;
        if ($firstbuild == 1) {
            $totalbuilds = 1;
        }

        // Convert errordiff to nfailederrors & nfixederrors (etc).
        $nfailedwarnings = 0;
        $nfixedwarnings = 0;
        $nfailederrors = 0;
        $nfixederrors = 0;
        $nfailedtests = 0;
        $nfixedtests = 0;
        if ($warningdiff > 0) {
            $nfailedwarnings = $warningdiff;
        } else {
            $nfixedwarnings = abs($warningdiff);
        }
        if ($errordiff > 0) {
            $nfailederrors = $errordiff;
        } else {
            $nfixederrors = abs($errordiff);
        }
        if ($testdiff > 0) {
            $nfailedtests = $testdiff;
        } else {
            $nfixedtests = abs($testdiff);
        }

        // Insert or update appropriately.
        \DB::transaction(function () use ($checkindate, $nfailederrors, $nfailedtests,
            $nfailedwarnings, $nfixederrors, $nfixedtests,
            $nfixedwarnings, $totalbuilds, $userid) {
            $row = \DB::table('userstatistics')
                ->where('userid', $userid)
                ->where('projectid', $this->ProjectId)
                ->where('checkindate', $checkindate)
                ->lockForUpdate()
                ->first();

            if ($row) {
                // Update existing entry.
                \DB::table('userstatistics')
                    ->where('userid', $userid)
                    ->where('projectid', $this->ProjectId)
                    ->where('checkindate', $checkindate)
                    ->update([
                        'totalupdatedfiles' => \DB::raw('totalupdatedfiles + 1'),
                        'totalbuilds' => \DB::raw("totalbuilds + $totalbuilds"),
                        'nfixedwarnings' => \DB::raw("nfixedwarnings + $nfixedwarnings"),
                        'nfailedwarnings' => \DB::raw("nfailedwarnings + $nfailedwarnings"),
                        'nfixederrors' => \DB::raw("nfixederrors + $nfixederrors"),
                        'nfailederrors' => \DB::raw("nfailederrors + $nfailederrors"),
                        'nfixedtests' => \DB::raw("nfixedtests + $nfixedtests"),
                        'nfailedtests' => \DB::raw("nfailedtests + $nfailedtests"),
                    ]);
            } else {
                // Insert a new row into the database.
                \DB::table('userstatistics')
                    ->insert([
                        'userid' => $userid,
                        'projectid' => $this->ProjectId,
                        'checkindate' => $checkindate,
                        'totalupdatedfiles' => 1,
                        'totalbuilds' => $totalbuilds,
                        'nfixedwarnings' => $nfixedwarnings,
                        'nfailedwarnings' => $nfailedwarnings,
                        'nfixederrors' => $nfixederrors,
                        'nfailederrors' => $nfailederrors,
                        'nfixedtests' => $nfixedtests,
                        'nfailedtests' => $nfailedtests,
                    ]);
            }
        }, 5);
    }

    /** Find the errors associated with a user
     *  For now the author is not used, we assume that the filename is sufficient */
    private function FindRealErrors($type, $author, $buildid, $filename)
    {
        $errortype = 0;
        if ($type == 'WARNING') {
            $errortype = 1;
        }

        // Get number of builderrors.
        $stmt = $this->PDO->prepare(
            'SELECT COUNT(*) FROM builderror
            WHERE type = ? AND sourcefile LIKE ? AND buildid = ?');
        if (!pdo_execute($stmt, [$errortype, "%$filename%", $buildid])) {
            return false;
        }
        $nerrors = $stmt->fetchColumn();

        // Get number of buildfailures.
        $stmt = $this->PDO->prepare(
            'SELECT COUNT(*) FROM buildfailure AS bf
            LEFT JOIN buildfailuredetails AS bfd ON (bfd.id = bf.detailsid)
            WHERE bfd.type = ? AND bf.sourcefile LIKE ? AND bf.buildid = ?');
        if (!pdo_execute($stmt, [$errortype, "%$filename%", $buildid])) {
            return false;
        }
        $nerrors += $stmt->fetchColumn();
        return $nerrors;
    }

    /** Return the name of a build */
    public function GetName()
    {
        if (!$this->Id) {
            add_log('Id not set', 'Build GetName()', LOG_ERR);
            return false;
        }

        $stmt = $this->PDO->prepare('SELECT name FROM build WHERE id = ?');
        if (!pdo_execute($stmt, [$this->Id])) {
            return false;
        }
        return $stmt->fetchColumn();
    }

    /** Get all the labels for a given build */
    public function GetLabels($labelarray = [])
    {
        if (!$this->Id) {
            add_log('Id not set', 'Build GetLabels()', LOG_ERR);
            return false;
        }

        $sql =
            'SELECT label.id as labelid FROM label WHERE label.id IN
                (SELECT labelid AS id FROM label2build
                 WHERE label2build.buildid = :buildid)';

        if (empty($labelarray) || isset($labelarray['test']['errors'])) {
            $sql .=
                ' OR label.id IN
                    (SELECT labelid AS id FROM label2test
                     WHERE label2test.buildid = :buildid)';
        }
        if (empty($labelarray) || isset($labelarray['coverage']['errors'])) {
            $sql .=
                ' OR label.id IN
                    (SELECT labelid AS id FROM label2coveragefile
                     WHERE label2coveragefile.buildid = :buildid)';
        }
        if (empty($labelarray) || isset($labelarray['build']['errors'])) {
            $sql .=
                " OR label.id IN (
                    SELECT l2bf.labelid AS id
                    FROM label2buildfailure AS l2bf
                    LEFT JOIN buildfailure AS bf ON (bf.id=l2bf.buildfailureid)
                    LEFT JOIN buildfailuredetails AS bfd ON (bfd.id=bf.detailsid)
                    WHERE bfd.type='0' AND bf.buildid = :buildid)";
        }
        if (empty($labelarray) || isset($labelarray['build']['warnings'])) {
            $sql .=
                " OR label.id IN (
                    SELECT l2bf.labelid AS id
                    FROM label2buildfailure AS l2bf
                    LEFT JOIN buildfailure AS bf ON (bf.id=l2bf.buildfailureid)
                    LEFT JOIN buildfailuredetails AS bfd ON (bfd.id=bf.detailsid)
                    WHERE bfd.type='1' AND bf.buildid = :buildid)";
        }
        if (empty($labelarray) || isset($labelarray['dynamicanalysis']['errors'])) {
            $sql .=
                ' OR label.id IN
                  (SELECT labelid AS id FROM label2dynamicanalysis l2da
                     JOIN dynamicanalysis da ON l2da.dynamicanalysisid = da.id
                     WHERE da.buildid = :buildid)';
        }

        $stmt = $this->PDO->prepare($sql);
        $stmt->bindValue(':buildid', $this->Id);
        if (!pdo_execute($stmt)) {
            return false;
        }

        $labelids = [];
        while ($label_array = $stmt->fetch()) {
            $labelids[] = $label_array['labelid'];
        }
        return array_unique($labelids);
    }

    /**
     * Get the group for a build
     * @return bool|mixed
     */
    public function GetGroup()
    {
        if (!$this->Id) {
            add_log('Id not set', 'Build GetGroup()', LOG_ERR);
            return false;
        }
        $stmt = $this->PDO->prepare(
            'SELECT groupid FROM build2group WHERE buildid = ?');
        if (!pdo_execute($stmt, [$this->Id])) {
            return false;
        }
        return $stmt->fetchColumn();
    }

    /** Get the number of errors for a build */
    public function GetNumberOfErrors()
    {
        if (!$this->Id) {
            add_log('Id not set', 'Build GetNumberOfErrors()', LOG_ERR);
            return false;
        }

        $stmt = $this->PDO->prepare(
            'SELECT builderrors FROM build WHERE id = ?');
        if (!pdo_execute($stmt, [$this->Id])) {
            return false;
        }
        $num_errors = $stmt->fetchColumn();
        return self::ConvertMissingToZero($num_errors);
    }

    /** Get the number of warnings for a build */
    public function GetNumberOfWarnings()
    {
        if (!$this->Id) {
            add_log('Id not set', 'Build GetNumberOfWarnings()', LOG_ERR);
            return false;
        }

        $stmt = $this->PDO->prepare(
            'SELECT buildwarnings FROM build WHERE id = ?');
        if (!pdo_execute($stmt, [$this->Id])) {
            return false;
        }

        $num_warnings = $stmt->fetchColumn();
        return self::ConvertMissingToZero($num_warnings);
    }

    /* Return all uploaded files or URLs for this build */
    public function GetUploadedFilesOrUrls()
    {
        if (!$this->Id) {
            add_log('Id not set', 'Build GetUploadedFilesOrUrls()', LOG_ERR);
            return false;
        }

        $stmt = $this->PDO->prepare(
            'SELECT fileid FROM build2uploadfile WHERE buildid = ?');
        if (!pdo_execute($stmt, [$this->Id])) {
            return false;
        }

        $allUploadedFiles = [];
        while ($uploadfiles_array = $stmt->fetch()) {
            $UploadFile = new UploadFile();
            $UploadFile->Id = $uploadfiles_array['fileid'];
            $UploadFile->Fill();
            $allUploadedFiles[] = $UploadFile;
        }
        return $allUploadedFiles;
    }

    /** Lookup this build's parentid, returning 0 if none is found. */
    public function LookupParentBuildId()
    {
        if (!$this->SiteId || !$this->Name || !$this->Stamp || !$this->ProjectId) {
            return 0;
        }

        $stmt = $this->PDO->prepare(
            'SELECT id FROM build WHERE parentid = -1 AND projectid = ? AND
            siteid = ? AND name = ? AND stamp = ?');
        pdo_execute($stmt, [$this->ProjectId, $this->SiteId, $this->Name, $this->Stamp]);
        $parentid = $stmt->fetchColumn();

        if ($parentid !== false) {
            return $parentid;
        }
        return 0;
    }

    /** Create a new build as a parent of $this and sets $this->ParentId.
     * Assumes many fields have been set prior to calling this function.
     **/
    public function CreateParentBuild($numErrors, $numWarnings)
    {
        if ($numErrors < 0) {
            $numErrors = 0;
        }
        if ($numWarnings < 0) {
            $numWarnings = 0;
        }

        // Check if there's an existing build that should be the parent.
        // This would be a standalone build with no subproject that matches
        // our name, site, stamp, and projectid.
        $stmt = $this->PDO->prepare(
            'SELECT id FROM build
            WHERE parentid = ' . Build::STANDALONE_BUILD . ' AND name = ? AND
                  siteid = ? AND stamp = ? AND projectid = ?');
        if (!pdo_execute($stmt,
            [$this->Name, $this->SiteId, $this->Stamp, $this->ProjectId])) {
            return false;
        }
        $existing_buildid = $stmt->fetchColumn();
        if ($existing_buildid !== false) {
            // Use the previously existing parent if one exists.
            $this->SetParentId($existing_buildid);

            // Mark it as a parent (parentid of -1).
            $stmt = $this->PDO->prepare(
                'UPDATE build SET parentid = ' . Build::PARENT_BUILD . '
                WHERE id = ?');
            pdo_execute($stmt, [$this->ParentId]);
        } else {
            // Otherwise create a new build to be the parent.
            $parent = clone $this;
            $parent->Id = null;
            $parent->ParentId = Build::PARENT_BUILD;
            $parent->SubProjectId = null;
            $parent->SubProjectName = '';
            $parent->Uuid = '';
            $parent->AddBuild(0, 0);
            $this->SetParentId($parent->Id);
        }

        // Update the parent's tally of build errors & warnings.
        $this->UpdateBuild($this->ParentId, $numErrors, $numWarnings);

        // Give the parent a label for this build's subproject.
        $label = new Label;
        $label->Text = $this->SubProjectName;
        $parent = new Build();
        $parent->Id = $this->ParentId;
        $parent->AddLabel($label);
        $parent->InsertLabelAssociations();

        // Since we just created a parent we should also update any existing
        // builds that should be a child of this parent but aren't yet.
        // This happens when Update.xml is parsed first, because it doesn't
        // contain info about what subproject it came from.
        // TODO: maybe we don't need this any more?
        $stmt = $this->PDO->prepare(
            'UPDATE build SET parentid = ?
            WHERE parentid = ' . Build::STANDALONE_BUILD . ' AND
                  siteid = ? AND name = ? AND stamp = ? AND projectid = ?');
        if (!pdo_execute($stmt,
            [$this->ParentId, $this->SiteId, $this->Name, $this->Stamp,
                $this->ProjectId])) {
            return false;
        }
        return true;
    }

    /**
     * Update our database record of a build so that it accurately reflects
     * this object and the specified number of new warnings & errors.
     **/
    public function UpdateBuild($buildid, $newErrors, $newWarnings, $lock=true)
    {
        if ($buildid < 1) {
            return;
        }

        \DB::transaction(function () use ($buildid, $newErrors, $newWarnings, $lock) {
            if ($lock) {
                $build = \DB::table('build')
                    ->where('id', $buildid)
                    ->lockForUpdate()
                    ->first();
                if ($build && $build->parentid > 0) {
                    $parent_build = \DB::table('build')
                        ->where('id', $build->parentid)
                        ->lockForUpdate()
                        ->first();
                }
            } else {
                $build = \DB::table('build')
                    ->where('id', $buildid)
                    ->first();
            }

            if (!$build) {
                return;
            }

            $fields_to_update = [];

            // Special case: check if we should move from -1 to 0 errors/warnings.
            $errorsHandled = false;
            $warningsHandled = false;
            if ($this->InsertErrors) {
                if ($build->builderrors == -1 && $newErrors == 0) {
                    $fields_to_update['builderrors'] = 0;
                    $errorsHandled = true;
                }
                if ($build->buildwarnings == -1 && $newWarnings == 0) {
                    $fields_to_update['buildwarnings'] = 0;
                    $warningsHandled = true;
                }
            }

            // Check if we still need to modify builderrors or buildwarnings.
            if (!$errorsHandled && $newErrors > 0) {
                $builderrors = self::ConvertMissingToZero($build->builderrors);
                $fields_to_update['builderrors'] = $builderrors + $newErrors;
            }
            if (!$warningsHandled && $newWarnings > 0) {
                $buildwarnings = self::ConvertMissingToZero($build->buildwarnings);
                $fields_to_update['buildwarnings'] = $buildwarnings + $newWarnings;
                $nwarnings = $buildwarnings + $newWarnings;
            }

            // Check if we need to modify starttime or endtime.
            // TODO: reference testing_handler.php line 368
            if (strtotime($build->starttime) > strtotime($this->StartTime)) {
                $fields_to_update['starttime'] = $this->StartTime;
            }
            if (strtotime($build->endtime) < strtotime($this->EndTime)) {
                $fields_to_update['endtime'] = $this->EndTime;
            }

            if ($build->parentid != -1) {
                // If this is not a parent build, check if its log or command
                // has changed.
                if ($this->Log && $this->Log != $build->log) {
                    if (!empty($build->log)) {
                        $log = $build->log . " " . $this->Log;
                    } else {
                        $log = $this->Log;
                    }
                    $fields_to_update['log'] = $log;
                }
                if ($this->Command && $this->Command != $build->command) {
                    if (!empty($build->command)) {
                        $command = $build->command . "; " . $this->Command;
                    } else {
                        $command = $this->Command;
                    }
                    $fields_to_update['command'] = $command;
                }
            }

            // Check if the build's changeid has changed.
            if ($this->PullRequest && $this->PullRequest != $build->changeid) {
                $fields_to_update['changeid'] = $this->PullRequest;
            }

            // Check if the build's generator has changed.
            if ($this->Generator && $this->Generator != $build->generator) {
                $fields_to_update['generator'] = $this->Generator;
            }

            if (!empty($fields_to_update)) {
                \DB::table('build')
                    ->where('id', $buildid)
                    ->update($fields_to_update);
            }

            $this->SaveInformation();

            // Also update the parent if necessary.
            $build = \DB::table('build')
                ->where('id', $buildid)
                ->first();
            if ($build->parentid > 0) {
                if ($buildid == $build->parentid) {
                    // Avoid infinite recursion.
                    // This should never happen, but we might as well be careful.
                    \Log::error("$buildid is its own parent");
                    return;
                }
                $this->UpdateBuild($build->parentid, $newErrors, $newWarnings, false);
                if ($buildid == $this->Id) {
                    $this->SetParentId($build->parentid);
                }
            }
        }, 5);
    }

    /** Update the testing numbers for our parent build. */
    public function UpdateParentTestNumbers($newFailed, $newNotRun, $newPassed)
    {
        if ($this->ParentId < 1) {
            return;
        }

        \DB::transaction(function () use ($newFailed, $newNotRun, $newPassed) {
            $numFailed = 0;
            $numNotRun = 0;
            $numPassed = 0;

            $parent = \DB::table('build')
                ->where('id', $this->ParentId)
                ->lockForUpdate()
                ->first();

            // Don't let the -1 default value screw up our math.
            $parent_testfailed = self::ConvertMissingToZero($parent->testfailed);
            $parent_testnotrun = self::ConvertMissingToZero($parent->testnotrun);
            $parent_testpassed = self::ConvertMissingToZero($parent->testpassed);

            $numFailed = $newFailed + $parent_testfailed;
            $numNotRun = $newNotRun + $parent_testnotrun;
            $numPassed = $newPassed + $parent_testpassed;

            \DB::table('build')
                ->where('id', $this->ParentId)
                ->update([
                    'testnotrun' => $numNotRun,
                    'testfailed' => $numFailed,
                    'testpassed' => $numPassed,
                ]);

            // NOTE: as far as I can tell, build.testtimestatusfailed isn't used,
            // so for now it isn't being updated for parent builds.
        }, 5);
    }

    /** Get/Set number of configure warnings for this build. */
    /**
     * @return int|null
     */
    public function GetNumberOfConfigureWarnings()
    {
        if ($this->BuildConfigure) {
            return $this->BuildConfigure->NumberOfWarnings;
        }

        $stmt = $this->PDO->prepare(
            'SELECT configurewarnings FROM build WHERE id = ?');
        if (!pdo_execute($stmt, [$this->Id])) {
            return null;
        }
        $num_warnings = $stmt->fetchColumn();
        if ($num_warnings == -1) {
            $num_warnings = 0;
        }
        return $num_warnings;
    }


    public function SetNumberOfConfigureWarnings($numWarnings)
    {
        if (!$this->Id || !is_numeric($this->Id)) {
            return;
        }

        $stmt = $this->PDO->prepare(
            'UPDATE build SET configurewarnings = ? WHERE id = ?');
        pdo_execute($stmt, [$numWarnings, $this->Id]);
    }

    /** Set number of configure errors for this build. */
    public function SetNumberOfConfigureErrors($numErrors)
    {
        if (!$this->Id || !is_numeric($this->Id)) {
            return;
        }

        $stmt = $this->PDO->prepare(
            'UPDATE build SET configureerrors = ? WHERE id = ?');
        pdo_execute($stmt, [$numErrors, $this->Id]);

        // Should we post configure errors to a pull request?
        if (!empty($this->PullRequest) && $numErrors > 0) {
            $message = "$this->Name failed to configure";
            $config = Config::getInstance();
            $url = $config->getBaseUrl() .
                "/build/{$this->Id}/configure";
            $this->NotifyPullRequest($message, $url);
        }
    }

    /**
     * @return int|null
     */
    public function GetNumberOfConfigureErrors()
    {
        if ($this->BuildConfigure) {
            return $this->BuildConfigure->NumberOfErrors;
        }
        $stmt = $this->PDO->prepare(
            'SELECT configureerrors FROM build WHERE id = ?');
        if (!pdo_execute($stmt, [$this->Id])) {
            return null;
        }
        $num_errors = $stmt->fetchColumn();
        if ($num_errors == -1) {
            $num_errors = 0;
        }
        return $num_errors;
    }

    /**
     * Update the tally of configure errors & warnings for this build's
     * parent.
     **/
    public function UpdateParentConfigureNumbers($newWarnings, $newErrors)
    {
        $this->SetParentId($this->LookupParentBuildId());
        if ($this->ParentId < 1) {
            return;
        }

        \DB::transaction(function () use ($newWarnings, $newErrors) {
            $numErrors = 0;
            $numWarnings = 0;

            $parent = \DB::table('build')
                ->where('id', $this->ParentId)
                ->lockForUpdate()
                ->first();

            // Don't let the -1 default value screw up our math.
            $parent_configureerrors = self::ConvertMissingToZero($parent->configureerrors);
            $parent_configurewarnings = self::ConvertMissingToZero($parent->configurewarnings);

            $numErrors = $newErrors + $parent_configureerrors;
            $numWarnings = $newWarnings + $parent_configurewarnings;

            \DB::table('build')
                ->where('id', $this->ParentId)
                ->update([
                    'configureerrors' => $numErrors,
                    'configurewarnings' => $numWarnings,
                ]);
        }, 5);
    }

    /** Get/set pull request for this build. */
    public function GetPullRequest()
    {
        return $this->PullRequest;
    }

    /**
     * @param $pr
     */
    public function SetPullRequest($pr)
    {
        $this->PullRequest = $pr;
    }

    /**
     * @param $message
     * @param $url
     */
    private function NotifyPullRequest($message, $url)
    {
        // Figure out if we should notify this build or its parent.
        $idToNotify = $this->Id;
        if ($this->ParentId > 0) {
            $idToNotify = $this->ParentId;
        }

        // Return early if this build already posted a comment on this PR.
        $notified = true;
        $stmt = $this->PDO->prepare(
            'SELECT notified FROM build WHERE id = ?');
        pdo_execute($stmt, [$idToNotify]);
        $notified = $stmt->fetchColumn();
        if ($notified) {
            return;
        }

        // Mention which SubProject caused this error (if any).
        if ($this->GetSubProjectName()) {
            $message .= " during $this->SubProjectName";
        }
        $message .= '.';

        // Post the PR comment & mark this build as 'notified'.
        post_pull_request_comment($this->ProjectId, $this->PullRequest,
            $message, $url);
        $stmt = $this->PDO->prepare(
            "UPDATE build SET notified='1' WHERE id = ?");
        pdo_execute($stmt, [$idToNotify]);
    }

    protected function UpdateDuration($field, $duration, $update_parent = true)
    {
        if ($duration === 0) {
            return;
        }

        if (!$this->Id || !is_numeric($this->Id) || !$this->Exists()) {
            return;
        }

        \DB::transaction(function () use ($field, $duration, $update_parent) {
            $build_row = \DB::table('build')
                ->where('id', $this->Id)
                ->lockForUpdate()
                ->first();

            // Update duration of specified step for this build.
            \DB::table('build')
                ->where('id', $this->Id)
                ->increment("{$field}duration", $duration);

            if (!$update_parent) {
                return;
            }

            // If this is a child build, add this duration to the parent's sum.
            $this->SetParentId($this->LookupParentBuildId());
            if ($this->ParentId > 0) {
                $build_row = \DB::table('build')
                    ->where('id', $this->ParentId)
                    ->lockForUpdate()
                    ->first();

                // Update duration of specified step for this build.
                \DB::table('build')
                    ->where('id', $this->ParentId)
                    ->increment("{$field}duration", $duration);
            }
        }, 5);
    }

    /**
     * @param $duration
     * @param bool $update_parent
     */
    public function SetConfigureDuration($duration, $update_parent = true)
    {
        return $this->UpdateDuration('configure', $duration, $update_parent);
    }

    /**
     * @param $duration
     * @param bool $update_parent
     */
    public function UpdateBuildDuration($duration, $update_parent = true)
    {
        return $this->UpdateDuration('build', $duration, $update_parent);
    }

    public function UpdateTestDuration($duration, $update_parent=true)
    {
        return $this->UpdateDuration('test', $duration, $update_parent);
    }

    // Return the dashboard date (in Y-m-d format) for this build.
    public function GetDate()
    {
        if (!$this->Id || !is_numeric($this->Id)) {
            return date(FMT_DATE);
        }
        $this->FillFromId($this->Id);
        $this->GetProject()->Fill();
        return TestingDay::get($this->Project, $this->StartTime);
    }

    /** Return whether or not this build has been marked as done. */
    public function GetDone()
    {
        if (empty($this->Id)) {
            return false;
        }

        if (!empty($this->Done)) {
            return $this->Done;
        }

        $stmt = $this->PDO->prepare('SELECT done FROM build WHERE id = ?');
        if (!pdo_execute($stmt, [$this->Id])) {
            return false;
        }

        $this->Done = $stmt->fetchColumn();
        return $this->Done;
    }

    /** Set (or unset) the done bit in the database for this build. */
    public function MarkAsDone($done)
    {
        $done_stmt = $this->PDO->prepare(
            'UPDATE build SET done = :done WHERE id = :buildid');
        pdo_execute($done_stmt, [':done' => $done, ':buildid' => $this->Id]);
    }

    /** Remove this build if it exists and has been marked as done.
     * This is called by XML handlers when a new replacement
     * submission is received.
     **/
    public function RemoveIfDone()
    {
        if (!$this->Exists() || !$this->GetDone()) {
            return false;
        }

        remove_build($this->Id);
        $this->Id = 0;
        return true;
    }

    /** Generate a UUID from the specified build details. */
    public static function GenerateUuid($stamp, $name, $siteid, $projectid,
        $subprojectname)
    {
        $input_string =
            $stamp . '_' . $name . '_' . $siteid . '_' . '_' .
            $projectid . '_' . $subprojectname;
        return md5($input_string);
    }

    /** Get/set the parentid for this build. */
    public function GetParentId()
    {
        return $this->ParentId;
    }

    /**
     * @param $parentid
     */
    public function SetParentId($parentid)
    {
        if ($parentid > 0 && $parentid == $this->Id) {
            add_log("Attempt to mark build $this->Id as its own parent",
                'Build::SetParentId', LOG_ERR,
                $this->ProjectId, $this->Id,
                ModelType::BUILD, $this->Id);
            return;
        }
        $this->ParentId = $parentid;
    }


    /* Get the beginning and the end of the testing day for this build
     * in DATETIME format.
     */
    public function ComputeTestingDayBounds()
    {
        if ($this->ProjectId < 1) {
            return false;
        }

        if (isset($this->BeginningOfDay) && isset($this->EndOfDay)) {
            return true;
        }

        $build_date = $this->GetDate();
        $this->GetProject()->Fill();
        list($this->BeginningOfDay, $this->EndOfDay) =
            $this->Project->ComputeTestingDayBounds($build_date);
        return true;
    }

    /**
     * Get all errors, including warnings, for all children builds of this build.
     *
     * @param int $fetchStyle
     * @return array|bool
     */
    public function GetErrorsForChildren($fetchStyle = PDO::FETCH_ASSOC)
    {
        if (!$this->Id) {
            add_log('Id not set', 'Build::GetErrorsForChildren', LOG_WARNING);
            return false;
        }

        $sql = '
            SELECT sp2b.subprojectid, sp.name subprojectname, be.*
            FROM builderror be
            JOIN build AS b
                ON b.id = be.buildid
            JOIN subproject2build AS sp2b
                ON sp2b.buildid = be.buildid
            JOIN subproject AS sp
                ON sp.id = sp2b.subprojectid
            WHERE b.parentid = ?
        ';

        $query = $this->PDO->prepare($sql);
        pdo_execute($query, [$this->Id]);

        return $query->fetchAll($fetchStyle);
    }

    /**
     * Get all failures, including warnings, for all children builds of this build.
     *
     * @param int $fetchStyle
     * @return array|bool
     */
    public function GetFailuresForChildren($fetchStyle = PDO::FETCH_ASSOC)
    {
        if (!$this->Id) {
            add_log('Id not set', 'Build::GetFailuresForChildren', LOG_WARNING);
            return false;
        }

        $sql = '
            SELECT
                bf.id,
                bf.sourcefile,
                bfd.language,
                bfd.targetname,
                bfd.outputfile,
                bfd.outputtype,
                bf.workingdirectory,
                bfd.stderror,
                bfd.stdoutput,
                bfd.type,
                bfd.exitcondition,
                sp2b.subprojectid,
                sp.name subprojectname
             FROM buildfailure AS bf
             LEFT JOIN buildfailuredetails AS bfd
                ON (bfd.id=bf.detailsid)
            JOIN subproject2build AS sp2b
                ON bf.buildid = sp2b.buildid
            JOIN subproject AS sp
                ON sp.id = sp2b.subprojectid
            JOIN build b on bf.buildid = b.id
            WHERE b.parentid = ?
        ';

        $query = $this->PDO->prepare($sql);

        pdo_execute($query, [$this->Id]);

        return $query->fetchAll($fetchStyle);
    }

    /**
     * Return a SubProject build for a particular parent if it exists.
     */
    public static function GetSubProjectBuild($parentid, $subprojectid)
    {
        $pdo = Database::getInstance()->getPdo();
        $stmt = $pdo->prepare(
            'SELECT b.id FROM build b
            JOIN subproject2build sp2b ON (sp2b.buildid = b.id)
            WHERE b.parentid = ? AND sp2b.subprojectid = ?');
        pdo_execute($stmt, [$parentid, $subprojectid]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $build = new Build();
        $build->Id = $row['id'];
        $build->FillFromId($build->Id);
        return $build;
    }

    /**
     * Returns the current Build's Site property. This method lazily loads the Site if no such
     * object exists.
     *
     * @return Site
     */
    public function GetSite()
    {
        if (!$this->Site) {
            $this->Site = new Site();
            $this->Site->Id = $this->SiteId;
        }
        return $this->Site;
    }

    /**
     * Sets the current Build's Site property.
     *
     * @param Site $site
     */
    public function SetSite(Site $site)
    {
        $this->Site = $site;
    }

    /**
     * Given a $buildtest, this method adds a BuildTest to the current Build's TestCollection.
     *
     * @param BuildTest $test
     * @return $this
     */
    public function AddTest(\App\Models\BuildTest $buildtest)
    {
        $this->TestCollection->put($buildtest->test->name, $buildtest);
        return $this;
    }

    /**
     * Return the current Build's TestCollection.
     *
     * @return TestCollection
     */
    public function GetTestCollection()
    {
        return $this->TestCollection;
    }

    /**
     * Return the Id of the Build matching the given $uuid,
     * or FALSE if no such build exists.
     */
    public static function GetIdFromUuid($uuid)
    {
        $pdo = Database::getInstance()->getPdo();
        $stmt = $pdo->prepare(
            'SELECT id FROM build WHERE uuid = ?');
        pdo_execute($stmt, [$uuid]);
        return $stmt->fetchColumn();
    }

    /**
     * Insert this build if it doesn't already exist.
     * If a build was created or an existing build was found,
     * this->Id gets set to a valid value.
     * Returns TRUE if a build was created, FALSE otherwise.
     * $this is expected to have Stamp, Name, SiteId, and ProjectId set.
     */
    public function AddBuild($nbuilderrors = -1, $nbuildwarnings = -1)
    {
        // Compute a uuid for this build if necessary.
        if (!$this->Uuid) {
            $this->Uuid = Build::GenerateUuid($this->Stamp, $this->Name,
                $this->SiteId, $this->ProjectId, $this->SubProjectName);
        }

        // Check if a build with this uuid already exists.
        $id = Build::GetIdFromUuid($this->Uuid);
        if ($id) {
            $this->Id = $id;
            return false;
        }

        // Set ParentId if this is a SubProject build.
        $justCreatedParent = false;
        if ($this->SubProjectName) {
            $this->SetParentId($this->LookupParentBuildId());
            if ($this->ParentId == 0) {
                // Parent build doesn't exist yet, create it here.
                $justCreatedParent = $this->CreateParentBuild($nbuilderrors, $nbuildwarnings);
            }
        }

        // Make sure this build has a type.
        if (strlen($this->Type) == 0) {
            $this->Type = extract_type_from_buildstamp($this->Stamp);
            if (!$this->Type) {
                $this->Type = '';
            }
        }

        // Build doesn't exist yet, create it here.
        $build_created = false;
        try {
            \DB::transaction(function () use ($nbuilderrors, $nbuildwarnings, &$build_created) {
                $new_id = \DB::table('build')->insertGetId([
                    'siteid'         => $this->SiteId,
                    'projectid'      => $this->ProjectId,
                    'stamp'          => $this->Stamp,
                    'name'           => $this->Name,
                    'type'           => $this->Type,
                    'generator'      => $this->Generator,
                    'starttime'      => $this->StartTime,
                    'endtime'        => $this->EndTime,
                    'submittime'     => $this->SubmitTime,
                    'command'        => $this->Command,
                    'log'            => $this->Log,
                    'builderrors'    => $nbuilderrors,
                    'buildwarnings'  => $nbuildwarnings,
                    'parentid'       => $this->ParentId,
                    'uuid'           => $this->Uuid,
                    'changeid'       => $this->PullRequest
                ]);
                $build_created = true;
                $this->Id = $new_id;
                $this->AssignToGroup();
            });
        } catch (\Exception $e) {
            // This error might be due to a unique key violation on the UUID.
            // Check again for a previously existing build.
            $existing_id = Build::GetIdFromUuid($this->Uuid);
            if ($existing_id) {
                $this->Id = $existing_id;
                $build_created = false;
            } else {
                // Otherwise log the error and return false.
                report($e);
                return false;
            }
        }

        $this->SaveInformation();

        if (!$build_created) {
            return false;
        }

        if ($this->ParentId > 0 && !$justCreatedParent) {
            // Update parent's tally of total build errors & warnings.
            $this->UpdateBuild($this->ParentId, $nbuilderrors, $nbuildwarnings);
        } elseif ($this->ParentId > 0) {
            // If we just created a child build, associate it with
            // the parent's updates (if any).
            BuildUpdate::AssignUpdateToChild(intval($this->Id), intval($this->ParentId));
        }

        return true;
    }

    public function AssignToGroup()
    {
        // Return early if this build already belongs to a group.
        $existing_groupid_row = \DB::table('build2group')->where('buildid', $this->Id)->first();
        if ($existing_groupid_row) {
            $this->GroupId = $existing_groupid_row->groupid;
            return false;
        }

        // Find and record the groupid for this build.
        $buildGroup = new BuildGroup();
        $this->GroupId = $buildGroup->GetGroupIdFromRule($this);
        \DB::table('build2group')->insertOrIgnore([
            ['groupid' => $this->GroupId, 'buildid' => $this->Id],
        ]);

        // Associate the parent with this build's group if necessary.
        if ($this->ParentId > 0) {
            $existing_parent_groupid_row = \DB::table('build2group')->where('buildid', $this->ParentId)->first();
            if (!$existing_parent_groupid_row) {
                \DB::table('build2group')->insertOrIgnore([
                        ['groupid' => $this->GroupId, 'buildid' => $this->ParentId],
                ]);
            }
        }

        // Add the subproject2build relationship if necessary.
        if ($this->SubProjectId) {
            \DB::table('subproject2build')->insertOrIgnore([
                ['subprojectid' => $this->SubProjectId, 'buildid' => $this->Id],
            ]);
        }
    }

    /**
     * @return string
     */
    public function GetBuildSummaryUrl()
    {
        $base = Config::getInstance()->getBaseUrl();
        return "{$base}/build/{$this->Id}";
    }

    /**
     * @return string
     */
    public function GetBuildErrorUrl()
    {
        $base = Config::getInstance()->getBaseUrl();
        return "{$base}/viewBuildError.php?buildid={$this->Id}";
    }

    /**
     * @return string
     */
    public function GetTestUrl()
    {
        $base = Config::getInstance()->getBaseUrl();
        return "{$base}/viewTest.php?buildid={$this->Id}";
    }

    public static function ConvertMissingToZero($value)
    {
        if ($value == -1) {
            $value = 0;
        }
        return $value;
    }

    /**
     * Returns the current Build's BuildConfigure property. This method lazily loads the
     * BuildConfigure object if none exists.
     *
     * @return BuildConfigure
     */
    public function GetBuildConfigure()
    {
        if (!$this->BuildConfigure) {
            $this->BuildConfigure = new BuildConfigure();
            $this->BuildConfigure->BuildId = $this->Id;
        }
        return $this->BuildConfigure;
    }

    /**
     * Sets the current Build's BuildConfigure property and ensures that the BuildConfigure's
     * BuildId property is set with the current Build's Id property.
     *
     * @param BuildConfigure $buildConfigure
     */
    public function SetBuildConfigure(BuildConfigure $buildConfigure)
    {
        $buildConfigure->BuildId = $this->Id;
        $this->BuildConfigure = $buildConfigure;
    }

    /**
     * Returns the current Build's Project object. This method lazily loads the Project if none
     * exists.
     *
     * @return Project
     */
    public function GetProject()
    {
        if (!$this->Project) {
            $this->Project = new Project();
            $this->Project->Id = $this->ProjectId;
        }
        return $this->Project;
    }

    /**
     * Sets the current Build's Project property.
     *
     * @param Project $project
     */
    public function SetProject(Project $project)
    {
        $this->Project = $project;
    }

    /**
     * Returns the current Build's TestFailedCount property.
     *
     * @return mixed
     */
    public function GetTestFailedCount()
    {
        return $this->TestFailedCount;
    }

    /**
     * Returns the current Build's Type property.
     *
     * @return string
     */
    public function GetBuildType()
    {
        return $this->Type;
    }

    /**
     * This method returns an array of all of the authors who are responsible for changes made
     * to the current Build.
     *
     * @return array
     */
    public function GetCommitAuthors()
    {
        // note: Per Zack: Depending on the type of submission (i.e. test, build error, etc)
        // this information may not yet be available as it is contained in the update xml
        // file submission.

        if (!$this->CommitAuthors) {
            $db = Database::getInstance();
            $sql = '
                SELECT
                    author,
                    email,
                    committeremail
                FROM
                    updatefile AS uf,
                    build2update AS b2u
                WHERE b2u.updateid = uf.updateid
                AND b2u.buildid = :buildId
            ';
            $stmt = $this->PDO->prepare($sql);
            $stmt->bindParam(':buildId', $this->Id);
            if ($db->execute($stmt)) {
                $authors = [];
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $hasAuthor = !empty($row['email']);
                    $hasCommitter = !empty($row['committeremail']);

                    if ($hasAuthor) {
                        $authors[] = $row['email'];
                    }

                    if ($hasCommitter) {
                        $authors[] = $row['committeremail'];
                    }

                    if ($hasAuthor === false
                        && $hasCommitter === false
                        && filter_var($row['author'], FILTER_VALIDATE_EMAIL)
                    ) {
                        $authors[] = $row['author'];
                    }
                }
                $this->CommitAuthors = array_unique($authors);
            }
        }
        return $this->CommitAuthors;
    }

    /**
     * Given a $subscriber this method returns true if the current Build has contains changes
     * authored by $subscriber and false if no such changes by the author exist.
     *
     * @param SubscriberInterface $subscriber
     * @return bool
     */
    public function AuthoredBy(SubscriberInterface $subscriber)
    {
        $authoredBy = false;
        $authors = $this->GetCommitAuthors();
        $credentials = $subscriber->getUserCredentials();
        $credentials[] = $subscriber->getAddress();

        foreach (array_unique($credentials) as $credential) {
            if (in_array($credential, $authors)) {
                $authoredBy = true;
                break;
            }
        }

        return $authoredBy;
    }

    /**
     * Returns the current Build's LabelCollection.
     * @return Collection
     */
    public function GetLabelCollection()
    {
        return $this->LabelCollection;
    }

    /**
     * Given a $filter of value ERROR or WARNING it will return the number of BuildErrors
     * whose Type property matches that of the $filter. If no $filter is provided it returns
     * the number of all the current Build's BuildErrors.
     *
     * @param null $filter
     * @return int
     */
    public function GetBuildErrorCount($filter = null)
    {
        $count = 0;
        if (is_null($filter)) {
            $count = count($this->Errors);
        } else {
            /** @var BuildError $error */
            foreach ($this->Errors as $error) {
                if ($error->Type == $filter) {
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * Given a $filter of value ERROR or WARNING it will return the number of BuildFailures
     * whose Type property matches that of the $filter. If no $filter is provided it returns
     * the number of all the current Build's BuildFailures.
     *
     * @param null $filter
     * @return int
     */
    public function getBuildFailureCount($filter = null)
    {
        $count = 0;
        if (is_null($filter)) {
            return count($this->Failures);
        } else {
            foreach ($this->Failures as $fail) {
                if ($fail->Type == $filter) {
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * Adds a DynamicAnalysis object to the Build's DynamicAnalysisCollection.
     *
     * @param DynamicAnalysis $analysis
     * @return $this
     */
    public function AddDynamicAnalysis(DynamicAnalysis $analysis)
    {
        $analyses = $this->GetDynamicAnalysisCollection();
        $analyses->add($analysis);
        return $this;
    }

    /**
     * Returns the current Build's DynamicAnalysisCollection object. This method lazily loads the
     * DynamicAnalysisCollection if none exists.
     *
     * @return DynamicAnalysisCollection
     */
    public function GetDynamicAnalysisCollection()
    {
        if (!$this->DynamicAnalysisCollection) {
            $this->DynamicAnalysisCollection = new DynamicAnalysisCollection();
        }
        return $this->DynamicAnalysisCollection;
    }

    /**
     * Returns the BuildEmailCollection object. This method lazily loads a CollectionCollection
     * object if none exists.
     *
     * @param $category
     * @return BuildEmailCollection;
     */
    public function GetBuildEmailCollection()
    {
        if (!$this->Id) {
            return new BuildEmailCollection();
        }

        if (!$this->BuildEmailCollection) {
            $this->BuildEmailCollection = BuildEmail::GetEmailSentForBuild($this->Id);
        }

        return $this->BuildEmailCollection;
    }

    /**
     * Sets the current build's BuildEmailCollection object. This method lazily loads a
     * CollectionCollection object as the current Build's BuildEmailCollection property if none
     * exists.
     *
     * @param BuildEmailCollection $collection
     */
    public function SetBuildEmailCollection(BuildEmailCollection $collection)
    {
        $this->BuildEmailCollection = $collection;
    }

    /**
     * Sets the build's BuildUpdate object.
     *
     * @param BuildUpdate $buildUpdate
     */
    public function SetBuildUpdate(BuildUpdate $buildUpdate)
    {
        $this->BuildUpdate = $buildUpdate;
    }

    /**
     * Returns the BuildUpdate object.
     *
     * @return BuildUpdate|null
     */
    public function GetBuildUpdate()
    {
        return $this->BuildUpdate;
    }

    /**
     * Returns a data structure representing the difference between the previous build and
     * the current build.
     *
     * @return array|bool
     * TODO: Create a diff class
     */
    public function GetDiffWithPreviousBuild()
    {
        if (!$this->Id) {
            return false;
        }

        if (!$this->ErrorDifferences) {
            if ($this->GetPreviousBuildId() === 0) {
                $warnings = array_reduce($this->Errors, function ($count, $item) {
                    $count += $item->Type === Build::TYPE_WARN ? 1 : 0;
                    return $count;
                }, 0);
                $errors = array_reduce($this->Errors, function ($count, $item) {
                    $count += $item->Type === Build::TYPE_ERROR ? 1 : 0;
                    return $count;
                }, 0);
                $passed = array_reduce($this->TestCollection->toArray(), function ($count, $test) {
                    $count += $test['status'] === Test::PASSED ? 1 : 0;
                    return $count;
                }, 0);
                $failed = array_reduce($this->TestCollection->toArray(), function ($count, $test) {
                    $count += $test['status'] === Test::FAILED ? 1 : 0;
                    return $count;
                }, 0);
                $notrun = array_reduce($this->TestCollection->toArray(), function ($count, $test) {
                    $count += $test['status'] === Test::NOTRUN ? 1 : 0;
                    return $count;
                }, 0);

                $this->ErrorDifferences = [
                    'BuildWarning' => [
                        'new' => $warnings,
                        'fixed' => 0,
                    ],
                    'BuildError' => [
                        'new' => $errors,
                        'fixed' => 0,
                    ],
                    'Configure' => [
                        'errors' => ($this->BuildConfigure ? $this->BuildConfigure->NumberOfErrors : 0),
                        'warnings' => ($this->BuildConfigure ? $this->BuildConfigure->NumberOfWarnings : 0),
                    ] ,
                    'TestFailure' => [
                        'passed' => [
                            'new' => $passed,
                            'broken' => 0,
                        ],
                        'failed' => [
                            'new' => $failed,
                            'fixed' => 0,
                        ],
                        'notrun' => [
                            'new' => $notrun,
                            'fixed' => 0,
                        ],
                    ],
                ];
            } else {
                $diff = $this->GetErrorDifferences();
                $this->ErrorDifferences = [
                    'BuildWarning' => [
                        'new' => $diff['buildwarningspositive'],
                        'fixed' => $diff['buildwarningsnegative'],
                    ],
                    'BuildError' => [
                        'new' => $diff['builderrorspositive'],
                        'fixed' => $diff['builderrorsnegative'],
                    ],
                    'Configure' => [
                        'errors' => $diff['configureerrors'],
                        'warnings' => $diff['configurewarnings'],
                    ] ,
                    'TestFailure' => [
                        'passed' => [
                            'new' => $diff['testpassedpositive'],
                            'broken' => $diff['testpassednegative']
                        ],
                        'failed' => [
                            'new' => $diff['testfailedpositive'],
                            'fixed' => $diff['testfailednegative'],
                        ],
                        'notrun' => [
                            'new' => $diff['testnotrunpositive'],
                            'fixed' => $diff['testnotrunnegative']
                        ],
                    ],
                ];
            }
        }
        return $this->ErrorDifferences;
    }

    protected function SaveInformation()
    {
        // Save the information
        if (!empty($this->Information)) {
            if ($this->ParentId > 0) {
                $this->Information->BuildId = $this->ParentId;
            } else {
                $this->Information->BuildId = $this->Id;
            }
            $this->Information->Save();
        }
    }
}
