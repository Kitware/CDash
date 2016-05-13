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

/** This class shouldn't be used externally */
class CoverageFile
{
    public $Id;
    public $File;
    public $FullPath;
    public $Crc32;

    private $LastPercentCoverage; // used when GetMetric

    /** Update the content of the file */
    public function Update($buildid)
    {
        if (!is_numeric($buildid) || $buildid == 0) {
            return;
        }

        global $CDASH_USE_COMPRESSION;

        $this->FullPath = trim($this->FullPath);

        // Compute the crc32 of the file (before compression for backward compatibility)
        $this->Crc32 = crc32($this->FullPath . $this->File);

        $this->FullPath = pdo_real_escape_string($this->FullPath);
        if ($CDASH_USE_COMPRESSION) {
            $file = gzcompress($this->File);
            if ($file === false) {
                $file = $this->File;
            } else {
                if (strlen($this->File) < 2000) {
                    // compression doesn't help for small chunk
                    $file = $this->File;
                }
                $file = base64_encode($file);
            }
        } else {
            $file = $this->File;
        }
        $file = pdo_real_escape_string($file);

        $coveragefile = pdo_query('SELECT id FROM coveragefile WHERE crc32=' . qnum($this->Crc32));
        add_last_sql_error('CoverageFile:Update');

        if (pdo_num_rows($coveragefile) > 0) {
            // we have the same crc32

            $coveragefile_array = pdo_fetch_array($coveragefile);
            $this->Id = $coveragefile_array['id'];

            // Update the current coverage.fileid
            $coverage = pdo_query('SELECT c.fileid FROM coverage AS c,coveragefile AS cf
                    WHERE c.fileid=cf.id AND c.buildid=' . qnum($buildid) . "
                    AND cf.fullpath='$this->FullPath'");
            $coverage_array = pdo_fetch_array($coverage);
            $prevfileid = $coverage_array['fileid'];

            pdo_query('UPDATE coverage SET fileid=' . qnum($this->Id) . ' WHERE buildid=' . qnum($buildid) . ' AND fileid=' . qnum($prevfileid));
            add_last_sql_error('CoverageFile:Update');

            $row = pdo_single_row_query('SELECT COUNT(*) AS c FROM label2coveragefile WHERE buildid=' . qnum($buildid) . ' AND coveragefileid=' . qnum($prevfileid));
            if (isset($row['c']) && $row['c'] > 0) {
                pdo_query('UPDATE label2coveragefile SET coveragefileid=' . qnum($this->Id) . ' WHERE buildid=' . qnum($buildid) . ' AND coveragefileid=' . qnum($prevfileid));
                add_last_sql_error('CoverageFile:Update');
            }

            // Remove the file if the crc32 is NULL
            pdo_query('DELETE FROM coveragefile WHERE id=' . qnum($prevfileid) . ' AND file IS NULL and crc32 IS NULL');
            add_last_sql_error('CoverageFile:Update');
        } else {
            // The file doesn't exist in the database

            // We find the current fileid based on the name and the file should be null
            $coveragefile = pdo_query('SELECT cf.id,cf.file FROM coverage AS c,coveragefile AS cf
                    WHERE c.fileid=cf.id AND c.buildid=' . qnum($buildid) . "
                    AND cf.fullpath='$this->FullPath' ORDER BY cf.id ASC");
            $coveragefile_array = pdo_fetch_array($coveragefile);

            // The GcovTarHandler creates coveragefiles before coverages
            // so we need a simpler query in this case.
            if (!empty($coveragefile_array)) {
                $this->Id = $coveragefile_array['id'];
            } else {
                $coveragefile = pdo_query(
                    "SELECT id, file FROM coveragefile
                        WHERE fullpath='$this->FullPath' AND file IS NULL
                        ORDER BY id ASC");
                $coveragefile_array = pdo_fetch_array($coveragefile);
            }
            if (!empty($coveragefile_array)) {
                $this->Id = $coveragefile_array['id'];
            } else {
                // If we still haven't found an existing fileid
                // we insert one here.
                pdo_query(
                    "INSERT INTO coveragefile (fullpath)
                        VALUES ('$this->FullPath')");
                $this->Id = pdo_insert_id('coveragefile');
            }

            $pdo = get_link_identifier()->getPdo();
            $stmt = $pdo->prepare(
                    'UPDATE coveragefile SET file=:file, crc32=:crc32 WHERE id=:id');
            $stmt->bindParam(':file', $file, PDO::PARAM_LOB);
            $stmt->bindParam(':crc32', $this->Crc32);
            $stmt->bindParam(':id', $this->Id);
            $stmt->execute();
            add_last_sql_error('CoverageFile:Update');
        }
        return true;
    }

    /** Get the path */
    public function GetPath()
    {
        if (!$this->Id) {
            echo 'CoverageFile GetPath(): Id not set';
            return false;
        }

        $coverage = pdo_query('SELECT fullpath FROM coveragefile WHERE id=' . qnum($this->Id));
        if (!$coverage) {
            add_last_sql_error('Coverage GetPath');
            return false;
        }

        $coverage_array = pdo_fetch_array($coverage);
        return $coverage_array['fullpath'];
    }  // GetPath

    /** Return the metric */
    public function GetMetric()
    {
        if (!$this->Id) {
            echo 'CoverageFile GetMetric(): Id not set';
            return false;
        }

        $coveragefile = pdo_query('SELECT loctested,locuntested,branchstested,branchsuntested,
                functionstested,functionsuntested FROM coverage WHERE fileid=' . qnum($this->Id));
        if (!$coveragefile) {
            add_last_sql_error('CoverageFile:GetMetric()');
            return false;
        }

        if (pdo_num_rows($coveragefile) == 0) {
            return false;
        }

        $coveragemetric = 1;
        $coveragefile_array = pdo_fetch_array($coveragefile);
        $loctested = $coveragefile_array['loctested'];
        $locuntested = $coveragefile_array['locuntested'];
        $branchstested = $coveragefile_array['branchstested'];
        $branchsuntested = $coveragefile_array['branchsuntested'];
        $functionstested = $coveragefile_array['functionstested'];
        $functionsuntested = $coveragefile_array['functionsuntested'];

        // Compute the coverage metric for bullseye
        if ($branchstested > 0 || $branchsuntested > 0 || $functionstested > 0 || $functionsuntested > 0) {
            // Metric coverage
            $metric = 0;
            if ($functionstested + $functionsuntested > 0) {
                $metric += $functionstested / ($functionstested + $functionsuntested);
            }
            if ($branchsuntested + $branchsuntested > 0) {
                $metric += $branchsuntested / ($branchstested + $branchsuntested);
                $metric /= 2.0;
            }
            $coveragemetric = $metric;
            $this->LastPercentCoverage = $metric * 100;
        } else {
            // coverage metric for gcov

            $coveragemetric = ($loctested + 10) / ($loctested + $locuntested + 10);
            $this->LastPercentCoverage = ($loctested / ($loctested + $locuntested)) * 100;
        }
        return $coveragemetric;
    }

    // Get the percent coverage
    public function GetLastPercentCoverage()
    {
        return $this->LastPercentCoverage;
    }

    // Get the fileid from the name
    public function GetIdFromName($file, $buildid)
    {
        $coveragefile = pdo_query("SELECT id FROM coveragefile,coverage WHERE fullpath LIKE '%" . $file . "%'
                AND coverage.buildid=" . qnum($buildid) . ' AND coverage.fileid=coveragefile.id');
        if (!$coveragefile) {
            add_last_sql_error('CoverageFile:GetIdFromName()');
            return false;
        }
        if (pdo_num_rows($coveragefile) == 0) {
            return false;
        }
        $coveragefile_array = pdo_fetch_array($coveragefile);
        return $coveragefile_array['id'];
    }

    // Populate $this from existing database results.
    public function Load()
    {
        global $CDASH_USE_COMPRESSION;

        if (!$this->Id) {
            return false;
        }

        $row = pdo_single_row_query(
            "SELECT * FROM coveragefile WHERE id='$this->Id'");
        if (!$row || !array_key_exists('id', $row)) {
            return false;
        }

        $this->FullPath = $row['fullpath'];
        $this->Crc32 = $row['crc32'];

        if ($CDASH_USE_COMPRESSION) {
            if (is_resource($row['file'])) {
                $this->File = base64_decode(stream_get_contents($row['file']));
            } else {
                $this->File = base64_decode($row['file']);
            }

            @$uncompressedrow = gzuncompress($this->File);
            if ($uncompressedrow !== false) {
                $this->File = $uncompressedrow;
            }
        } else {
            $this->File = $row['file'];
        }

        return true;
    }

    // Remove the extra <br> tag that is added to the end of the file
    // by some of our XML handlers.
    public function TrimLastNewline()
    {
        // Remove trailing <br> tag.
        if (substr($this->File, -4) === '<br>') {
            $this->File = substr($this->File, 0, strlen($this->File) -4);
        }
    }
}
