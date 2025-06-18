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
use App\Models\CoverageFile as EloquentCoverageFile;
use CDash\Database;
use PDO;

/** This class shouldn't be used externally */
class CoverageFile
{
    public $Id;
    public $File;
    public $FullPath;
    public $Crc32;

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

        $existing_file_row = EloquentCoverageFile::firstWhere('crc32', $this->Crc32);
        if ($existing_file_row !== null) {
            // A file already exists with this crc32.
            // Update this object to use the previously existing result's id.
            $this->Id = $existing_file_row->id;

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

                Coverage::where([
                    'buildid' => $buildid,
                    'fileid' => $prevfileid,
                ])->update([
                    'fileid' => $this->Id,
                ]);

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
                EloquentCoverageFile::where([
                    'id' => $prevfileid,
                    'file' => null,
                    'crc32' => null,
                ])->delete();
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
                $coveragefile_row = EloquentCoverageFile::firstWhere([
                    'fullpath' => $this->FullPath,
                    'file' => null,
                ]);

                if ($coveragefile_row !== null) {
                    $this->Id = $coveragefile_row->id;
                } else {
                    // If we still haven't found an existing fileid
                    // we insert one here.
                    $this->Id = EloquentCoverageFile::create([
                        'fullpath' => $this->FullPath,
                        'crc32' => 0,
                    ])->id;
                }
            }

            EloquentCoverageFile::findOrFail((int) $this->Id)->update([
                'file' => $file,
                'crc32' => $this->Crc32,
            ]);
        }
        return true;
    }

    // Populate $this from existing database results.
    public function Load()
    {
        if (!$this->Id) {
            return false;
        }

        $coverage_file = EloquentCoverageFile::find((int) $this->Id);
        if ($coverage_file === null) {
            return false;
        }

        $this->FullPath = $coverage_file->fullpath;
        $this->Crc32 = $coverage_file->crc32;
        if (config('cdash.use_compression')) {
            if (is_resource($coverage_file->file)) {
                $this->File = base64_decode(stream_get_contents($coverage_file->file));
            } else {
                $this->File = base64_decode($coverage_file->file);
            }

            @$uncompressedrow = gzuncompress($this->File);
            if ($uncompressedrow !== false) {
                $this->File = $uncompressedrow;
            }
        } else {
            // TODO: This branch of the conditional is possibly faulty.  Postgres should always return a resource here.
            $this->File = $coverage_file->file;
        }

        return true;
    }

    // Remove the extra <br> tag that is added to the end of the file
    // by some of our XML handlers.
    public function TrimLastNewline()
    {
        // Remove trailing <br> tag.
        if (substr($this->File, -4) === '<br>') {
            $this->File = substr($this->File, 0, strlen($this->File) - 4);
        }
    }
}
