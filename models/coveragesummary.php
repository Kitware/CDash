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

        // Add the coverages
        // Construct the SQL query
        if (count($this->Coverages) > 0) {
            foreach ($this->Coverages as &$coverage) {
                $sql = 'INSERT INTO coverage (buildid,fileid,covered,loctested,locuntested,branchstested,branchsuntested,
                    functionstested,functionsuntested) VALUES ';

                $fullpath = $coverage->CoverageFile->FullPath;

                // GcovTarHandler creates its own coveragefiles, no need to do
                // it again here.
                $fileid = -1;
                if (!empty($coverage->CoverageFile->Crc32)) {
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
                        pdo_query("INSERT INTO coveragefile (fullpath) VALUES ('$fullpath')");
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

                if ($append) {
                    // UPDATE (instead of INSERT) if this coverage already
                    // exists.
                    pdo_begin_transaction();
                    $row = pdo_single_row_query(
                        'SELECT * FROM coverage
                            WHERE buildid=' . qnum($this->BuildId) . ' AND
                            fileid=' . qnum($coverage->CoverageFile->Id) . '
                            FOR UPDATE');
                    if ($row && array_key_exists('1', $row)) {
                        $query = 'UPDATE coverage SET
                            covered=' . qnum($covered) . ',
                            loctested=' . qnum($loctested) . ',
                            locuntested=' . qnum($locuntested) . ',
                            branchstested=' . qnum($branchstested) . ',
                            branchsuntested=' . qnum($branchsuntested) . ',
                            functionstested=' . qnum($functionstested) . ',
                            functionsuntested=' . qnum($functionsuntested) . '
                            WHERE buildid=' . qnum($this->BuildId) . ' AND
                            fileid=' . qnum($coverage->CoverageFile->Id);
                        if (!pdo_query($query)) {
                            add_last_sql_error('CoverageSummary Update Coverage');
                            pdo_rollback();
                            return false;
                        }
                        pdo_commit();
                        continue;
                    }
                    pdo_commit();
                }

                $sql .= '(' . qnum($this->BuildId) . ',' . qnum($fileid) . ',' . qnum($covered) . ',' . qnum($loctested) . ',' . qnum($locuntested) . ',
                    ' . qnum($branchstested) . ',' . qnum($branchsuntested) .
                    ',' . qnum($functionstested) . ',' . qnum($functionsuntested) . ')';

                // Insert into coverage
                if (!pdo_query($sql)) {
                    add_last_sql_error('CoverageSummary Insert Coverage');
                    return false;
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
            pdo_begin_transaction();
            $row = pdo_single_row_query(
                'SELECT loctested, locuntested FROM coveragesummary
                    WHERE buildid=' . qnum($this->BuildId) . '
                    FOR UPDATE');
            if ($row && array_key_exists('loctested', $row)) {
                $previous_loctested = $row['loctested'];
                $previous_locuntested = $row['locuntested'];
                // Recompute how many lines were tested & untested
                // based on all files covered by this build.
                $this->LocTested = 0;
                $this->LocUntested = 0;
                $query = 'SELECT loctested, locuntested FROM coverage
                    WHERE buildid=' . qnum($this->BuildId);
                $results = pdo_query($query);
                if (!$results) {
                    add_last_sql_error('CoverageSummary:GetExistingCoverage');
                    pdo_rollback();
                    return false;
                }
                while ($row = pdo_fetch_array($results)) {
                    $this->LocTested += $row['loctested'];
                    $this->LocUntested += $row['locuntested'];
                }

                // Update the existing record with this information.
                $query = 'UPDATE coveragesummary SET
                    loctested=' . qnum($this->LocTested) . ',
                    locuntested=' . qnum($this->LocUntested) . '
                    WHERE buildid=' . qnum($this->BuildId);
                if (!pdo_query($query)) {
                    add_last_sql_error('CoverageSummary Update');
                    pdo_rollback();
                    return false;
                }
                $summary_updated = true;

                // Record how loctested and locuntested changed as a result
                // of this update.
                $delta_tested = $this->LocTested - $previous_loctested;
                $delta_untested = $this->LocUntested - $previous_locuntested;
            }
            pdo_commit();
        }

        if (!$summary_updated) {
            $query = 'INSERT INTO coveragesummary
                (buildid,loctested,locuntested)
                VALUES (' . qnum($this->BuildId) . ',' . qnum($this->LocTested) . ',' . qnum($this->LocUntested) . ')';
            if (!pdo_query($query)) {
                add_last_sql_error('CoverageSummary Insert');
                return false;
            }
        }

        // If this is a child build then update the parent's summary as well.
        $parent = pdo_single_row_query(
            'SELECT parentid FROM build WHERE id=' . qnum($this->BuildId));
        if ($parent && array_key_exists('parentid', $parent)) {
            $parentid = $parent['parentid'];
            if ($parentid > 0) {
                pdo_begin_transaction();
                $exists = pdo_query(
                    'SELECT * FROM coveragesummary
                        WHERE buildid=' . qnum($parentid) . '
                        FOR UPDATE');
                if (pdo_num_rows($exists) == 0) {
                    $query = 'INSERT INTO coveragesummary
                        (buildid,loctested,locuntested)
                        VALUES
                        (' . qnum($parentid) . ',' . qnum($this->LocTested) . ',' . qnum($this->LocUntested) . ')';
                } else {
                    if (!isset($delta_tested)) {
                        $delta_tested = $this->LocTested;
                    }
                    if (!isset($delta_untested)) {
                        $delta_untested = $this->LocUntested;
                    }
                    $query =
                        'UPDATE coveragesummary SET
                        loctested = loctested + ' . qnum($delta_tested) . ',
                        locuntested = locuntested + ' . qnum($delta_untested) . '
                            WHERE buildid=' . qnum($parentid);
                }
                if (!pdo_query($query)) {
                    add_last_sql_error('CoverageSummary Parent Update');
                    pdo_rollback();
                    return false;
                }
                pdo_commit();
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
