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

use App\Models\CoverageSummaryDiff;
use CDash\Database;
use Illuminate\Support\Facades\DB;

class CoverageSummary
{
    private $LocTested = 0;
    private $LocUntested = 0;
    public $BuildId;
    private $Coverages;
    public $BranchesTested;
    public $BranchesUntested;
    public $FunctionsTested;
    public $FunctionsUntested;

    public function __construct()
    {
        $this->Coverages = [];
    }

    public function AddCoverage($coverage): void
    {
        $this->Coverages[] = $coverage;
    }

    /** Remove all coverage information */
    public function RemoveAll(): bool
    {
        if (!$this->BuildId) {
            abort(500, 'CoverageSummary::RemoveAll(): BuildId not set');
        }

        $db = Database::getInstance();

        $query = $db->executePrepared('DELETE FROM coveragesummarydiff WHERE buildid=?', [intval($this->BuildId)]);
        if ($query === false) {
            add_last_sql_error('CoverageSummary RemoveAll');
            return false;
        }

        // coverage file are kept unless they are shared
        $coverage = $db->executePrepared('SELECT fileid FROM coverage WHERE buildid=?', [intval($this->BuildId)]);
        foreach ($coverage as $coverage_array) {
            $fileid = intval($coverage_array['fileid']);
            // Make sure the file is not shared
            $numfiles = $db->executePreparedSingleRow('SELECT count(*) AS c FROM coveragefile WHERE id=?', [$fileid]);
            if (intval($numfiles['c']) === 1) {
                $db->executePrepared('DELETE FROM coveragefile WHERE id=?', [$fileid]);
            }
        }

        $query = $db->executePrepared('DELETE FROM coverage WHERE buildid=?', [intval($this->BuildId)]);
        if ($query === false) {
            add_last_sql_error('CoverageSummary RemoveAll');
            return false;
        }

        $query = $db->executePrepared('DELETE FROM coveragefilelog WHERE buildid=?', [intval($this->BuildId)]);
        if ($query === false) {
            add_last_sql_error('CoverageSummary RemoveAll');
            return false;
        }

        $query = $db->executePrepared('DELETE FROM coveragesummary WHERE buildid=?', [intval($this->BuildId)]);
        if ($query === false) {
            add_last_sql_error('CoverageSummary RemoveAll');
            return false;
        }
        return true;
    }   // RemoveAll()

