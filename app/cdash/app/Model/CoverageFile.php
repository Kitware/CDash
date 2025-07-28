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
        $file = $this->File;

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
        $this->File = $coverage_file->file;

        return true;
    }
}
