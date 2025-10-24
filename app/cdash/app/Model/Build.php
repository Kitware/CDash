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

use App\Models\BasicBuildAlert;
use App\Models\Build as EloquentBuild;
use App\Models\BuildUpdateFile;
use App\Models\Site;
use App\Models\Test;
use App\Utils\DatabaseCleanupUtils;
use App\Utils\RepositoryUtils;
use App\Utils\SubmissionUtils;
use App\Utils\TestDiffUtil;
use App\Utils\TestingDay;
use CDash\Collection\BuildEmailCollection;
use CDash\Collection\DynamicAnalysisCollection;
use CDash\Database;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use PDO;
use PDOStatement;

class Build
{
    public const TYPE_ERROR = EloquentBuild::TYPE_ERROR;
    public const TYPE_WARN = EloquentBuild::TYPE_WARN;
    public const STATUS_NEW = 1;

    public const PARENT_BUILD = -1;
    public const STANDALONE_BUILD = 0;

    public $Id;
    public $SiteId = 0;
    public $ProjectId = 0;
    private int $ParentId = 0;
    private string $Uuid = '';
    private string $Stamp = '';
    public string $Name = '';
    public string $Type = '';
    public string $Generator = '';
    public string $StartTime = '1980-01-01 00:00:00';
    public string $EndTime = '1980-01-01 00:00:00';
    public string $SubmitTime = '1980-01-01 00:00:00';
    public string $Command = '';
    public ?string $OSName = null;
    public ?string $OSPlatform = null;
    public ?string $OSRelease = null;
    public ?string $OSVersion = null;
    public ?string $CompilerName = null;
    public ?string $CompilerVersion = null;
    public int $BuildErrorCount;
    public int $TestFailedCount;

    // For the moment we accept only one group per build
    public int $GroupId = 0;

    public array $Errors = [];
    private array $Failures = [];
    public array $MissingTests;

    public $SubProjectId;
    public $SubProjectName;
    public bool $Append = false;
    private bool $Done;

    // Only the build.xml has information about errors and warnings
    // when the InsertErrors is false the build is created but not the errors and warnings
    public bool $InsertErrors = true;

    // Used to comment on pull/merge requests when something goes wrong
    // with this build.
    private $PullRequest = '';

    // Used to mark whether this object already has its fields set.
    public bool $Filled = false;

    // Not set by FillFromId(), but cached the first time they are
    // computed.
    public string $BeginningOfDay;
    public string $EndOfDay;

    private Collection $TestCollection;
    private $PDO;
    private $Site;
    private $BuildUpdate;
    private Project $Project;
    private array $CommitAuthors = [];
    private $BuildConfigure;
    private $LabelCollection;
    private DynamicAnalysisCollection $DynamicAnalysisCollection;
    private BuildEmailCollection $BuildEmailCollection;

    // TODO: ErrorDiffs appears to be no longer used?
    private array $ErrorDifferences;

    /**
     * Build constructor.
     */
    public function __construct()
    {
        $this->TestCollection = collect();

        $this->LabelCollection = collect();

        $this->PDO = Database::getInstance()->getPdo();
    }

    public function IsParentBuild(): bool
    {
        return $this->ParentId === -1;
    }

    public function AddError($error): void
    {
        $error->BuildId = $this->Id;
        $this->Errors[] = $error;
    }

    public function AddLabel($label): void
    {
        $label->BuildId = $this->Id;
        $this->LabelCollection->put($label->Text, $label);
    }

    public function SetStamp(string $stamp): void
    {
        $this->Stamp = $stamp;
        if (strlen($this->Type) === 0) {
            $this->Type = self::extract_type_from_buildstamp($this->Stamp);
        }
    }

    public function GetStamp(): string
    {
        return $this->Stamp;
    }

