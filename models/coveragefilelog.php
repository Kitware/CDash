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
require_once 'models/coveragefile.php';
require_once 'models/coveragesummary.php';

class coveragefilelog
{
    public $BuildId;
    public $FileId;
    public $Lines;
    public $Branches;

    public function __construct()
    {
        $this->Lines = array();
        $this->Branches = array();
    }

    public function AddLine($number, $code)
    {
        if (array_key_exists($number, $this->Lines)) {
            $this->Lines[$number] += $code;
        } else {
            $this->Lines[$number] = $code;
        }
    }

    public function AddBranch($number, $covered, $total)
    {
        $this->Branches[$number] = "$covered/$total";
    }

    /** Update the content of the file */
    public function Insert($append = false)
    {
        if (!$this->BuildId || !is_numeric($this->BuildId)) {
            add_log('BuildId not set', 'CoverageFileLog::Insert()', LOG_ERR,
                0, $this->BuildId, CDASH_OBJECT_COVERAGE, $this->FileId);
            return false;
        }

        if (!$this->FileId || !is_numeric($this->FileId)) {
            add_log('FileId not set', 'CoverageFileLog::Insert()', LOG_ERR,
                0, $this->BuildId, CDASH_OBJECT_COVERAGE, $this->FileId);
            return false;
        }

        pdo_begin_transaction();
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
                $sql_command = 'UPDATE';
                $sql = "UPDATE coveragefilelog SET log='$log'
                WHERE buildid=" . qnum($this->BuildId) . ' AND
                fileid=' . qnum($this->FileId);
            } else {
                $sql_command = 'INSERT';
                $sql = 'INSERT INTO coveragefilelog (buildid,fileid,log) VALUES ';
                $sql .= '(' . qnum($this->BuildId) . ',' . qnum($this->FileId) . ",'" . $log . "')";
            }
            pdo_query($sql);
            add_last_sql_error("CoverageFileLog::$sql_command()");
        }
        pdo_commit();
        $this->UpdateAggregate();
        return true;
    }

    public function Load($for_update = false)
    {
        global $CDASH_DB_TYPE;

        $query = 'SELECT log FROM coveragefilelog
            WHERE fileid=' . qnum($this->FileId) . '
            AND buildid=' . qnum($this->BuildId);
        if ($for_update) {
            $query .= ' FOR UPDATE';
        }

        $result = pdo_query($query);
        if (!$result || pdo_num_rows($result) < 1) {
            return false;
        }

        $row = pdo_fetch_array($result);
        if ($CDASH_DB_TYPE == 'pgsql') {
            $log = stream_get_contents($row['log']);
        } else {
            $log = $row['log'];
        }

        $log_entries = explode(';', $log);
        foreach ($log_entries as $log_entry) {
            if (empty($log_entry)) {
                continue;
            }
            list($line, $value) = explode(':', $log_entry);
            if ($line[0] === 'b') {
                // Branch coverage
                $line = ltrim($line, 'b');
                list($covered, $total) = explode('/', $value);
                $this->AddBranch($line, $covered, $total);
            } else {
                // Line coverage
                $this->AddLine($line, $value);
            }
        }
        return true;
    }

    public function GetStats()
    {
        $stats = array();
        $stats['loctested'] = 0;
        $stats['locuntested'] = 0;
        $stats['branchestested'] = 0;
        $stats['branchesuntested'] = 0;
        foreach ($this->Lines as $line => $timesHit) {
            if ($timesHit > 0) {
                $stats['loctested'] += 1;
            } else {
                $stats['locuntested'] += 1;
            }
        }

        foreach ($this->Branches as $line => $value) {
            list($timesHit, $total) = explode('/', $value);
            if ($timesHit > 0) {
                $stats['branchestested'] += 1;
            } else {
                $stats['branchesuntested'] += 1;
            }
        }
        return $stats;
    }

    /** Update the aggregate coverage build to include these results. */
    public function UpdateAggregate()
    {
        $build = new Build();
        $build->Id = $this->BuildId;
        $build->FillFromId($this->BuildId);

        // Only nightly builds count towards aggregate coverage.
        if ($build->Type !== 'Nightly' ||
            $build->Name === 'Aggregate Coverage'
        ) {
            return;
        }

        // Get the site ID for 'CDash Server'.
        $server = new Site();
        $server->Name = 'CDash Server';
        if (!$server->Exists()) {
            // Create it if it doesn't exist.
            $server_ip = $_SERVER['SERVER_ADDR'];
            $server->Ip = $server_ip;
            $server->Insert();
        }

        // Get the nightly start time for this project.
        $row = pdo_single_row_query("SELECT nightlytime FROM project WHERE id='$build->ProjectId'");
        if (!$row || !array_key_exists('nightlytime', $row)) {
            return;
        }
        $nightly_time = $row['nightlytime'];

        // Get the beginning and end of this testing day.
        $build_date = $build->GetDate();
        list($previousdate, $currentstarttime, $nextdate) =
            get_dates($build_date, $nightly_time);
        $beginning_timestamp = $currentstarttime;
        $end_timestamp = $currentstarttime + 3600 * 24;
        $beginning_UTCDate = gmdate(FMT_DATETIME, $beginning_timestamp);
        $end_UTCDate = gmdate(FMT_DATETIME, $end_timestamp);

        // Use all this information to find the build ID for this day's edition
        // of 'Aggregate Coverage'.
        $query =
            "SELECT id FROM build
            WHERE name='Aggregate Coverage' AND
            siteid='$server->Id' AND
            starttime <'$end_UTCDate' AND starttime>='$beginning_UTCDate'";
        $row = pdo_single_row_query($query);
        if (!$row || !array_key_exists('id', $row)) {
            // If the aggregate coverage build doesn't exist we add it here.
            $aggregateBuild = new Build();
            $aggregateBuild->Name = 'Aggregate Coverage';
            $aggregateBuild->SiteId = $server->Id;
            $aggregateBuild->SetStamp($build->GetStamp());
            $aggregateBuild->ProjectId = $build->ProjectId;

            $aggregateBuild->StartTime = $build->StartTime;
            $aggregateBuild->EndTime = $build->EndTime;
            $aggregateBuild->SubmitTime = gmdate(FMT_DATETIME);
            $aggregateBuild->InsertErrors = false;
            add_build($aggregateBuild);
            $aggregateBuildId = $aggregateBuild->Id;
        } else {
            $aggregateBuildId = $row['id'];
        }

        // Abort if this log refers to a different version of the file
        // than the one already contained in the aggregate.
        $row = pdo_single_row_query(
            "SELECT id, fullpath FROM coveragefile WHERE id='$this->FileId'");
        $path = $row['fullpath'];
        $row = pdo_single_row_query(
            "SELECT id FROM coveragefile AS cf
                INNER JOIN coveragefilelog AS cfl ON (cfl.fileid=cf.id)
                WHERE cfl.buildid='$aggregateBuildId' AND cf.fullpath='$path'");
        if ($row && array_key_exists('id', $row) &&
            $row['id'] !== $this->FileId
        ) {
            add_log("Not appending coverage of '$path' to aggregate as it " .
                'already contains a different version of this file.',
                'CoverageSummary::UpdateAggregate', LOG_INFO,
                $this->BuildId);
            return;
        }

        // Append these results to the aggregate coverage log.
        $aggregateLog = clone $this;
        $aggregateLog->BuildId = $aggregateBuildId;
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
        if ($aggregateCoverage->LocTested > 0) {
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
        $aggregateSummary->ComputeDifference();
    }
}
