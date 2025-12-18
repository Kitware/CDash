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
use Illuminate\Support\Facades\Log;

class CoverageFileLog
{
    public $BuildId;
    public $Build;
    public $FileId;
    public $Lines;
    public $Branches;
    // The following members are used by GcovTar_handler & friends to
    // speed up parsing for coverage across SubProjects.
    public $AggregateBuildId;
    public $PreviousAggregateParentId;

    public function __construct()
    {
        $this->Lines = [];
        $this->Branches = [];
        $this->PreviousAggregateParentId = null;
    }

    public function AddLine($number, $code): void
    {
        if (array_key_exists($number, $this->Lines)) {
            $this->Lines[$number] += (int) $code;
        } else {
            $this->Lines[$number] = (int) $code;
        }
    }

    public function AddBranch($number, $covered, $total): void
    {
        $this->Branches[$number] = "$covered/$total";
    }

    /** Update the content of the file */
    public function Insert($append = false): bool
    {
        if (!$this->BuildId || !is_numeric($this->BuildId)) {
            Log::error('BuildId not set', [
                'function' => 'CoverageFileLog::Insert()',
            ]);
            return false;
        }

        if (!$this->FileId || !is_numeric($this->FileId)) {
            Log::error('FileId not set', [
                'function' => 'CoverageFileLog::Insert()',
                'buildid' => $this->BuildId,
            ]);
            return false;
        }

        DB::transaction(function () use ($append): void {
            $update = false;
            if ($append) {
                // Load any previously existing results for this file & build.
                $update = $this->Load(true);
            }

            $log = '';
            foreach ($this->Lines as $lineNumber => $code) {
                $log .= $lineNumber . ':' . $code . ';';
            }
            foreach ($this->Branches as $lineNumber => $code) {
                $log .= 'b' . $lineNumber . ':' . $code . ';';
            }

            if ($log != '') {
                if ($update) {
                    DB::table('coveragefilelog')
                        ->where('buildid', $this->BuildId)
                        ->where('fileid', $this->FileId)
                        ->update(['log' => $log]);
                } else {
                    DB::table('coveragefilelog')
                        ->insert([
                            'buildid' => $this->BuildId,
                            'fileid' => $this->FileId,
                            'log' => $log,
                        ]);
                }
            }
        });
        $this->UpdateAggregate();
        return true;
    }

    public function Load($for_update = false): bool
    {
        if ($for_update) {
            $row = DB::table('coveragefilelog')
                ->where('buildid', $this->BuildId)
                ->where('fileid', $this->FileId)
                ->lockForUpdate()
                ->first();
        } else {
            $row = DB::table('coveragefilelog')
                ->where('buildid', $this->BuildId)
                ->where('fileid', $this->FileId)
                ->first();
        }

        if (!$row) {
            return false;
        }

        $log_entries = explode(';', $row->log);
        // Make an initial pass through $log_entries to see what lines
        // it contains.
        $lines_retrieved = [];
        foreach ($log_entries as $log_entry) {
            if (empty($log_entry)) {
                continue;
            }
            [$line, $value] = explode(':', $log_entry);
            if (is_numeric($line)) {
                $lines_retrieved[] = (int) $line;
            } else {
                $lines_retrieved[] = $line;
            }
        }

        // Use this info to remove any uncovered lines that
        // the previous result considered uncoverable.
        foreach ($this->Lines as $line_number => $times_hit) {
            if ($times_hit == 0
                    && !in_array($line_number, $lines_retrieved, true)) {
                unset($this->Lines[$line_number]);
            }
        }

        // Does $this already contain coverage?
        // We use this information to distinguish between uncoverable lines
        // vs. missed lines below.
        $alreadyPopulated = !empty($this->Lines);

        foreach ($log_entries as $log_entry) {
            if (empty($log_entry)) {
                continue;
            }
            [$line, $value] = explode(':', $log_entry);
            if ($line[0] === 'b') {
                // Branch coverage
                $line = ltrim($line, 'b');
                [$covered, $total] = explode('/', $value);
                $this->AddBranch($line, $covered, $total);
            } else {
                // Line coverage
                if ($value == 0 && $alreadyPopulated
                        && !array_key_exists($line, $this->Lines)) {
                    // This object already considers the line uncoverable,
                    // so ignore the result from the database marking it as
                    // missed.
                    continue;
                }
                $this->AddLine($line, $value);
            }
        }
        return true;
    }

    public function GetStats(): array
    {
        $stats = [];
        $stats['loctested'] = 0;
        $stats['locuntested'] = 0;
        $stats['branchestested'] = 0;
        $stats['branchesuntested'] = 0;
        foreach ($this->Lines as $line => $timesHit) {
            if ($timesHit > 0) {
                $stats['loctested']++;
            } else {
                $stats['locuntested']++;
            }
        }

        foreach ($this->Branches as $line => $value) {
            [$timesHit, $total] = explode('/', $value);
            $stats['branchestested'] += $timesHit;
            $stats['branchesuntested'] += ($total - $timesHit);
        }
        return $stats;
    }