    /** Set the subproject id */
    public function SetSubProject($subproject): int|bool
    {
        if (!empty($this->SubProjectId)) {
            return (int) $this->SubProjectId;
        }

        if (empty($subproject)) {
            return false;
        }

        if (empty($this->ProjectId)) {
            Log::error('ProjectId not set', [
                'projectid' => $this->ProjectId,
                'buildid' => $this->Id,
            ]);
            return false;
        }

        $this->SubProjectName = $subproject;

        $stmt = $this->PDO->prepare(
            "SELECT id FROM subproject WHERE name = ? AND projectid = ? AND
            endtime='1980-01-01 00:00:00'");
        if (!pdo_execute($stmt, [$subproject, $this->ProjectId])) {
            return false;
        }

        $label = new Label();
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
            $this->SubProjectId = (int) $subprojectid;
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
        $Label = new Label();
        $Label->Text = $subProject->GetName();
        $Label->Insert();

        Log::debug('New subproject detected: ' . $subproject, [
            'projectid' => $this->ProjectId,
            'buildid' => $this->Id,
        ]);
        return true;
    }

    /** Return the subproject name */
    public function GetSubProjectName(): string|false
    {
        if (empty($this->Id)) {
            return false;
        }

        if (!empty($this->SubProjectName)) {
            return $this->SubProjectName;
        }

        $subproject_name = EloquentBuild::findOrFail((int) $this->Id)->subProject->name ?? false;
        if ($subproject_name !== false) {
            $this->SubProjectName = $subproject_name;
        }
        return $subproject_name;
    }

    /**
     * Record the total execution time of all the tests performed by this build.
     **/
    public function SaveTotalTestsTime(): bool
    {
        if (!$this->Exists()) {
            return false;
        }

        // Calculate how much processor time was spent running this build's tests.
        $total_proc_time = 0.0;
        foreach ($this->TestCollection as $test) {
            $exec_time = (float) $test->time;
            $num_procs = 1.0;
            foreach ($test->measurements as $measurement) {
                if ($measurement->name === 'Processors') {
                    $num_procs *= $measurement->value;
                    break;
                }
            }
            $total_proc_time += ($exec_time * $num_procs);
        }

        $this->UpdateBuildTestTime($total_proc_time);

        // If this is a child build, add this exec time
        // to the parent's value.
        $this->SetParentId($this->LookupParentBuildId());
        if ($this->ParentId > 0) {
            $parent = new Build();
            $parent->Id = $this->ParentId;
            $parent->UpdateBuildTestTime($total_proc_time);
        }

        return true;
    }

    /** Extract the type from the build stamp */
    private static function extract_type_from_buildstamp($buildstamp): string
    {
        // We assume that the time stamp is always of the form
        // 20080912-1810-this-is-a-type
        if (!empty($buildstamp)) {
            return substr($buildstamp, strpos($buildstamp, '-', strpos($buildstamp, '-') + 1) + 1);
        }

        return '';
    }

    /**
     * Insert or update a record in the buildtesttime table.
     **/
    private function UpdateBuildTestTime(float $test_exec_time): void
    {
        DB::transaction(function () use ($test_exec_time): void {
            $buildtesttime_row =
            DB::table('buildtesttime')
                ->where('buildid', $this->Id)
                ->lockForUpdate()
                ->first();
            if ($buildtesttime_row) {
                // Add to the running total if an entry already exists for this build.
                DB::table('buildtesttime')
                    ->where('buildid', $this->Id)
                    ->update(['time' => $test_exec_time + $buildtesttime_row->time]);
            } else {
                // Otherwise insert a new record.
                DB::table('buildtesttime')->insert(
                    ['buildid' => $this->Id, 'time' => $test_exec_time]);
            }
        }, 5);
    }

    /** Update the end time */
    public function UpdateEndTime(string $end_time): bool
    {
        if (!$this->Id || !is_numeric($this->Id)) {
            return false;
        }

        return EloquentBuild::where('id', $this->Id)->update([
            'endtime' => $end_time,
        ]) > 0;
    }

    private function QuerySubProjectId(int $buildid): int|false
    {
        return EloquentBuild::findOrFail($buildid)->subProject->id ?? false;
    }

    /** Fill the current build information from the buildid */
    public function FillFromId($buildid): void
    {
        $buildid = (int) $buildid;

        if ($this->Filled) {
            // Already filled, no need to do it again.
            return;
        }

        $model = EloquentBuild::find($buildid);
        if ($model === null) {
            return;
        }

        $this->Name = $model->name;
        $this->SetStamp($model->stamp);
        $this->Type = $model->type;
        $this->StartTime = $model->starttime;
        $this->EndTime = $model->endtime;
        $this->SubmitTime = $model->submittime;
        $this->SiteId = $model->siteid;
        $this->ProjectId = $model->projectid;
        $this->SetParentId($model->parentid);
        $this->Done = $model->done;
        $this->Generator = $model->generator;
        $this->Command = $model->command;
        $this->BuildErrorCount = $model->builderrors;
        $this->TestFailedCount = $model->testfailed;

        $this->OSName = $model->osname;
        $this->OSPlatform = $model->osplatform;
        $this->OSRelease = $model->osrelease;
        $this->OSVersion = $model->osversion;
        $this->CompilerName = $model->compilername;
        $this->CompilerVersion = $model->compilerversion;

        $subprojectid = $this->QuerySubProjectId($buildid);
        if ($subprojectid) {
            $this->SubProjectId = $subprojectid;
        }

        $this->GroupId = (int) (DB::select('SELECT groupid FROM build2group WHERE buildid = ?', [$buildid])[0]->groupid ?? false);
        $this->Filled = true;
    }

    public static function MarshalResponseArray(Build $build, array $optional_values = []): array
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
    public function GetPreviousBuildId(?int $previous_parentid = null): int
    {
        if (!$this->Id) {
            return 0;
        }
        $this->FillFromId($this->Id);

        $previous_clause =
            'AND starttime < :starttime ORDER BY starttime DESC';
        $values_to_bind = [':starttime' => $this->StartTime];
        return $this->GetRelatedBuildId($previous_clause, $values_to_bind,
            $previous_parentid);
    }

    /** Get the next build id. */
    public function GetNextBuildId(?int $next_parentid = null): int
    {
        if (!$this->Id) {
            return 0;
        }
        $this->FillFromId($this->Id);

        $next_clause = 'AND starttime > :starttime ORDER BY starttime';
        $values_to_bind = [':starttime' => $this->StartTime];
        return $this->GetRelatedBuildId($next_clause, $values_to_bind, $next_parentid);
    }

    /** Get the most recent build id. */
    public function GetCurrentBuildId(?int $current_parentid = null): int
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
     *
     * @param array<string, string> $extra_values_to_bind
     **/
    private function GetRelatedBuildId(
        string $which_build_criteria,
        array $extra_values_to_bind = [],
        ?int $related_parentid = null,
    ): int {
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

            foreach (array_merge($values_to_bind, $extra_values_to_bind) as $parameter => $value) {
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

        $subproj_criteria = '';
        $parent_criteria = '';

        // If we know the parent of the build we're looking for, use that as our
        // search criteria rather than matching site, name, type, and project.
        if ($related_parentid) {
            $related_build_criteria = 'WHERE parentid = :parentid';
            $values_to_bind = [':parentid' => $related_parentid];
        }

        if ($this->SubProjectId) {
            $subproj_criteria =
                'AND subprojectid = :subprojectid';
            $values_to_bind['subprojectid'] = $this->SubProjectId;
        }
        if ($this->ParentId === Build::PARENT_BUILD) {
            // Only search for other parents.
            $parent_criteria = 'AND build.parentid = ' . Build::PARENT_BUILD;
        }

        $stmt = $this->PDO->prepare("
            SELECT id FROM build
            $related_build_criteria
            $subproj_criteria
            $parent_criteria
            $which_build_criteria
            LIMIT 1");

        foreach (array_merge($values_to_bind, $extra_values_to_bind) as $parameter => $value) {
            $stmt->bindValue($parameter, $value);
        }
        if (!pdo_execute($stmt)) {
            return 0;
        }

        $related_buildid = $stmt->fetchColumn();
        if (!$related_buildid) {
            return 0;
        }
        return (int) $related_buildid;
    }

    /**
     * Return the errors that have been resolved from this build.
     *
     * @todo This doesn't support getting resolved build errors across parent builds.
     **/
    public function GetResolvedBuildErrors(int $type): PDOStatement|false
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
        pdo_execute($stmt, [$this->GetPreviousBuildId(), $type, $this->Id, $type]);
        return $stmt;
    }

    /**
     * Returns all errors, including warnings, from the database, caches, and
     * returns the filtered results
     */
    public function GetErrors(array $propertyFilters = [], int $fetchStyle = PDO::FETCH_ASSOC): array|false
    {
        // This needs to take into account that this build may be a parent build
        if ($this->Errors === []) {
            if (!$this->Id) {
                Log::warning('BuildId not set', [
                    'projectid' => $this->ProjectId,
                    'buildid' => $this->Id,
                    'function' => 'Build::GetErrors',
                ]);
                return false;
            }

            if ($this->IsParentBuild()) {
                $errors = $this->GetErrorsForChildren($fetchStyle);
            } else {
                $result = BasicBuildAlert::where('buildid', $this->Id)
                    ->orderBy('logline')
                    ->get();
                $errors = $fetchStyle === PDO::FETCH_ASSOC ? $result->toArray() : $result->all();
            }

            if ($errors !== false) {
                $this->Errors = $errors;
            }
        }
        return $this->PropertyFilter($this->Errors, $propertyFilters);
    }

    /**
     * Returns all failures (errors), including warnings, for current build
     */
    public function GetFailures(array $propertyFilters = [], int $fetchStyle = PDO::FETCH_ASSOC): array|false
    {
        // This needs to take into account that this build may be a parent build
        if ($this->Failures === []) {
            if (!$this->Id) {
                Log::warning('BuildId not set', [
                    'projectid' => $this->ProjectId,
                    'buildid' => $this->Id,
                    'function' => 'Build::GetFailures',
                ]);
                return false;
            }

            if ($this->IsParentBuild()) {
                $failures = $this->GetFailuresForChildren($fetchStyle);
            } else {
                $buildFailure = new BuildFailure();
                $buildFailure->BuildId = $this->Id;
                $failures = $buildFailure->GetFailuresForBuild($fetchStyle);
            }

            if ($failures !== false) {
                $this->Failures = $failures;
            }
        }
        return $this->PropertyFilter($this->Failures, $propertyFilters);
    }

    /**
     * Apply filter to rows
     *
     * @param array<string,mixed> $filters
     */
    protected function PropertyFilter(array $rows, array $filters): array
    {
        return array_filter($rows, function ($row) use ($filters) {
            foreach ($filters as $prop => $value) {
                if (is_object($row)) {
                    if (!property_exists($row, $prop)) {
                        Log::warning("Cannot filter on {$prop}: property does not exist", [
                            'function' => 'Build::PropertyFilter',
                        ]);
                        continue;
                    }

                    if ($row->$prop != $value) {
                        return false;
                    }
                } elseif (is_array($row)) {
                    if (!array_key_exists($prop, $row)) {
                        Log::warning("Cannot filter on {$prop}: property does not exist", [
                            'function' => 'Build::PropertyFilter',
                        ]);
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
     *
     * @todo This doesn't support getting resolved build failures across parent builds.
     **/
    public function GetResolvedBuildFailures(int $type): PDOStatement
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

    public function GetConfigures(): PDOStatement|false
    {
        $stmt = null;
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
                    JOIN subproject sp ON sp.id = b.subprojectid
                    JOIN build b ON b.id = b2c.buildid
                    WHERE b.parentid = ?');
            } elseif (count($configure_rows) === 1) {
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
        }
        if (is_null($stmt)) {
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
    public function GetIdFromName(?string $subproject): int
    {
        // Make sure subproject name and id fields are set:
        //
        $this->SetSubProject($subproject);

        $query = EloquentBuild::where([
            'projectid' => $this->ProjectId,
            'siteid' => $this->SiteId,
            'name' => $this->Name,
            'stamp' => $this->Stamp,
        ]);

        if ((int) $this->SubProjectId !== 0) {
            $query = $query->where('subprojectid', $this->SubProjectId);
        } else {
            $query = $query->whereIn('parentid', [0, -1]);
        }

        $id = $query->first()->id ?? 0;

        if ($id > 0) {
            $this->Id = $id;
        }

        return $id;
    }

    public function InsertLabelAssociations(): bool
    {
        if (!$this->Id) {
            Log::error('No Build::Id - cannot call $label->Insert...', [
                'function' => 'Build::InsertLabelAssociations',
                'projectid' => $this->ProjectId,
            ]);
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

    public function Exists(): bool
    {
        if (!$this->Id) {
            return false;
        }

        return EloquentBuild::where('id', $this->Id)->exists();
    }

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
                if ((int) $error->Type === 0) {
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

        // Add label associations regardless of how Build::Save gets called:
        //
        $this->InsertLabelAssociations();

        // Should we post build errors to a pull request?
        if (!empty($this->PullRequest)) {
            $hasErrors = false;
            foreach ($this->Errors as $error) {
                if ((int) $error->Type === 0) {
                    $hasErrors = true;
                    break;
                }
            }

            if ($hasErrors) {
                $message = "$this->Name experienced errors";
                $url = url('/viewBuildError.php') . "?buildid=$this->Id";
                $this->NotifyPullRequest($message, $url);
            }
        }
        return true;
    }

    /** Helper function for test number accessors. */
    private function GetNumberOfTestsByField(string $field): int
    {
        if (!in_array($field, ['testpassed', 'testfailed', 'testnotrun'], true)) {
            throw new InvalidArgumentException('Invalid field specified.');
        }

        $model = EloquentBuild::find((int) $this->Id);
        return $model === null ? 0 : max($model->getAttribute($field), 0);
    }

    /** Get number of failed tests */
    public function GetNumberOfFailedTests(): int|false
    {
        return $this->GetNumberOfTestsByField('testfailed');
    }

    /** Get number of passed tests */
    public function GetNumberOfPassedTests(): int|false
    {
        return $this->GetNumberOfTestsByField('testpassed');
    }

    /** Get number of not run tests */
    public function GetNumberOfNotRunTests(): int|false
    {
        return $this->GetNumberOfTestsByField('testnotrun');
    }

    /** Update the test numbers */
    public function UpdateTestNumbers(int $numberTestsPassed, int $numberTestsFailed, int $numberTestsNotRun): void
    {
        $this->TestFailedCount = $numberTestsFailed;

        // If this is a subproject build, we also have to update its parents test numbers.
        $newFailed = $numberTestsFailed - $this->GetNumberOfFailedTests();
        $newNotRun = $numberTestsNotRun - $this->GetNumberOfNotRunTests();
        $newPassed = $numberTestsPassed - $this->GetNumberOfPassedTests();
        $this->SetParentId($this->LookupParentBuildId());
        $this->UpdateParentTestNumbers($newFailed, $newNotRun, $newPassed);

        EloquentBuild::where('id', $this->Id)->update([
            'testnotrun' => $numberTestsNotRun,
            'testfailed' => $numberTestsFailed,
            'testpassed' => $numberTestsPassed,
        ]);

        // Should we should post test failures to a pull request?
        if (!empty($this->PullRequest) && $numberTestsFailed > 0) {
            $message = "$this->Name experienced failing tests";
            $url = url('/viewTest.php') . "?onlyfailed&buildid=$this->Id";
            $this->NotifyPullRequest($message, $url);
        }
    }

    /**
     * Get missing tests' names relative to previous build
     *
     * @return array<int, string>
     */
    public function GetMissingTests(): array
    {
        if (!isset($this->MissingTests)) {
            $this->MissingTests = [];

            if (!$this->Id) {
                Log::error('BuildId is not set', [
                    'function' => 'Build::GetMissingTests',
                    'projectid' => $this->ProjectId,
                ]);
                return [];
            }

            $previous_build_tests = [];
            $current_build_tests = [];

            $previous_build = $this->GetPreviousBuildId();

            $sql = 'SELECT DISTINCT testname
                FROM build2test
                WHERE buildid=?
                ORDER BY testname
             ';

            foreach (DB::select($sql, [$previous_build]) as $test) {
                $previous_build_tests[] = $test->testname;
            }

            foreach (DB::select($sql, [$this->Id]) as $test) {
                $current_build_tests[] = $test->testname;
            }
            $this->MissingTests = array_diff($previous_build_tests, $current_build_tests);
        }

        return $this->MissingTests;
    }

    /**
     * Gut the number of missing tests relative to previous build
     **/
    public function GetNumberOfMissingTests(): int
    {
        if (!isset($this->MissingTests)) {
            // feels clumsy but necessary for testing :( (for the time being)
            $this->MissingTests = $this->GetMissingTests();
        }

        return count($this->MissingTests);
    }

    /**
     * Get this build's tests that match the supplied WHERE clause.
     */
    private function GetTests(string $criteria, int $maxitems = 0): array|false
    {
        if (!$this->Id) {
            Log::error('BuildId is not set', [
                'function' => 'Build::GetTests',
                'projectid' => $this->ProjectId,
            ]);
            return false;
        }

        $limit_clause = '';
        if ($maxitems > 0) {
            $limit_clause = "LIMIT $maxitems";
        }

        $sql = "
            SELECT b2t.testname AS name, b2t.id AS buildtestid, b2t.details
            FROM build2test b2t
            WHERE b2t.buildid = :buildid
            AND $criteria
            ORDER BY b2t.testname
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
     */
    public function GetPassedTests(int $maxitems = 0): array|false
    {
        $criteria = "b2t.status = 'passed'";
        return $this->GetTests($criteria, $maxitems);
    }

    /**
     * Get this build's tests that failed but did not timeout.
     */
    public function GetFailedTests(int $maxitems = 0): array|false
    {
        $criteria = "b2t.status = 'failed'";
        return $this->GetTests($criteria, $maxitems);
    }

    /**
     * Get this build's tests that failed the time status check.
     */
    public function GetFailedTimeStatusTests(int $maxitems = 0, int $max_time_status = 3): array|false
    {
        $criteria = "b2t.timestatus > $max_time_status";
        return $this->GetTests($criteria, $maxitems);
    }

    /**
     * Get this build's tests whose status is "Not Run" and whose details
     * is not 'Disabled'.
     */
    public function GetNotRunTests(int $maxitems = 0): array|false
    {
        $criteria = "b2t.status = 'notrun' AND b2t.details != 'Disabled'";
        return $this->GetTests($criteria, $maxitems);
    }

    /**
     * Get the errors differences for the build
     *
     * @return array{
     *     'builderrorspositive': int,
     *     'builderrorsnegative': int,
     *     'buildwarningspositive': int,
     *     'buildwarningsnegative': int,
     *     'configureerrors': int,
     *     'configurewarnings': int,
     *     'testpassedpositive': int,
     *     'testpassednegative': int,
     *     'testfailedpositive': int,
     *     'testfailednegative': int,
     *     'testnotrunpositive': int,
     *     'testnotrunnegative': int,
     * }|false
     */
    public function GetErrorDifferences(): array|false
    {
        if (!$this->Id) {
            Log::error('BuildId is not set', [
                'function' => 'Build::GetErrorDifferences',
                'projectid' => $this->ProjectId,
            ]);
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
            if ((int) $query_array['builderrortype'] === 0) {
                $diff['builderrorspositive'] = (int) $query_array['builderrorspositive'];
                $diff['builderrorsnegative'] = (int) $query_array['builderrorsnegative'];
            } else {
                $diff['buildwarningspositive'] = (int) $query_array['builderrorspositive'];
                $diff['buildwarningsnegative'] = (int) $query_array['builderrorsnegative'];
            }

            if ((int) $query_array['configureerrortype'] === 0) {
                $diff['configureerrors'] = (int) $query_array['configureerrors'];
            } else {
                $diff['configurewarnings'] = (int) $query_array['configureerrors'];
            }

            if ((int) $query_array['testerrortype'] === 2) {
                $diff['testpassedpositive'] = (int) $query_array['testerrorspositive'];
                $diff['testpassednegative'] = (int) $query_array['testerrorsnegative'];
            } elseif ((int) $query_array['testerrortype'] === 1) {
                $diff['testfailedpositive'] = (int) $query_array['testerrorspositive'];
                $diff['testfailednegative'] = (int) $query_array['testerrorsnegative'];
            } elseif ((int) $query_array['testerrortype'] === 0) {
                $diff['testnotrunpositive'] = (int) $query_array['testerrorspositive'];
                $diff['testnotrunnegative'] = (int) $query_array['testerrorsnegative'];
            }
        }

        // If some of the errors are not set default to zero
        $variables = [
            'builderrorspositive',
            'builderrorsnegative',
            'buildwarningspositive',
            'buildwarningsnegative',
            'configureerrors',
            'configurewarnings',
            'testpassedpositive',
            'testpassednegative',
            'testfailedpositive',
            'testfailednegative',
            'testnotrunpositive',
            'testnotrunnegative',
        ];
        foreach ($variables as $var) {
            if (!isset($diff[$var])) {
                $diff[$var] = 0;
            }
        }
        return $diff;
    }

    /** Compute the build errors differences */
    public function ComputeDifferences(): bool
    {
        if (!$this->Id) {
            Log::error('BuildId is not set', [
                'function' => 'Build::ComputeDifferences',
                'projectid' => $this->ProjectId,
            ]);
            return false;
        }

        $previousbuildid = $this->GetPreviousBuildId();
        if ($previousbuildid === 0) {
            return true;
        }
        SubmissionUtils::compute_error_difference($this->Id, $previousbuildid, 0); // errors
        SubmissionUtils::compute_error_difference($this->Id, $previousbuildid, 1); // warnings

        return true;
    }

    /** Compute the difference in configure warnings between this build and the
     *  previous one.
     *  TODO: we should probably also do configure errors here too.
     */
    public function ComputeConfigureDifferences(): bool
    {
        if (!$this->Id) {
            Log::error('Buildid is not set');
            return false;
        }

        $previousbuildid = $this->GetPreviousBuildId();
        if ($previousbuildid === 0) {
            return true;
        }

        // Look up the number of configure warnings for this build
        // and the previous one.
        $nwarnings = EloquentBuild::findOrFail((int) $this->Id)->configurewarnings;
        $npreviouswarnings = EloquentBuild::findOrFail($previousbuildid)->configurewarnings;

        DB::transaction(function () use ($nwarnings, $npreviouswarnings): void {
            // Check if a diff already exists for this build.
            $existing_diff_row = DB::table('configureerrordiff')
                ->where('buildid', $this->Id)
                ->lockForUpdate()
                ->first();

            $existing_diff = (int) $existing_diff_row?->difference;

            // Don't log if no diff.
            $warningdiff = $nwarnings - $npreviouswarnings;
            if ($warningdiff === 0 && $existing_diff === 0) {
                return;
            }

            // UPDATE or INSERT a new record as necessary.
            if ($existing_diff_row) {
                DB::table('configureerrordiff')
                    ->where('buildid', $this->Id)
                    ->update(['difference' => $warningdiff]);
            } else {
                DB::table('configureerrordiff')->insertOrIgnore([
                    ['buildid' => $this->Id, 'difference' => $warningdiff],
                ]);
            }
        }, 5);

        return true;
    }

    /** Compute the test timing as a weighted average of the previous test.
     *  Also compute the difference in tests between builds.
     *  We do that in one shot for speed reasons. */
    public function ComputeTestTiming(): bool
    {
        if (!$this->Id) {
            Log::error('BuildId is not set', [
                'function' => 'Build::ComputeTestTiming',
                'projectid' => $this->ProjectId,
            ]);
            return false;
        }

        if (!$this->ProjectId) {
            Log::error('ProjectId is not set', [
                'function' => 'Build::ComputeTestTiming',
                'projectid' => $this->ProjectId,
                'buildid' => $this->Id,
            ]);
            return false;
        }

        // Find the previous build
        $previousbuildid = $this->GetPreviousBuildId();
        if ($previousbuildid === 0) {
            return false;
        }

        // Record test differences from the previous build.
        // (+/- number of tests that failed, etc.)
        TestDiffUtil::computeDifferences($this);

        $project = \App\Models\Project::findOrFail((int) $this->ProjectId);

        // The weight of the current test compared to the previous mean/std
        // (this defines a window).
        $weight = 0.3;
        // Whether or not this build has any tests that failed
        // the time status check.
        $testtimestatusfailed = 0;

        // Get the tests performed by the previous build.
        $previous_tests = Test::where('buildid', $previousbuildid)->get();

        // Loop through the tests performed by this build.
        $current_tests = Test::where('buildid', $this->Id)->get();
        /** @var Test $current_test */
        foreach ($current_tests as $current_test) {
            $previous_test = $previous_tests->firstWhere('testname', $current_test->testname);
            if ($previous_test !== null) {
                if ($current_test->status === 'passed') {
                    // if the current test passed

                    // Check the current status
                    $previoustimestd = $previous_test->timestd;
                    if ($previous_test->timestd < $project->testtimestdthreshold) {
                        $previoustimestd = $project->testtimestdthreshold;
                    }

                    if ($current_test->time > $previous_test->timemean + $project->testtimestd * $previoustimestd) {
                        // only do positive std

                        $timestatus = $previous_test->timestatus + 1; // flag
                    } else {
                        $timestatus = 0; // reset the time status to 0
                    }

                    if ($timestatus > 0 && $timestatus <= $project->testtimemaxstatus) {
                        // if we are currently detecting the time changed we should use previous mean std

                        $timemean = $previous_test->timemean;
                        $timestd = $previoustimestd;
                    } else {
                        // Update the mean and std
                        $timemean = (1 - $weight) * $previous_test->timemean + $weight * $current_test->time;
                        $timestd = sqrt((1 - $weight) * $previoustimestd * $previoustimestd + $weight * ($current_test->time - $timemean) * ($current_test->time - $timemean));
                    }
                } else {
                    // the test failed so we just replicate the previous test time

                    $timemean = $previous_test->timemean;
                    $timestd = $previous_test->timestd;
                    $timestatus = 0;
                }
            } else {
                // the test doesn't exist

                $timestd = 0;
                $timestatus = 0;
                $timemean = $current_test->time;
            }

            $current_test->timestatus = (int) $timestatus;
            $current_test->timemean = $timemean;
            $current_test->timestd = $timestd;
            $current_test->save();

            if ($timestatus >= $project->testtimemaxstatus) {
                $testtimestatusfailed++;
            }
        }

        return EloquentBuild::where('id', $this->Id)->update([
            'testtimestatusfailed' => $testtimestatusfailed,
        ]) > 0;
    }

    /** Compute the user statistics */
    public function ComputeUpdateStatistics(): bool
    {
        if (!$this->Id) {
            Log::error('Id is not set', [
                'function' => 'Build::ComputeUpdateStatistics',
                'projectid' => $this->ProjectId,
            ]);
            return false;
        }

        if (!$this->ProjectId) {
            Log::error('ProjectId is not set', [
                'function' => 'Build::ComputeUpdateStatistics',
                'buildid' => $this->Id,
            ]);
            return false;
        }

        $previousbuildid = $this->GetPreviousBuildId();
        if ($previousbuildid < 1) {
            // Nothing to compare the current results against.
            return false;
        }

        // Record user statistics for each updated file.
        $updatefiles_stmt = $this->PDO->prepare(
            "SELECT author,email,checkindate,filename FROM updatefile AS uf
            JOIN build2update AS b2u ON b2u.updateid=uf.updateid
            WHERE b2u.buildid=? AND checkindate>'1980-01-01T00:00:00'
            ORDER BY author ASC, checkindate ASC");
        pdo_execute($updatefiles_stmt, [$this->Id]);

        while ($updatefiles_array = $updatefiles_stmt->fetch()) {
            $author = $updatefiles_array['author'];
            $email = $updatefiles_array['email'];

            // cache the author, email results
            $this->CommitAuthors = array_unique(array_merge($this->CommitAuthors, [$author, $email]));
        }
        return true;
    }

    /** Return the name of a build */
    public function GetName(): string|false
    {
        if (!$this->Id) {
            Log::error('Build GetName(): Id not set.');
            return false;
        }

        if ($this->Name !== '') {
            return $this->Name;
        }

        $this->Name = EloquentBuild::findOrFail((int) $this->Id)->name;
        return $this->Name;
    }

    /** Get all the labels for a given build */
    public function GetLabels($labelarray = []): array|false
    {
        if (!$this->Id) {
            Log::error('Build GetLabels(): Id not set.');
            return false;
        }

        $sql =
            'SELECT label.id as labelid FROM label WHERE label.id IN
                (SELECT labelid AS id FROM label2build
                 WHERE label2build.buildid = :buildid)';

        if (empty($labelarray) || isset($labelarray['test']['errors'])) {
            $sql .=
                ' OR label.id IN (
                    SELECT labelid AS id
                    FROM label2test
                    INNER JOIN build2test ON build2test.id = label2test.testid
                    WHERE build2test.buildid = :buildid
                )';
        }
        if (empty($labelarray) || isset($labelarray['coverage']['errors'])) {
            $sql .=
                ' OR label.id IN
                    (SELECT labelid AS id FROM label2coverage
                     INNER JOIN coverage ON (coverage.id=label2coverage.coverageid)
                     WHERE coverage.buildid = :buildid)';
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
     */
    public function GetGroup(): int|false
    {
        if (!$this->Id) {
            Log::error('Id is not set', [
                'function' => 'Build::GetGroup',
            ]);
            return false;
        }
        $stmt = $this->PDO->prepare(
            'SELECT groupid FROM build2group WHERE buildid = ?');
        if (!pdo_execute($stmt, [$this->Id])) {
            return false;
        }
        return (int) $stmt->fetchColumn();
    }

    /** Get the number of errors for a build */
    public function GetNumberOfErrors(): int|false
    {
        if (!$this->Id) {
            Log::error('Build GetNumberOfErrors(): Id not set');
            return false;
        }

        $num_errors = EloquentBuild::findOrFail((int) $this->Id)->builderrors;
        return self::ConvertMissingToZero($num_errors);
    }

    /** Get the number of warnings for a build */
    public function GetNumberOfWarnings(): int|false
    {
        if (!$this->Id) {
            Log::error('Build GetNumberOfWarnings(): Id not set');
            return false;
        }

        $num_warnings = EloquentBuild::findOrFail((int) $this->Id)->buildwarnings;
        return self::ConvertMissingToZero($num_warnings);
    }

    /** Lookup this build's parentid, returning 0 if none is found. */
    public function LookupParentBuildId(): int
    {
        if (!$this->SiteId || $this->Name === '' || $this->Stamp === '' || !$this->ProjectId) {
            return 0;
        }

        $builds = EloquentBuild::where([
            'parentid' => -1,
            'projectid' => $this->ProjectId,
            'siteid' => $this->SiteId,
            'name' => $this->Name,
            'stamp' => $this->Stamp,
        ]);

        return $builds->first()->id ?? 0;
    }

    /** Create a new build as a parent of $this and sets $this->ParentId.
     * Assumes many fields have been set prior to calling this function.
     **/
    public function CreateParentBuild(int $numErrors, int $numWarnings): bool
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
        $existing_build = EloquentBuild::firstWhere([
            'parentid' => Build::STANDALONE_BUILD,
            'name' => $this->Name,
            'siteid' => $this->SiteId,
            'stamp' => $this->Stamp,
            'projectid' => $this->ProjectId,
        ]);

        if ($existing_build !== null) {
            // Use the previously existing parent if one exists.
            $this->SetParentId($existing_build->id);

            // Mark it as a parent (parentid of -1).
            $existing_build->parentid = Build::PARENT_BUILD;
            $existing_build->save();
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
        $label = new Label();
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
        return EloquentBuild::where([
            'parentid' => Build::STANDALONE_BUILD,
            'siteid' => $this->SiteId,
            'name' => $this->Name,
            'stamp' => $this->Stamp,
            'projectid' => $this->ProjectId,
        ])->update(['parentid' => $this->ParentId]) > 0;
    }

    /**
     * Update our database record of a build so that it accurately reflects
     * this object and the specified number of new warnings & errors.
     **/
    public function UpdateBuild($buildid, $newErrors, $newWarnings): void
    {
        if ($buildid < 1) {
            return;
        }

        $buildid = (int) $buildid;

        DB::transaction(function () use ($buildid, $newErrors, $newWarnings): void {
            $build = EloquentBuild::find($buildid);

            if ($build === null) {
                return;
            }

            $fields_to_update = [];

            // Special case: check if we should move from -1 to 0 errors/warnings.
            $errorsHandled = false;
            $warningsHandled = false;
            if ($this->InsertErrors) {
                if ($build->builderrors === -1 && (int) $newErrors === 0) {
                    $fields_to_update['builderrors'] = 0;
                    $errorsHandled = true;
                }
                if ($build->buildwarnings === -1 && (int) $newWarnings === 0) {
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
            }

            // Check if we need to modify starttime or endtime.
            // TODO: reference testing_handler.php line 368
            if (strtotime($build->starttime) > strtotime($this->StartTime)) {
                $fields_to_update['starttime'] = $this->StartTime;
            }
            if (strtotime($build->endtime) < strtotime($this->EndTime)) {
                $fields_to_update['endtime'] = $this->EndTime;
            }

            if ($build->parentid !== -1) {
                // If this is not a parent build, check if its command has changed.
                if ($this->Command !== '' && $this->Command !== $build->command) {
                    if (!empty($build->command)) {
                        $command = $build->command . '; ' . $this->Command;
                    } else {
                        $command = $this->Command;
                    }
                    $fields_to_update['command'] = $command;
                }
            }

            // Check if the build's changeid has changed.
            if ($this->PullRequest && $this->PullRequest !== $build->changeid) {
                $fields_to_update['changeid'] = $this->PullRequest;
            }

            // Check if the build's generator has changed.
            if ($this->Generator !== '' && $this->Generator !== $build->generator) {
                $fields_to_update['generator'] = $this->Generator;
            }

            if ($this->OSName !== null && $this->OSName !== $build->osname) {
                $fields_to_update['osname'] = $this->OSName;
            }

            if ($this->OSPlatform !== null && $this->OSPlatform !== $build->osplatform) {
                $fields_to_update['osplatform'] = $this->OSPlatform;
            }

            if ($this->OSRelease !== null && $this->OSRelease !== $build->osrelease) {
                $fields_to_update['osrelease'] = $this->OSRelease;
            }

            if ($this->OSVersion !== null && $this->OSVersion !== $build->osversion) {
                $fields_to_update['osversion'] = $this->OSVersion;
            }

            if ($this->CompilerName !== null && $this->CompilerName !== $build->compilername) {
                $fields_to_update['compilername'] = $this->CompilerName;
            }

            if ($this->CompilerVersion !== null && $this->CompilerVersion !== $build->compilerversion) {
                $fields_to_update['compilerversion'] = $this->CompilerVersion;
            }

            if (!empty($fields_to_update)) {
                $build->update($fields_to_update);
            }

            // Also update the parent if necessary.
            if ($build->parentid > 0) {
                if ($buildid === $build->parentid) {
                    // Avoid infinite recursion.
                    // This should never happen, but we might as well be careful.
                    Log::error("$buildid is its own parent");
                    return;
                }
                $this->UpdateBuild($build->parentid, $newErrors, $newWarnings);
                if ($buildid === (int) $this->Id) {
                    $this->SetParentId($build->parentid);
                }
            }
        }, 5);
    }

    /** Update the testing numbers for our parent build. */
    private function UpdateParentTestNumbers(int $newFailed, int $newNotRun, int $newPassed): void
    {
        if ($this->ParentId < 1) {
            return;
        }

        DB::transaction(function () use ($newFailed, $newNotRun, $newPassed): void {
            $parent = EloquentBuild::findOrFail($this->ParentId);

            // Don't let the -1 default value screw up our math.
            $parent_testfailed = self::ConvertMissingToZero($parent->testfailed);
            $parent_testnotrun = self::ConvertMissingToZero($parent->testnotrun);
            $parent_testpassed = self::ConvertMissingToZero($parent->testpassed);

            $parent->update([
                'testnotrun' => $newNotRun + $parent_testnotrun,
                'testfailed' => $newFailed + $parent_testfailed,
                'testpassed' => $newPassed + $parent_testpassed,
            ]);

            // NOTE: as far as I can tell, build.testtimestatusfailed isn't used,
            // so for now it isn't being updated for parent builds.
        }, 5);
    }

    /**
     * Get/Set number of configure warnings for this build.
     */
    public function GetNumberOfConfigureWarnings(): int
    {
        if ($this->BuildConfigure) {
            return (int) $this->BuildConfigure->NumberOfWarnings;
        }

        $num_warnings = EloquentBuild::findOrFail((int) $this->Id)->configurewarnings;
        return self::ConvertMissingToZero($num_warnings);
    }

    public function SetNumberOfConfigureWarnings(int $numWarnings): void
    {
        if (!$this->Id || !is_numeric($this->Id)) {
            return;
        }

        EloquentBuild::where('id', (int) $this->Id)->update([
            'configurewarnings' => $numWarnings,
        ]);
    }

    /** Set number of configure errors for this build. */
    public function SetNumberOfConfigureErrors(int $numErrors): void
    {
        if (!$this->Id || !is_numeric($this->Id)) {
            return;
        }

        EloquentBuild::where('id', (int) $this->Id)->update([
            'configureerrors' => $numErrors,
        ]);

        // Should we post configure errors to a pull request?
        if (!empty($this->PullRequest) && $numErrors > 0) {
            $message = "$this->Name failed to configure";
            $url = url("/builds/{$this->Id}/configure");
            $this->NotifyPullRequest($message, $url);
        }
    }

    public function GetNumberOfConfigureErrors(): int
    {
        if ($this->BuildConfigure) {
            return (int) $this->BuildConfigure->NumberOfErrors;
        }

        $num_errors = EloquentBuild::findOrFail((int) $this->Id)->configureerrors;
        return self::ConvertMissingToZero($num_errors);
    }

    /**
     * Update the tally of configure errors & warnings for this build's
     * parent.
     **/
    public function UpdateParentConfigureNumbers(int $newWarnings, int $newErrors): void
    {
        $this->SetParentId($this->LookupParentBuildId());
        if ($this->ParentId < 1) {
            return;
        }

        DB::transaction(function () use ($newWarnings, $newErrors): void {
            $parent = EloquentBuild::findOrFail($this->ParentId);

            // Don't let the -1 default value screw up our math.
            $parent_configureerrors = self::ConvertMissingToZero($parent->configureerrors);
            $parent_configurewarnings = self::ConvertMissingToZero($parent->configurewarnings);

            $numErrors = $newErrors + $parent_configureerrors;
            $numWarnings = $newWarnings + $parent_configurewarnings;

            $parent->update([
                'configureerrors' => $numErrors,
                'configurewarnings' => $numWarnings,
            ]);
        }, 5);
    }

    public function SetPullRequest($pr): void
    {
        $this->PullRequest = $pr;
    }

    private function NotifyPullRequest(string $message, string $url): void
    {
        // Figure out if we should notify this build or its parent.
        $idToNotify = $this->Id;
        if ($this->ParentId > 0) {
            $idToNotify = $this->ParentId;
        }

        // Return early if this build already posted a comment on this PR.
        $buildToNotify = EloquentBuild::findOrFail((int) $idToNotify);
        if ($buildToNotify->notified) {
            return;
        }

        // Mention which SubProject caused this error (if any).
        if ($this->GetSubProjectName()) {
            $message .= " during $this->SubProjectName";
        }
        $message .= '.';

        // Post the PR comment & mark this build as 'notified'.
        RepositoryUtils::post_pull_request_comment($this->ProjectId, $this->PullRequest, $message, $url);

        $buildToNotify->notified = true;
        $buildToNotify->save();
    }

    private function UpdateDuration(string $field, int $duration, bool $update_parent = true): void
    {
        if ($duration === 0) {
            return;
        }

        if (!$this->Id || !is_numeric($this->Id) || !$this->Exists()) {
            return;
        }

        DB::transaction(function () use ($field, $duration, $update_parent): void {
            // Update duration of specified step for this build.
            EloquentBuild::where('id', $this->Id)
                ->increment("{$field}duration", $duration);

            if (!$update_parent) {
                return;
            }

            // If this is a child build, add this duration to the parent's sum.
            $this->SetParentId($this->LookupParentBuildId());
            if ($this->ParentId > 0) {
                // Update duration of specified step for this build.
                EloquentBuild::where('id', $this->ParentId)
                    ->increment("{$field}duration", $duration);
            }
        }, 5);
    }

    public function SetConfigureDuration(int $duration, bool $update_parent = true): void
    {
        $this->UpdateDuration('configure', $duration, $update_parent);
    }

    public function UpdateBuildDuration(int $duration, bool $update_parent = true): void
    {
        $this->UpdateDuration('build', $duration, $update_parent);
    }

    public function UpdateTestDuration(int $duration, bool $update_parent = true): void
    {
        $this->UpdateDuration('test', $duration, $update_parent);
    }

    /**
     * Return the dashboard date (in Y-m-d format) for this build.
     */
    public function GetDate(): string
    {
        if (!$this->Id || !is_numeric($this->Id)) {
            return date(FMT_DATE);
        }
        $this->FillFromId($this->Id);
        $this->GetProject()->Fill();
        return TestingDay::get($this->Project, $this->StartTime);
    }

    /** Return whether or not this build has been marked as done. */
    public function GetDone(): bool
    {
        if (empty($this->Id)) {
            return false;
        }

        if (isset($this->Done)) {
            return $this->Done;
        }

        $this->Done = EloquentBuild::findOrFail((int) $this->Id)->done;
        return $this->Done;
    }

    /** Set (or unset) the done bit in the database for this build. */
    public function MarkAsDone(bool $done): void
    {
        EloquentBuild::where('id', $this->Id)->update([
            'done' => $done,
        ]);
    }

    /**
     * Remove this build if it exists and has been marked as done.
     * This is called by XML handlers when a new replacement
     * submission is received.
     */
    public function RemoveIfDone(): bool
    {
        if (!$this->Exists() || !$this->GetDone()) {
            return false;
        }

        DatabaseCleanupUtils::removeBuild($this->Id);
        $this->Id = 0;
        return true;
    }

    /** Generate a UUID from the specified build details. */
    private static function GenerateUuid($stamp, $name, $siteid, $projectid, $subprojectname): string
    {
        return md5($stamp . '_' . $name . '_' . $siteid . '__' . $projectid . '_' . $subprojectname);
    }

    /** Get/set the parentid for this build. */
    public function GetParentId(): int
    {
        return $this->ParentId;
    }

    public function SetParentId($parentid): void
    {
        if ($parentid > 0 && (int) $parentid === (int) $this->Id) {
            Log::error("Attempt to mark build $this->Id as its own parent", [
                'function' => 'Build::SetParentId',
                'projectid' => $this->ProjectId,
                'buildid' => $this->Id,
            ]);
            return;
        }
        $this->ParentId = (int) $parentid;
    }

    /**
     * Get the beginning and the end of the testing day for this build in DATETIME format.
     */
    public function ComputeTestingDayBounds(): bool
    {
        if ($this->ProjectId < 1) {
            return false;
        }

        if (isset($this->BeginningOfDay) && isset($this->EndOfDay)) {
            return true;
        }

        $build_date = $this->GetDate();
        $this->GetProject()->Fill();
        [$this->BeginningOfDay, $this->EndOfDay] = $this->Project->ComputeTestingDayBounds($build_date);
        return true;
    }

    /**
     * Get all errors, including warnings, for all children builds of this build.
     */
    private function GetErrorsForChildren(int $fetchStyle = PDO::FETCH_ASSOC): array|false
    {
        if (!$this->Id) {
            Log::warning('Id not set', [
                'function' => 'Build::GetErrorsForChildren',
            ]);
            return false;
        }

        $sql = '
            SELECT b.subprojectid, sp.name subprojectname, be.*
            FROM builderror be
            JOIN build AS b
                ON b.id = be.buildid
            JOIN subproject AS sp
                ON sp.id = b.subprojectid
            WHERE b.parentid = ?
        ';

        $query = $this->PDO->prepare($sql);
        pdo_execute($query, [$this->Id]);

        return $query->fetchAll($fetchStyle);
    }

    /**
     * Get all failures, including warnings, for all children builds of this build.
     */
    private function GetFailuresForChildren(int $fetchStyle = PDO::FETCH_ASSOC): array|false
    {
        if (!$this->Id) {
            Log::warning('Id not set', [
                'function' => 'Build::GetFailuresForChildren',
            ]);
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
                b.subprojectid,
                sp.name subprojectname
            FROM buildfailure AS bf
            LEFT JOIN buildfailuredetails AS bfd
                ON (bfd.id=bf.detailsid)
            JOIN build b ON bf.buildid = b.id
            JOIN subproject AS sp
                ON sp.id = b.subprojectid
            WHERE b.parentid = ?
        ';

        $query = $this->PDO->prepare($sql);

        pdo_execute($query, [$this->Id]);

        return $query->fetchAll($fetchStyle);
    }

    /**
     * Return a SubProject build for a particular parent if it exists.
     */
    public static function GetSubProjectBuild(int $parentid, int $subprojectid): ?self
    {
        $eloquent_model = EloquentBuild::where([
            'parentid' => $parentid,
            'subprojectid' => $subprojectid,
        ])->first();

        if ($eloquent_model === null) {
            return null;
        }

        $build = new Build();
        $build->Id = $eloquent_model->id;
        $build->FillFromId($eloquent_model->id);
        return $build;
    }

    /**
     * Returns the current Build's Site property. This method lazily loads the Site if no such
     * object exists.
     */
    public function GetSite(): ?Site
    {
        if (!$this->Site) {
            $this->Site = Site::find($this->SiteId);
        }
        return $this->Site;
    }

    /**
     * Sets the current Build's Site property.
     */
    public function SetSite(Site $site): void
    {
        $this->Site = $site;
    }

    /**
     * Given a $buildtest, this method adds a BuildTest to the current Build's TestCollection.
     */
    public function AddTest(Test $buildtest): self
    {
        $this->TestCollection->put($buildtest->testname, $buildtest);
        return $this;
    }

    /**
     * Return the current Build's TestCollection.
     */
    public function GetTestCollection(): Collection
    {
        return $this->TestCollection;
    }

    /**
     * Return the Id of the Build matching the given $uuid,
     * or FALSE if no such build exists.
     */
    private static function GetIdFromUuid($uuid): ?int
    {
        $model = EloquentBuild::where('uuid', $uuid);
        return $model->first()?->id;
    }

    /**
     * Insert this build if it doesn't already exist.
     * If a build was created or an existing build was found,
     * this->Id gets set to a valid value.
     * Returns TRUE if a build was created, FALSE otherwise.
     * $this is expected to have Stamp, Name, SiteId, and ProjectId set.
     */
    public function AddBuild(int $nbuilderrors = -1, int $nbuildwarnings = -1): bool
    {
        // Compute a uuid for this build if necessary.
        if ($this->Uuid === '') {
            $this->Uuid = self::GenerateUuid($this->Stamp, $this->Name,
                $this->SiteId, $this->ProjectId, $this->SubProjectName);
        }

        // Check if a build with this uuid already exists.
        $id = self::GetIdFromUuid($this->Uuid);
        if ($id !== null) {
            $this->Id = $id;
            return false;
        }

        // Set ParentId if this is a SubProject build.
        $justCreatedParent = false;
        if ($this->SubProjectName) {
            $this->SetParentId($this->LookupParentBuildId());
            if ($this->ParentId === 0) {
                // Parent build doesn't exist yet, create it here.
                $justCreatedParent = $this->CreateParentBuild($nbuilderrors, $nbuildwarnings);
            }
        }

        // Make sure this build has a type.
        if (strlen($this->Type) === 0) {
            $this->Type = self::extract_type_from_buildstamp($this->Stamp);
        }

        // Build doesn't exist yet, create it here.
        $build_created = false;
        try {
            DB::transaction(function () use ($nbuilderrors, $nbuildwarnings, &$build_created): void {
                $this->Id = EloquentBuild::create([
                    'siteid' => $this->SiteId,
                    'projectid' => $this->ProjectId,
                    'stamp' => $this->Stamp,
                    'name' => $this->Name,
                    'type' => $this->Type,
                    'generator' => $this->Generator,
                    'starttime' => $this->StartTime,
                    'endtime' => $this->EndTime,
                    'submittime' => $this->SubmitTime,
                    'command' => $this->Command,
                    'builderrors' => $nbuilderrors,
                    'buildwarnings' => $nbuildwarnings,
                    'parentid' => $this->ParentId,
                    'uuid' => $this->Uuid,
                    'changeid' => $this->PullRequest,
                    'osname' => $this->OSName,
                    'osplatform' => $this->OSPlatform,
                    'osrelease' => $this->OSRelease,
                    'osversion' => $this->OSVersion,
                    'compilername' => $this->CompilerName,
                    'compilerversion' => $this->CompilerVersion,
                ])->id;
                $build_created = true;
                $this->AssignToGroup();
            });
        } catch (Exception $e) {
            // This error might be due to a unique key violation on the UUID.
            // Check again for a previously existing build.
            $existing_id = self::GetIdFromUuid($this->Uuid);
            if ($existing_id !== null) {
                $this->Id = $existing_id;
                $build_created = false;
            } else {
                // Otherwise log the error and return false.
                report($e);
                return false;
            }
        }

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

    public function AssignToGroup(): void
    {
        // Return early if this build already belongs to a group.
        $existing_groupid_row = DB::table('build2group')->where('buildid', $this->Id)->first();
        if ($existing_groupid_row) {
            $this->GroupId = (int) $existing_groupid_row->groupid;
            return;
        }

        // Find and record the groupid for this build.
        $buildGroup = new BuildGroup();
        $this->GroupId = $buildGroup->GetGroupIdFromRule($this);
        DB::table('build2group')->insertOrIgnore([
            ['groupid' => $this->GroupId, 'buildid' => $this->Id],
        ]);

        // Associate the parent with this build's group if necessary.
        if ($this->ParentId > 0) {
            $existing_parent_groupid_row = DB::table('build2group')->where('buildid', $this->ParentId)->first();
            if (!$existing_parent_groupid_row) {
                DB::table('build2group')->insertOrIgnore([
                    ['groupid' => $this->GroupId, 'buildid' => $this->ParentId],
                ]);
            }
        }

        // Add the subproject relationship if necessary.
        if ($this->SubProjectId) {
            EloquentBuild::where('id', $this->Id)->update(['subprojectid' => $this->SubProjectId]);
        }
    }

    public function GetBuildSummaryUrl(): string
    {
        return url("/builds/{$this->Id}");
    }

    public function GetBuildErrorUrl(): string
    {
        return url('/viewBuildError.php') . "?buildid={$this->Id}";
    }

    public function GetTestUrl(): string
    {
        return url('/viewTest.php') . "?buildid={$this->Id}";
    }

    public static function ConvertMissingToZero($value): int
    {
        $value = (int) $value;
        return $value === -1 ? 0 : $value;
    }

    /**
     * Returns the current Build's BuildConfigure property. This method lazily loads the
     * BuildConfigure object if none exists.
     */
    public function GetBuildConfigure(): BuildConfigure
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
     */
    public function SetBuildConfigure(BuildConfigure $buildConfigure): void
    {
        $buildConfigure->BuildId = $this->Id;
        $this->BuildConfigure = $buildConfigure;
    }

    /**
     * Returns the current Build's Project object. This method lazily loads the Project if none
     * exists.
     */
    public function GetProject(): Project
    {
        if (!isset($this->Project)) {
            $this->Project = new Project();
            $this->Project->Id = $this->ProjectId;
        }
        return $this->Project;
    }

    /**
     * Sets the current Build's Project property.
     */
    public function SetProject(Project $project): void
    {
        $this->Project = $project;
    }

    /**
     * Returns the current Build's Type property.
     */
    public function GetBuildType(): string
    {
        return $this->Type;
    }

    /**
     * This method returns an array of all of the authors who are responsible for changes made
     * to the current Build.
     */
    public function GetCommitAuthors(): array
    {
        // note: Per Zack: Depending on the type of submission (i.e. test, build error, etc)
        // this information may not yet be available as it is contained in the update xml
        // file submission.

        if ($this->CommitAuthors === []) {
            $update_files = EloquentBuild::with('updates.updateFiles')
                ->findOrFail((int) $this->Id)
                ->updates
                ->pluck('updateFiles')
                ->flatten();

            $authors = [];
            /** @var BuildUpdateFile $row */
            foreach ($update_files as $row) {
                $hasAuthor = !empty($row->email);
                $hasCommitter = !empty($row->committeremail);

                if ($hasAuthor) {
                    $authors[] = $row->email;
                }

                if ($hasCommitter) {
                    $authors[] = $row->committeremail;
                }

                if ($hasAuthor === false
                    && $hasCommitter === false
                    && filter_var($row->author, FILTER_VALIDATE_EMAIL) !== false
                ) {
                    $authors[] = $row->author;
                }
            }
            $this->CommitAuthors = array_unique($authors);
        }
        return $this->CommitAuthors;
    }

    /**
     * Given a $subscriber this method returns true if the current Build has contains changes
     * authored by $subscriber and false if no such changes by the author exist.
     */
    public function AuthoredBy(SubscriberInterface $subscriber): bool
    {
        return in_array($subscriber->getAddress(), $this->GetCommitAuthors());
    }

    /**
     * Returns the current Build's LabelCollection.
     */
    public function GetLabelCollection(): Collection
    {
        return $this->LabelCollection;
    }

    /**
     * Adds a DynamicAnalysis object to the Build's DynamicAnalysisCollection.
     */
    public function AddDynamicAnalysis(DynamicAnalysis $analysis): self
    {
        $analyses = $this->GetDynamicAnalysisCollection();
        $analyses->add($analysis);
        return $this;
    }

    /**
     * Returns the current Build's DynamicAnalysisCollection object. This method lazily loads the
     * DynamicAnalysisCollection if none exists.
     */
    public function GetDynamicAnalysisCollection(): DynamicAnalysisCollection
    {
        if (!isset($this->DynamicAnalysisCollection)) {
            $this->DynamicAnalysisCollection = new DynamicAnalysisCollection();
        }
        return $this->DynamicAnalysisCollection;
    }

    /**
     * Returns the BuildEmailCollection object. This method lazily loads a CollectionCollection
     * object if none exists.
     */
    public function GetBuildEmailCollection(): BuildEmailCollection
    {
        if (!$this->Id) {
            return new BuildEmailCollection();
        }

        if (!isset($this->BuildEmailCollection)) {
            $this->BuildEmailCollection = BuildEmail::GetEmailSentForBuild($this->Id);
        }

        return $this->BuildEmailCollection;
    }

    /**
     * Sets the current build's BuildEmailCollection object. This method lazily loads a
     * CollectionCollection object as the current Build's BuildEmailCollection property if none
     * exists.
     */
    public function SetBuildEmailCollection(BuildEmailCollection $collection): void
    {
        $this->BuildEmailCollection = $collection;
    }

    /**
     * Sets the build's BuildUpdate object.
     */
    public function SetBuildUpdate(BuildUpdate $buildUpdate): void
    {
        $this->BuildUpdate = $buildUpdate;
    }

    /**
     * Returns the BuildUpdate object.
     */
    public function GetBuildUpdate(): ?BuildUpdate
    {
        return $this->BuildUpdate;
    }

    /**
     * Returns a data structure representing the difference between the previous build and
     * the current build.
     *
     * TODO: Create a diff class
     */
    public function GetDiffWithPreviousBuild(): array|false
    {
        if (!$this->Id) {
            return false;
        }

        if (!isset($this->ErrorDifferences)) {
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
                    ],
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
                if ($diff === false) {
                    abort(500, 'Error calculating error diffs');
                }
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
                    ],
                    'TestFailure' => [
                        'passed' => [
                            'new' => $diff['testpassedpositive'],
                            'broken' => $diff['testpassednegative'],
                        ],
                        'failed' => [
                            'new' => $diff['testfailedpositive'],
                            'fixed' => $diff['testfailednegative'],
                        ],
                        'notrun' => [
                            'new' => $diff['testnotrunpositive'],
                            'fixed' => $diff['testnotrunnegative'],
                        ],
                    ],
                ];
            }
        }
        return $this->ErrorDifferences;
    }
}
