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

        $pdo = get_link_identifier()->getPdo();

        global $CDASH_USE_COMPRESSION;

        $this->FullPath = trim($this->FullPath);

        // Compute the crc32 of the file (before compression for backward compatibility)
        $this->Crc32 = crc32($this->FullPath . $this->File);

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

        $stmt = $pdo->prepare(
                'SELECT id FROM coveragefile WHERE crc32=:crc32');
        $stmt->bindParam(':crc32', $this->Crc32);
        $stmt->execute();
        add_last_sql_error('CoverageFile:Update');
        $existing_file_row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($existing_file_row)) {
            // A file already exists with this crc32.
            // Update this object to use the previously existing result's id.
            $this->Id = $existing_file_row['id'];

            // Update the corresponding coverage row to use this fileid too.
            // First we query for the old fileid used by this coverage entry,
            // just to be sure that we're updating the correct record.
            $stmt = $pdo->prepare(
                    'SELECT c.fileid FROM coverage AS c
                    INNER JOIN coveragefile AS cf ON (cf.id=c.fileid)
                    WHERE c.buildid=:buildid AND cf.fullpath=:fullpath');
            $stmt->bindParam(':buildid', $buildid);
            $stmt->bindParam(':fullpath', $this->FullPath);
            $stmt->execute();
            $old_fileid_row = $stmt->fetch();
            $prevfileid = $old_fileid_row['fileid'];

            $stmt = $pdo->prepare(
                    'UPDATE coverage SET fileid=:fileid
                    WHERE buildid=:buildid AND fileid=:prevfileid');
            $stmt->bindParam(':fileid', $this->Id);
            $stmt->bindParam(':buildid', $buildid);
            $stmt->bindParam(':prevfileid', $prevfileid);
            $stmt->execute();
            add_last_sql_error('CoverageFile:Update');

            // Similarly update any labels if necessary.
            $stmt = $pdo->prepare(
                    'SELECT COUNT(*) AS c FROM label2coveragefile
                    WHERE buildid=:buildid AND coveragefileid=:prevfileid');
            $stmt->bindParam(':buildid', $buildid);
            $stmt->bindParam(':prevfileid', $prevfileid);
            $stmt->execute();
            $count_labels_row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($count_labels_row['c'] > 0) {
                $stmt = $pdo->prepare(
                        'UPDATE label2coveragefile SET coveragefileid=:fileid
                        WHERE buildid=:buildid AND coveragefileid=:prevfileid');
                $stmt->bindParam(':fileid', $this->Id);
                $stmt->bindParam(':buildid', $buildid);
                $stmt->bindParam(':prevfileid', $prevfileid);
                $stmt->execute();
                add_last_sql_error('CoverageFile:Update');
            }

            // Remove the file if the crc32 is NULL
            $stmt = $pdo->prepare(
                    'DELETE FROM coveragefile
                    WHERE id=:prevfileid AND file IS NULL AND crc32 IS NULL');
            $stmt->bindParam(':prevfileid', $prevfileid);
            $stmt->execute();
            add_last_sql_error('CoverageFile:Update');
        } else {
            // The file doesn't exist in the database

            // We find the current fileid based on the name and the file should be null
            $stmt = $pdo->prepare(
                    'SELECT cf.id, cf.file
                    FROM coverage AS c
                    INNER JOIN coveragefile AS cf ON (cf.id=c.fileid)
                    WHERE c.buildid=:buildid AND cf.fullpath=:fullpath
                    ORDER BY cf.id ASC');
            $stmt->bindParam(':buildid', $buildid);
            $stmt->bindParam(':fullpath', $this->FullPath);
            $stmt->execute();
            $coveragefile_row = $stmt->fetch(PDO::FETCH_ASSOC);

            // The GcovTarHandler creates coveragefiles before coverages
            // so we need a simpler query in this case.
            if (is_array($coveragefile_row)) {
                $this->Id = $coveragefile_row['id'];
            } else {
                $stmt = $pdo->prepare(
                        'SELECT id, file FROM coveragefile
                        WHERE fullpath=:fullpath AND file IS NULL
                        ORDER BY id ASC');
                $stmt->bindParam(':fullpath', $this->FullPath);
                $stmt->execute();
                $coveragefile_row = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if (is_array($coveragefile_row)) {
                $this->Id = $coveragefile_row['id'];
            } else {
                // If we still haven't found an existing fileid
                // we insert one here.
                $stmt = $pdo->prepare(
                        'INSERT INTO coveragefile (fullpath)
                        VALUES (:fullpath)');
                $stmt->bindParam(':fullpath', $this->FullPath);
                $stmt->execute();
                $this->Id = pdo_insert_id('coveragefile');
            }

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

        $pdo = get_link_identifier()->getPdo();
        $stmt = $pdo->prepare(
                'SELECT fullpath FROM coveragefile WHERE id=:id');
        $stmt->bindParam(':id', $this->Id);
        if (!$stmt->execute()) {
            add_last_sql_error('Coverage GetPath');
            return false;
        }
        $row = $stmt->fetch();
        return $row['fullpath'];
    }  // GetPath

    /** Return the metric */
    public function GetMetric()
    {
        if (!$this->Id) {
            echo 'CoverageFile GetMetric(): Id not set';
            return false;
        }

        $pdo = get_link_identifier()->getPdo();
        $stmt = $pdo->prepare(
                'SELECT loctested, locuntested, branchstested, branchsuntested,
                functionstested, functionsuntested
                FROM coverage WHERE fileid=:id');
        $stmt->bindParam(':id', $this->Id);
        if (!$stmt->execute()) {
            add_last_sql_error('CoverageFile:GetMetric()');
            return false;
        }

        $row = $stmt->fetch();
        if (!array_key_exists('loctested', $row)) {
            return false;
        }

        $coveragemetric = 1;
        $loctested = $row['loctested'];
        $locuntested = $row['locuntested'];
        $branchstested = $row['branchstested'];
        $branchsuntested = $row['branchsuntested'];
        $functionstested = $row['functionstested'];
        $functionsuntested = $row['functionsuntested'];

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
        $pdo = get_link_identifier()->getPdo();
        $stmt = $pdo->prepare(
                'SELECT id FROM coveragefile
                INNER JOIN coverage ON (coveragefile.id=coverage.fileid)
                WHERE fullpath LIKE :fullpath AND coverage.buildid=:buildid');
        $file_with_wildcard = "%$file%";
        $stmt->bindParam(':fullpath', $file_with_wildcard);
        $stmt->bindParam(':buildid', $buildid);
        if (!$stmt->execute()) {
            add_last_sql_error('CoverageFile:GetIdFromName()');
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
        global $CDASH_USE_COMPRESSION;

        if (!$this->Id) {
            return false;
        }

        $pdo = get_link_identifier()->getPdo();
        $stmt = $pdo->prepare('SELECT * FROM coveragefile WHERE id=:id');
        $stmt->bindParam(':id', $this->Id);
        $stmt->execute();
        $row = $stmt->fetch();
        if (!array_key_exists('id', $row)) {
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