    /** Update the aggregate coverage build to include these results. */
    public function UpdateAggregate(): void
    {
        if (!$this->Build) {
            $this->Build = new Build();
            $this->Build->Id = $this->BuildId;
        }
        $this->Build->FillFromId($this->BuildId);

        // Only nightly builds count towards aggregate coverage.
        if ($this->Build->Type !== 'Nightly'
            || $this->Build->Name === 'Aggregate Coverage'
        ) {
            return;
        }

        $db = Database::getInstance();

        // Find the build ID for this day's edition of 'Aggregate Coverage'.
        if ($this->AggregateBuildId) {
            if ($this->Build->SubProjectId) {
                // For SubProject builds, AggregateBuildId refers to the parent.
                // Look up the ID of the appropriate child.
                $row = $db->executePreparedSingleRow('
                           SELECT id
                           FROM build
                           WHERE
                               parentid=?
                               AND projectid=?
                               AND b.subprojectid=?
                       ', [
                    (int) $this->AggregateBuildId,
                    (int) $this->Build->ProjectId,
                    (int) $this->Build->SubProjectId,
                ]);
                if (!$row || !array_key_exists('id', $row)) {
                    // An aggregate build for this SubProject doesn't exist yet.
                    // Create it here.
                    $aggregateBuild = create_aggregate_build($this->Build);
                    $aggregateBuildId = $aggregateBuild->Id;
                } else {
                    $aggregateBuildId = (int) $row['id'];
                }
            } else {
                // For standalone builds AggregateBuildId is exactly what we're
                // looking for.
                $aggregateBuildId = (int) $this->AggregateBuildId;
            }
            $aggregateBuild = new Build();
            $aggregateBuild->Id = $aggregateBuildId;
            $aggregateBuild->FillFromId($aggregateBuildId);
        } else {
            // AggregateBuildId not specified, look it up here.
            $aggregateBuild = get_aggregate_build($this->Build);
            $aggregateBuildId = $aggregateBuild->Id;
            $aggregateBuild->FillFromId($aggregateBuildId);
        }

        // Abort if this log refers to a different version of the file
        // than the one already contained in the aggregate.
        $path = \App\Models\CoverageFile::findOrFail((int) $this->FileId)->fullpath;
        $row = $db->executePreparedSingleRow('
                   SELECT id
                   FROM coveragefile AS cf
                   INNER JOIN coveragefilelog AS cfl ON (cfl.fileid=cf.id)
                   WHERE
                       cfl.buildid=?
                       AND cf.fullpath=?
               ', [$aggregateBuildId, $path]);
        if ($row && array_key_exists('id', $row) && (int) $row['id'] !== (int) $this->FileId) {
            Log::info("Not appending coverage of '$path' to aggregate as it already contains a different version of this file.", [
                'function' => 'CoverageFileLog::UpdateAggregate',
                'buildid' => $this->BuildId,
            ]);
            return;
        }

        // Append these results to the aggregate coverage log.
        $aggregateLog = clone $this;
        $aggregateLog->BuildId = $aggregateBuildId;
        $aggregateLog->Build = $aggregateBuild;
        $aggregateLog->Insert(true);

        // Update the aggregate coverage summary.
        $aggregateSummary = new CoverageSummary();
        $aggregateSummary->BuildId = $aggregateBuildId;

        $coverageFile = new CoverageFile();
        $coverageFile->Id = $this->FileId;
        $coverageFile->Load();
        $coverageFile->Update($aggregateBuildId);

        // Query the log to get how many lines & branches were covered.
        // We do this after inserting the filelog because we want to
        // accurately reflect the union of the current and previously
        // existing results (if any).
        $stats = $aggregateLog->GetStats();
        $aggregateCoverage = new Coverage();
        $aggregateCoverage->CoverageFile = $coverageFile;
        $aggregateCoverage->LocUntested = $stats['locuntested'];
        $aggregateCoverage->LocTested = $stats['loctested'];
        if ($aggregateCoverage->LocTested > 0 || $aggregateCoverage->LocUntested > 0) {
            $aggregateCoverage->Covered = 1;
        } else {
            $aggregateCoverage->Covered = 0;
        }
        $aggregateCoverage->BranchesUntested = $stats['branchesuntested'];
        $aggregateCoverage->BranchesTested = $stats['branchestested'];

        // Add this Coverage to the summary.
        $aggregateSummary->AddCoverage($aggregateCoverage);

        // Insert/Update the aggregate summary.
        $aggregateSummary->Insert(true);
        $aggregateSummary->ComputeDifference($this->PreviousAggregateParentId);

        if ($this->Build->SubProjectId && $this->AggregateBuildId) {
            // Compute diff for the aggregate parent too.
            $aggregateParentSummary = new CoverageSummary();
            $aggregateParentSummary->BuildId = $this->AggregateBuildId;
            $aggregateParentSummary->ComputeDifference();
        }
    }
}
