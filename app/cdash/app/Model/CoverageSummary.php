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
        $this->Coverages = array();
    }

    public function AddCoverage($coverage)
    {
        $this->Coverages[] = $coverage;
    }

    public function GetCoverages()
    {
        return $this->Coverages;
    }

    /** Remove all coverage information */
    public function RemoveAll()
    {
        if (!$this->BuildId) {
            echo 'CoverageSummary::RemoveAll(): BuildId not set';
            return false;
        }

        $query = 'DELETE FROM coveragesummarydiff WHERE buildid=' . qnum($this->BuildId);
        if (!pdo_query($query)) {
            add_last_sql_error('CoverageSummary RemoveAll');
            return false;
        }

        // coverage file are kept unless they are shared
        $coverage = pdo_query('SELECT fileid FROM coverage WHERE buildid=' . qnum($this->BuildId));
        while ($coverage_array = pdo_fetch_array($coverage)) {
            $fileid = $coverage_array['fileid'];
            // Make sure the file is not shared
            $numfiles = pdo_query("SELECT count(*) FROM coveragefile WHERE id='$fileid'");
            $numfiles_array = pdo_fetch_row($numfiles);
            if ($numfiles_array[0] == 1) {
                pdo_query("DELETE FROM coveragefile WHERE id='$fileid'");
            }
        }

        $query = 'DELETE FROM coverage WHERE buildid=' . qnum($this->BuildId);
        if (!pdo_query($query)) {
            add_last_sql_error('CoverageSummary RemoveAll');
            return false;
        }

        $query = 'DELETE FROM coveragefilelog WHERE buildid=' . qnum($this->BuildId);
        if (!pdo_query($query)) {
            add_last_sql_error('CoverageSummary RemoveAll');
            return false;
        }

        $query = 'DELETE FROM coveragesummary WHERE buildid=' . qnum($this->BuildId);
        if (!pdo_query($query)) {
            add_last_sql_error('CoverageSummary RemoveAll');
            return false;
        }
        return true;
    }   // RemoveAll()

    /** Insert a new summary */
    public function Insert($append = false)
    {
        if (!$this->BuildId || !is_numeric($this->BuildId)) {
            echo 'CoverageSummary::Insert(): BuildId not set';
            return false;
        }

        if (count($this->Coverages) > 0) {
            foreach ($this->Coverages as &$coverage) {
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
                    $coveragefile = pdo_query(
                        "SELECT id FROM coveragefile AS cf
                    INNER JOIN coveragefilelog AS cfl ON (cfl.fileid=cf.id)
                    WHERE cf.fullpath='$fullpath' AND cfl.buildid='$this->BuildId'");
                    if (pdo_num_rows($coveragefile) == 0) {
                        // Create an empty file if doesn't exist.
                        pdo_query("INSERT INTO coveragefile (fullpath, crc32) VALUES ('$fullpath', 0)");
                        $fileid = pdo_insert_id('coveragefile');
                    } else {
                        $coveragefile_array = pdo_fetch_array($coveragefile);
                        $fileid = $coveragefile_array['id'];
                    }
                    $coverage->CoverageFile->Id = $fileid;
                }

                $covered = $coverage->Covered;
                $loctested = $coverage->LocTested;
                $locuntested = $coverage->LocUntested;
                $branchstested = $coverage->BranchesTested;
                $branchsuntested = $coverage->BranchesUntested;
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
                if (empty($branchstested)) {
                    $branchstested = 0;
                }
                if (empty($branchsuntested)) {
                    $branchsuntested = 0;
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
                    $existing_row_updated = \DB::transaction(function () use ($coverage, $covered, $loctested, $locuntested, $branchstested, $branchsuntested, $functionstested, $functionsuntested) {
                        $existing_coverage_row = \DB::table('coverage')
                            ->where('buildid', $this->BuildId)
                            ->where('fileid', $coverage->CoverageFile->Id)
                            ->lockForUpdate()
                            ->first();
                        if ($existing_coverage_row) {
                            \DB::table('coverage')
                                ->where('buildid', $this->BuildId)
                                ->where('fileid', $coverage->CoverageFile->Id)
                                ->update([
                                    'covered' => $covered,
                                    'loctested' => $loctested,
                                    'locuntested' => $locuntested,
                                    'branchstested' => $branchstested,
                                    'branchsuntested' => $branchsuntested,
                                    'functionstested' => $functionstested,
                                    'functionsuntested' => $functionsuntested,
                                ]);
                            return true;
                        }
                        return false;
                    });
                }
                if (!$existing_row_updated) {
                    \DB::table('coverage')->insert([
                        'buildid' => $this->BuildId,
                        'fileid' => $fileid,
                        'covered' => $covered,
                        'loctested' => $loctested,
                        'locuntested' => $locuntested,
                        'branchstested' => $branchstested,
                        'branchsuntested' => $branchsuntested,
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
            $summary_updated = \DB::transaction(function () use (&$delta_tested, &$delta_untested) {
                $existing_summary_row = \DB::table('coveragesummary')
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
                $rows = \DB::table('coverage')->where('buildid', $this->BuildId)->get();
                foreach ($rows as $row) {
                    $this->LocTested += $row->loctested;
                    $this->LocUntested += $row->locuntested;
                }

                // Update the existing record with this information.
                \DB::table('coveragesummary')
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
            \DB::table('coveragesummary')->insert([
                'buildid' => $this->BuildId,
                'loctested' => $this->LocTested,
                'locuntested' => $this->LocUntested,
            ]);
        }

        // If this is a child build then update the parent's summary as well.
        $build_row = \DB::table('build')->where('id', $this->BuildId)->first();
        if ($build_row) {
            $parentid = $build_row->parentid;
            if ($parentid > 0) {
                \DB::transaction(function () use ($parentid, $delta_tested, $delta_untested) {
                    $parent_summary = \DB::table('coveragesummary')
                        ->where('buildid', $parentid)
                        ->lockForUpdate()
                        ->first();

                    if (!$parent_summary) {
                        \DB::table('coveragesummary')->insert([
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
                        \DB::table('coveragesummary')
                        ->where('buildid', $parentid)
                        ->update([
                            'loctested' => \DB::raw("loctested + $delta_tested"),
                            'locuntested' => \DB::raw("locuntested + $delta_untested"),
                        ]);
                    }
                });
            }
        }
        return true;
    }   // Insert()

    /** Compute the coverage summary diff */
    public function ComputeDifference($previous_parentid=null)
    {
        $build = new Build();
        $build->Id = $this->BuildId;
        $previousBuildId = $build->GetPreviousBuildId($previous_parentid);
        if ($previousBuildId < 1) {
            return;
        }

        // Look at the number of errors and warnings differences
        $coverage = pdo_query('SELECT loctested,locuntested FROM coveragesummary WHERE buildid=' . qnum($this->BuildId));
        if (!$coverage) {
            add_last_sql_error('CoverageSummary:ComputeDifference');
            return false;
        }
        $coverage_array = pdo_fetch_array($coverage);
        $loctested = $coverage_array['loctested'];
        $locuntested = $coverage_array['locuntested'];

        $previouscoverage = pdo_query('SELECT loctested,locuntested FROM coveragesummary WHERE buildid=' . qnum($previousBuildId));
        if (pdo_num_rows($previouscoverage) > 0) {
            $previouscoverage_array = pdo_fetch_array($previouscoverage);
            $previousloctested = $previouscoverage_array['loctested'];
            $previouslocuntested = $previouscoverage_array['locuntested'];

            $summaryDiff = new CoverageSummaryDiff();
            $summaryDiff->BuildId = $this->BuildId;
            $loctesteddiff = $loctested - $previousloctested;
            $locuntesteddiff = $locuntested - $previouslocuntested;

            // Don't log if no diff unless an entry already exists
            // for this build.
            if ($summaryDiff->Exists() || $loctesteddiff != 0 || $locuntesteddiff != 0) {
                $summaryDiff->LocTested = $loctesteddiff;
                $summaryDiff->LocUntested = $locuntesteddiff;
                $summaryDiff->Insert();
            }
        }
    }

    /** Return the list of buildid which are contributing to the dashboard */
    public function GetBuilds($projectid, $timestampbegin, $timestampend)
    {
        $buildids = array();
        $coverage = pdo_query('SELECT buildid FROM coveragesummary,build WHERE coveragesummary.buildid=build.id
                AND build.projectid=' . qnum($projectid) . " AND build.starttime>'" . $timestampbegin . "'
                AND endtime<'" . $timestampend . "'");
        if (!$coverage) {
            add_last_sql_error('CoverageSummary:GetBuilds');
            return false;
        }
        while ($coverage_array = pdo_fetch_array($coverage)) {
            $buildids[] = $coverage_array['buildid'];
        }
        return $buildids;
    }

    /** Return whether or not a CoverageSummary exists for this build. */
    public function Exists()
    {
        if (!$this->BuildId) {
            return false;
        }

        $exists_result = pdo_single_row_query(
            'SELECT COUNT(1) AS numrows FROM coveragesummary
                WHERE buildid=' . qnum($this->BuildId));

        if ($exists_result && array_key_exists('numrows', $exists_result)) {
            $numrows = $exists_result['numrows'];
            if ($numrows > 0) {
                return true;
            }
        }
        return false;
    }
}