    /** Insert a new summary */
    public function Insert($append = false): bool
    {
        if (!$this->BuildId || !is_numeric($this->BuildId)) {
            abort(500, 'CoverageSummary::Insert(): BuildId not set');
        }

        if (count($this->Coverages) > 0) {
            $db = Database::getInstance();

            foreach ($this->Coverages as $coverage) {
                $fullpath = $coverage->CoverageFile->FullPath;

                // GcovTarHandler creates its own coveragefiles, no need to do
                // it again here.
                $fileid = -1;
                if ($coverage->CoverageFile->Crc32 > 0) {
                    $fileid = $coverage->CoverageFile->Id;
                }

                if ($fileid === -1) {
                    // Check if this file already exists in the database.
                    // This could happen if CoverageLog.xml was parsed before Coverage.xml.
                    $coveragefile = $db->executePreparedSingleRow('
                                        SELECT id
                                        FROM coveragefile AS cf
                                        INNER JOIN coveragefilelog AS cfl ON (cfl.fileid=cf.id)
                                        WHERE cf.fullpath=? AND cfl.buildid=?
                                   ', [$fullpath, intval($this->BuildId)]);
                    if (empty($coveragefile)) {
                        // Create an empty file if doesn't exist.
                        $db->executePrepared('INSERT INTO coveragefile (fullpath, crc32) VALUES (?, 0)', [$fullpath]);
                        $fileid = intval(pdo_insert_id('coveragefile'));
                    } else {
                        $fileid = intval($coveragefile['id']);
                    }
                    $coverage->CoverageFile->Id = $fileid;
                }

                $covered = $coverage->Covered;
                $loctested = $coverage->LocTested;
                $locuntested = $coverage->LocUntested;
                $branchestested = $coverage->BranchesTested;
                $branchesuntested = $coverage->BranchesUntested;
                $functionstested = $coverage->FunctionsTested;
                $functionsuntested = $coverage->FunctionsUntested;

                if (empty($covered)) {
                    $covered = 0;
                }
                if (empty($loctested)) {
                    $loctested = 0;
                }
                if (empty($locuntested)) {
                    $locuntested = 0;
                }
                if (empty($branchestested)) {
                    $branchestested = 0;
                }
                if (empty($branchesuntested)) {
                    $branchesuntested = 0;
                }
                if (empty($functionstested)) {
                    $functionstested = 0;
                }
                if (empty($functionsuntested)) {
                    $functionsuntested = 0;
                }

                $this->LocTested += $loctested;
                $this->LocUntested += $locuntested;

                $existing_row_updated = false;
                if ($append) {
                    // UPDATE (instead of INSERT) if this coverage already
                    // exists.
                    $existing_row_updated = DB::transaction(function () use ($coverage, $covered, $loctested, $locuntested, $branchestested, $branchesuntested, $functionstested, $functionsuntested) {
                        $existing_coverage_row = DB::table('coverage')
                            ->where('buildid', $this->BuildId)
                            ->where('fileid', $coverage->CoverageFile->Id)
                            ->lockForUpdate()
                            ->first();
                        if ($existing_coverage_row) {
                            DB::table('coverage')
                                ->where('buildid', $this->BuildId)
                                ->where('fileid', $coverage->CoverageFile->Id)
                                ->update([
                                    'covered' => $covered,
                                    'loctested' => $loctested,
                                    'locuntested' => $locuntested,
                                    'branchestested' => $branchestested,
                                    'branchesuntested' => $branchesuntested,
                                    'functionstested' => $functionstested,
                                    'functionsuntested' => $functionsuntested,
                                ]);
                            return true;
                        }
                        return false;
                    });
                }
                if (!$existing_row_updated) {
                    DB::table('coverage')->insert([
                        'buildid' => $this->BuildId,
                        'fileid' => $fileid,
                        'covered' => $covered,
                        'loctested' => $loctested,
                        'locuntested' => $locuntested,
                        'branchestested' => $branchestested,
                        'branchesuntested' => $branchesuntested,
                        'functionstested' => $functionstested,
                        'functionsuntested' => $functionsuntested,
                    ]);
                }
            }

            // Add labels
            foreach ($this->Coverages as &$coverage) {
                $coverage->InsertLabelAssociations($this->BuildId);
            }
        }

        $summary_updated = false;
        if ($append) {
            // Check if a coveragesummary already exists for this build.
            $summary_updated = DB::transaction(function () use (&$delta_tested, &$delta_untested) {
                $existing_summary_row = DB::table('coveragesummary')
                    ->where('buildid', $this->BuildId)
                    ->lockForUpdate()
                    ->first();
                if (!$existing_summary_row) {
                    return false;
                }
                $previous_loctested = $existing_summary_row->loctested;
                $previous_locuntested = $existing_summary_row->locuntested;

                // Recompute how many lines were tested & untested
                // based on all files covered by this build.
                $this->LocTested = 0;
                $this->LocUntested = 0;
                $rows = DB::table('coverage')->where('buildid', $this->BuildId)->get();
                foreach ($rows as $row) {
                    $this->LocTested += $row->loctested;
                    $this->LocUntested += $row->locuntested;
                }

                // Update the existing record with this information.
                DB::table('coveragesummary')
                    ->where('buildid', $this->BuildId)
                    ->update([
                        'loctested' => $this->LocTested,
                        'locuntested' => $this->LocUntested,
                    ]);

                // Record how loctested and locuntested changed as a result
                // of this update.
                $delta_tested = $this->LocTested - $previous_loctested;
                $delta_untested = $this->LocUntested - $previous_locuntested;

                return true;
            });
        }

        if (!$summary_updated) {
            DB::table('coveragesummary')->insert([
                'buildid' => $this->BuildId,
                'loctested' => $this->LocTested,
                'locuntested' => $this->LocUntested,
            ]);
        }

        // If this is a child build then update the parent's summary as well.
        $build_row = DB::table('build')->where('id', $this->BuildId)->first();
        if ($build_row) {
            $parentid = $build_row->parentid;
            if ($parentid > 0) {
                DB::transaction(function () use ($parentid, $delta_tested, $delta_untested) {
                    $parent_summary = DB::table('coveragesummary')
                        ->where('buildid', $parentid)
                        ->lockForUpdate()
                        ->first();

                    if (!$parent_summary) {
                        DB::table('coveragesummary')->insert([
                            'buildid' => $parentid,
                            'loctested' => $this->LocTested,
                            'locuntested' => $this->LocUntested,
                        ]);
                    } else {
                        if (!isset($delta_tested)) {
                            $delta_tested = $this->LocTested;
                        }
                        if (!isset($delta_untested)) {
                            $delta_untested = $this->LocUntested;
                        }
                        DB::table('coveragesummary')
                        ->where('buildid', $parentid)
                        ->update([
                            'loctested' => DB::raw("loctested + $delta_tested"),
                            'locuntested' => DB::raw("locuntested + $delta_untested"),
                        ]);
                    }
                });
            }
        }
        return true;
    }   // Insert()

    /** Compute the coverage summary diff */
    public function ComputeDifference($previous_parentid=null): bool
    {
        $build = new Build();
        $build->Id = $this->BuildId;
        $previousBuildId = $build->GetPreviousBuildId($previous_parentid);
        if ($previousBuildId < 1) {
            return true;
        }

        $db = Database::getInstance();

        // Look at the number of errors and warnings differences
        $coverage = $db->executePreparedSingleRow('
                        SELECT loctested, locuntested
                        FROM coveragesummary
                        WHERE buildid=?
                    ', [intval($this->BuildId)]);
        if (empty($coverage)) {
            add_last_sql_error('CoverageSummary:ComputeDifference');
            return false;
        }
        $loctested = intval($coverage['loctested']);
        $locuntested = intval($coverage['locuntested']);

        $previouscoverage = $db->executePreparedSingleRow('
                                SELECT loctested, locuntested
                                FROM coveragesummary
                                WHERE buildid=?
                            ', [intval($previousBuildId)]);
        if (!empty($previouscoverage)) {
            $previousloctested = intval($previouscoverage['loctested']);
            $previouslocuntested = intval($previouscoverage['locuntested']);

            $loctesteddiff = $loctested - $previousloctested;
            $locuntesteddiff = $locuntested - $previouslocuntested;

            // Don't log if no diff unless an entry already exists for this build.
            if (CoverageSummaryDiff::where(['buildid' => $this->BuildId])->exists() || $loctesteddiff !== 0 || $locuntesteddiff !== 0) {
                CoverageSummaryDiff::updateOrCreate([
                    'buildid' => $this->BuildId,
                    ], [
                    'loctested' => $loctesteddiff,
                    'locuntested' => $locuntesteddiff,
                ]);
            }
        }

        return true;
    }

    /** Return the list of buildid which are contributing to the dashboard */
    public function GetBuilds($projectid, $timestampbegin, $timestampend): array|false
    {
        $db = Database::getInstance();
        $coverage = $db->executePrepared('
                        SELECT buildid
                        FROM coveragesummary, build
                        WHERE
                            coveragesummary.buildid=build.id
                            AND build.projectid=?
                            AND build.starttime>?
                            AND endtime<?', [intval($projectid), $timestampbegin, $timestampend]);
        if ($coverage === false) {
            add_last_sql_error('CoverageSummary:GetBuilds');
            return false;
        }

        $buildids = [];
        foreach ($coverage as $coverage_array) {
            $buildids[] = intval($coverage_array['buildid']);
        }
        return $buildids;
    }

    /** Return whether or not a CoverageSummary exists for this build. */
    public function Exists(): bool
    {
        if (!$this->BuildId) {
            return false;
        }

        $db = Database::getInstance();
        $exists_result = $db->executePrepared('
                             SELECT 1
                             FROM coveragesummary
                             WHERE buildid=?
                         ', [intval($this->BuildId)]);

        return !empty($exists_result);
    }
}
