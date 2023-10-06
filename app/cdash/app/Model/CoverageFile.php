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
use PDO;

/** This class shouldn't be used externally */
class CoverageFile
{
    public $Id;
    public $File;
    public $FullPath;
    public $Crc32;

    private $LastPercentCoverage; // used when GetMetric
    private $PDO;
    public function __construct()
    {
        $this->PDO = Database::getInstance()->getPdo();
    }

    /** Update the content of the file */
    public function Update($buildid)
    {
        if (!is_numeric($buildid) || $buildid == 0) {
            return;
        }

        $this->FullPath = trim($this->FullPath);

        // Compute the crc32 of the file (before compression for backward compatibility)
        $this->Crc32 = crc32($this->FullPath . $this->File);
        if (config('cdash.use_compression')) {
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

        $stmt = $this->PDO->prepare(
            'SELECT id FROM coveragefile WHERE crc32=:crc32');
        $stmt->bindParam(':crc32', $this->Crc32);
        pdo_execute($stmt);
        $existing_file_row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($existing_file_row)) {
            // A file already exists with this crc32.
            // Update this object to use the previously existing result's id.
            $this->Id = $existing_file_row['id'];

            // Update the corresponding coverage row to use this fileid too.
            // First we query for the old fileid used by this coverage entry,
            // just to be sure that we're updating the correct record.
            $stmt = $this->PDO->prepare(
                'SELECT c.fileid FROM coverage AS c
                    INNER JOIN coveragefile AS cf ON (cf.id=c.fileid)
                    WHERE c.buildid=:buildid AND cf.fullpath=:fullpath');
            $stmt->bindParam(':buildid', $buildid);
            $stmt->bindParam(':fullpath', $this->FullPath);
            pdo_execute($stmt);
            $old_fileid_row = $stmt->fetch();
            if (is_array($old_fileid_row)) {
                $prevfileid = $old_fileid_row['fileid'];

                $stmt = $this->PDO->prepare(
                    'UPDATE coverage SET fileid=:fileid
                        WHERE buildid=:buildid AND fileid=:prevfileid');
                $stmt->bindParam(':fileid', $this->Id);
                $stmt->bindParam(':buildid', $buildid);
                $stmt->bindParam(':prevfileid', $prevfileid);
                pdo_execute($stmt);

                // Similarly update any labels if necessary.
                $stmt = $this->PDO->prepare(
                    'SELECT COUNT(*) AS c FROM label2coveragefile
                        WHERE buildid=:buildid AND coveragefileid=:prevfileid');
                $stmt->bindParam(':buildid', $buildid);
                $stmt->bindParam(':prevfileid', $prevfileid);
                pdo_execute($stmt);
                $count_labels_row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($count_labels_row['c'] > 0) {
                    $stmt = $this->PDO->prepare(
                        'UPDATE label2coveragefile SET coveragefileid=:fileid
                            WHERE buildid=:buildid AND coveragefileid=:prevfileid');
                    $stmt->bindParam(':fileid', $this->Id);
                    $stmt->bindParam(':buildid', $buildid);
                    $stmt->bindParam(':prevfileid', $prevfileid);
                    pdo_execute($stmt);
                }

                // Remove the file if the crc32 is NULL
                DB::delete('
                    DELETE FROM coveragefile
                    WHERE
                        id = ?
                        AND file IS NULL
                        AND crc32 IS NULL
                ', [$prevfileid]);
            }
        } else {
            // The file doesn't exist in the database

            // We find the current fileid based on the name and the file should be null
            $stmt = $this->PDO->prepare(
                'SELECT cf.id, cf.file
                    FROM coverage AS c
                    INNER JOIN coveragefile AS cf ON (cf.id=c.fileid)
                    WHERE c.buildid=:buildid AND cf.fullpath=:fullpath
                    ORDER BY cf.id ASC');
            $stmt->bindParam(':buildid', $buildid);
            $stmt->bindParam(':fullpath', $this->FullPath);
            pdo_execute($stmt);
            $coveragefile_row = $stmt->fetch(PDO::FETCH_ASSOC);

            // The GcovTarHandler creates coveragefiles before coverages
            // so we need a simpler query in this case.
            if (is_array($coveragefile_row)) {
                $this->Id = $coveragefile_row['id'];
            } else {
                $stmt = $this->PDO->prepare(
                    'SELECT id, file FROM coveragefile
                        WHERE fullpath=:fullpath AND file IS NULL
                        ORDER BY id ASC');
                $stmt->bindParam(':fullpath', $this->FullPath);
                pdo_execute($stmt);
                $coveragefile_row = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if (is_array($coveragefile_row)) {
                $this->Id = $coveragefile_row['id'];
            } else {
                // If we still haven't found an existing fileid
                // we insert one here.
                $stmt = $this->PDO->prepare(
                    'INSERT INTO coveragefile (fullpath, crc32)
                        VALUES (:fullpath, 0)');
                $stmt->bindParam(':fullpath', $this->FullPath);
                pdo_execute($stmt);
                $this->Id = pdo_insert_id('coveragefile');
            }

            $stmt = $this->PDO->prepare(
                'UPDATE coveragefile SET file=:file, crc32=:crc32 WHERE id=:id');
            $stmt->bindParam(':file', $file, PDO::PARAM_LOB);
            $stmt->bindParam(':crc32', $this->Crc32);
            $stmt->bindParam(':id', $this->Id);
            pdo_execute($stmt);
        }
        return true;
    }

    /** Get the path */
    public function GetPath()
    {
        if (!$this->Id) {
            abort(500, 'CoverageFile GetPath(): Id not set');
        }

        $stmt = $this->PDO->prepare(
            'SELECT fullpath FROM coveragefile WHERE id=:id');
        $stmt->bindParam(':id', $this->Id);
        if (!pdo_execute($stmt)) {
            return false;
        }
        $row = $stmt->fetch();
        return $row['fullpath'];
    }  // GetPath

    /** Return the metric */
    public function GetMetric()
    {
        if (!$this->Id) {
            abort(500, 'CoverageFile GetMetric(): Id not set');
        }

        $stmt = $this->PDO->prepare(
            'SELECT loctested, locuntested, branchestested, branchesuntested,
                functionstested, functionsuntested
                FROM coverage WHERE fileid=:id');
        $stmt->bindParam(':id', $this->Id);
        if (!pdo_execute($stmt)) {
            return false;
        }

        $row = $stmt->fetch();
        if (!array_key_exists('loctested', $row)) {
            return false;
        }

        $coveragemetric = 1;
        $loctested = $row['loctested'];
        $locuntested = $row['locuntested'];
        $branchestested = $row['branchestested'];
        $branchesuntested = $row['branchesuntested'];
        $functionstested = $row['functionstested'];
        $functionsuntested = $row['functionsuntested'];

        // Compute the coverage metric for bullseye
        if ($branchestested > 0 || $branchesuntested > 0 || $functionstested > 0 || $functionsuntested > 0) {
            // Metric coverage
            $metric = 0;
            if ($functionstested + $functionsuntested > 0) {
                $metric += $functionstested / ($functionstested + $functionsuntested);
            }
            if ($branchesuntested + $branchesuntested > 0) {
                $metric += $branchesuntested / ($branchestested + $branchesuntested);
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
        $stmt = $this->PDO->prepare(
            'SELECT coveragefile.id FROM coveragefile
                INNER JOIN coverage ON (coveragefile.id=coverage.fileid)
                WHERE fullpath LIKE :fullpath AND coverage.buildid=:buildid');
        $file_with_wildcard = "%$file%";
        $stmt->bindParam(':fullpath', $file_with_wildcard);
        $stmt->bindParam(':buildid', $buildid);
        if (!pdo_execute($stmt)) {
            return false;
        }
        $row = $stmt->fetch();
        if (!array_key_exists('id', $row)) {
            return false;
        }
        return $row['id'];
    }

    // Populate $this from existing database results.
    public function Load()
    {
        if (!$this->Id) {
            return false;
        }

        $stmt = $this->PDO->prepare('SELECT * FROM coveragefile WHERE id=:id');
        $stmt->bindParam(':id', $this->Id);
        pdo_execute($stmt);
        $row = $stmt->fetch();
        if (!array_key_exists('id', $row)) {
            return false;
        }

        $this->FullPath = $row['fullpath'];
        $this->Crc32 = $row['crc32'];
        if (config('cdash.use_compression')) {
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
