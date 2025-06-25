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

use App\Models\Coverage;
use App\Models\CoverageFile;
use App\Models\Label;
use CDash\Database;
use Exception;
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
    public function RemoveAll(): void
    {
        if (!$this->BuildId) {
            abort(500, 'CoverageSummary::RemoveAll(): BuildId not set');
        }

        // coverage files are kept unless they are shared
        $build = \App\Models\Build::findOrFail((int) $this->BuildId);
        /** @var Coverage $coverage */
        foreach ($build->coverageResults()->get() as $coverage) {
            /** @var CoverageFile $file */
            $file = $coverage->file()->first();
            if ($file->builds()->count() === 1) {
                $file->delete();
            }
        }

        $build->coverageResults()->delete();
        DB::delete('DELETE FROM coveragefilelog WHERE buildid=?', [intval($this->BuildId)]);
        DB::delete('DELETE FROM coveragesummary WHERE buildid=?', [intval($this->BuildId)]);
    }

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
                        $fileid = CoverageFile::create([
                            'fullpath' => $fullpath,
                            'crc32' => 0,
                        ])->id;
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

                $eloquent_coverage = null;

                // TODO: replace the following two conditionals with a single call to updateOrCreate()
                if ($append) {
                    // UPDATE (instead of INSERT) if this coverage already
                    // exists.
                    $existing_row_updated = DB::transaction(function () use ($coverage, $covered, $loctested, $locuntested, $branchestested, $branchesuntested, $functionstested, $functionsuntested, &$eloquent_coverage) {
                        $eloquent_coverage = Coverage::firstWhere([
                            'buildid' => $this->BuildId,
                            'fileid' => $coverage->CoverageFile->Id,
                        ]);

                        if ($eloquent_coverage !== null) {
                            $eloquent_coverage->update([
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
                    $eloquent_coverage = Coverage::create([
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

                // This case should never happen, but we check just in case to make PHPStan happy
                if ($eloquent_coverage === null) {
                    throw new Exception('Invalid state: coverage model does not exist.');
                }

                foreach ($coverage->Labels ?? [] as $label) {
                    $eloquent_coverage->labels()->syncWithoutDetaching(Label::firstOrCreate(['text' => $label->Text]));
                }
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
                $this->LocTested = Coverage::where('buildid', (int) $this->BuildId)->sum('loctested');
                $this->LocUntested = Coverage::where('buildid', (int) $this->BuildId)->sum('locuntested');

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
    public function ComputeDifference($previous_parentid = null): bool
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
                        SELECT loctested, locuntested, loctesteddiff, locuntesteddiff
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

            DB::update('
                UPDATE coveragesummary
                SET
                    loctesteddiff = ?,
                    locuntesteddiff = ?
                WHERE buildid = ?
            ', [
                $loctesteddiff,
                $locuntesteddiff,
                intval($this->BuildId),
            ]);
        }

        return true;
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
