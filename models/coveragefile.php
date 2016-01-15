<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE.  See the above copyright notices for more information.

  =========================================================================*/
/** This class shouldn't be used externally */
class coveragefile
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

        include("config/config.php");

        // Compute the crc32 of the file (before compression for backward compatibility)
        $this->Crc32 = crc32($this->FullPath.$this->File);

        $this->FullPath = pdo_real_escape_string($this->FullPath);
        if ($CDASH_USE_COMPRESSION) {
            $file = gzcompress($this->File);
            if ($file === false) {
                $file = $this->File;
            } else {
                if ($CDASH_DB_TYPE == "pgsql") {
                    if (strlen($this->File)<2000) {
                        // compression doesn't help for small chunk

                        $file = $this->File;
                    }
                    $file = pg_escape_bytea(base64_encode($file)); // hopefully does the escaping correctly
                }
            }
        } else {
            $file = $this->File;
            if ($CDASH_DB_TYPE == "pgsql") {
                $file = pg_escape_bytea($file);
            }
        }
        $file = pdo_real_escape_string($file);

        $coveragefile = pdo_query("SELECT id FROM coveragefile WHERE crc32=".qnum($this->Crc32));
        add_last_sql_error("CoverageFile:Update");

        if (pdo_num_rows($coveragefile)>0) {
            // we have the same crc32

            $coveragefile_array = pdo_fetch_array($coveragefile);
            $this->Id = $coveragefile_array["id"];

            // Update the current coverage.fileid
            $coverage = pdo_query("SELECT c.fileid FROM coverage AS c,coveragefile AS cf
                    WHERE c.fileid=cf.id AND c.buildid=".qnum($buildid)."
                    AND cf.fullpath='$this->FullPath'");
            $coverage_array = pdo_fetch_array($coverage);
            $prevfileid = $coverage_array["fileid"];

            pdo_query("UPDATE coverage SET fileid=".qnum($this->Id)." WHERE buildid=".qnum($buildid)." AND fileid=".qnum($prevfileid));
            add_last_sql_error("CoverageFile:Update");

            $row = pdo_single_row_query("SELECT COUNT(*) AS c FROM label2coveragefile WHERE buildid=".qnum($buildid)." AND coveragefileid=".qnum($prevfileid));
            if (isset($row['c']) && $row['c']>0) {
                pdo_query("UPDATE label2coveragefile SET coveragefileid=".qnum($this->Id)." WHERE buildid=".qnum($buildid)." AND coveragefileid=".qnum($prevfileid));
                add_last_sql_error("CoverageFile:Update");
            }

            // Remove the file if the crc32 is NULL
            pdo_query("DELETE FROM coveragefile WHERE id=".qnum($prevfileid)." AND file IS NULL and crc32 IS NULL");
            add_last_sql_error("CoverageFile:Update");
        } else {
            // The file doesn't exist in the database

            // We find the current fileid based on the name and the file should be null
            $coveragefile = pdo_query("SELECT cf.id,cf.file FROM coverage AS c,coveragefile AS cf
                    WHERE c.fileid=cf.id AND c.buildid=".qnum($buildid)."
                    AND cf.fullpath='$this->FullPath' ORDER BY cf.id ASC");
            $coveragefile_array = pdo_fetch_array($coveragefile);

            // The GcovTarHandler creates coveragefiles before coverages
            // so we need a simpler query in this case.
            if (empty($coveragefile_array)) {
                $coveragefile = pdo_query(
                        "SELECT id, file FROM coveragefile
                        WHERE fullpath='$this->FullPath' AND file IS NULL
                        ORDER BY id ASC");
                $coveragefile_array = pdo_fetch_array($coveragefile);
            }

            $this->Id = $coveragefile_array["id"];
            pdo_query("UPDATE coveragefile SET file='$file',crc32='$this->Crc32' WHERE id=".qnum($this->Id));
            add_last_sql_error("CoverageFile:Update");
        }
        return true;
    }

    /** Get the path */
    public function GetPath()
    {
        if (!$this->Id) {
            echo "CoverageFile GetPath(): Id not set";
            return false;
        }

        $coverage = pdo_query("SELECT fullpath FROM coveragefile WHERE id=".qnum($this->Id));
        if (!$coverage) {
            add_last_sql_error("Coverage GetPath");
            return false;
        }

        $coverage_array = pdo_fetch_array($coverage);
        return $coverage_array['fullpath'];
    }  // GetPath

    /** Return the metric */
    public function GetMetric()
    {
        if (!$this->Id) {
            echo "CoverageFile GetMetric(): Id not set";
            return false;
        }

        $coveragefile = pdo_query("SELECT loctested,locuntested,branchstested,branchsuntested,
                functionstested,functionsuntested FROM coverage WHERE fileid=".qnum($this->Id));
        if (!$coveragefile) {
            add_last_sql_error("CoverageFile:GetMetric()");
            return false;
        }

        if (pdo_num_rows($coveragefile)==0) {
            return false;
        }

        $coveragemetric = 1;
        $coveragefile_array = pdo_fetch_array($coveragefile);
        $loctested = $coveragefile_array["loctested"];
        $locuntested = $coveragefile_array["locuntested"];
        $branchstested = $coveragefile_array["branchstested"];
        $branchsuntested = $coveragefile_array["branchsuntested"];
        $functionstested = $coveragefile_array["functionstested"];
        $functionsuntested = $coveragefile_array["functionsuntested"];

        // Compute the coverage metric for bullseye
        if ($branchstested>0 || $branchsuntested>0 || $functionstested>0 || $functionsuntested>0) {
            // Metric coverage
            $metric = 0;
            if ($functionstested+$functionsuntested>0) {
                $metric += $functionstested/($functionstested+$functionsuntested);
            }
            if ($branchsuntested+$branchsuntested>0) {
                $metric += $branchsuntested/($branchstested+$branchsuntested);
                $metric /= 2.0;
            }
            $coveragemetric = $metric;
            $this->LastPercentCoverage = $metric*100;
        } else {
            // coverage metric for gcov

            $coveragemetric = ($loctested+10)/($loctested+$locuntested+10);
            $this->LastPercentCoverage = ($loctested/($loctested+$locuntested))*100;
        }

        return $coveragemetric;
    } // end function GetMetric

    // Get the percent coverage
    public function GetLastPercentCoverage()
    {
        return $this->LastPercentCoverage;
    }

    // Get the fileid from the name
    public function GetIdFromName($file, $buildid)
    {
        $coveragefile = pdo_query("SELECT id FROM coveragefile,coverage WHERE fullpath LIKE '%".$file."%'
                AND coverage.buildid=".qnum($buildid)." AND coverage.fileid=coveragefile.id");
        if (!$coveragefile) {
            add_last_sql_error("CoverageFile:GetIdFromName()");
            return false;
        }
        if (pdo_num_rows($coveragefile)==0) {
            return false;
        }
        $coveragefile_array = pdo_fetch_array($coveragefile);
        return $coveragefile_array['id'];
    }
}
