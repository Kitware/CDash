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
use Illuminate\Support\Facades\Log;

class UploadFile
{
    public $Id;
    public $Filename;
    public $Filesize;
    public $Sha1Sum;
    public $IsUrl;
    public $BuildId;

    // Insert in the database
    // TODO: (williamjallen) execute all of the queries in this function in one transaction
    public function Insert(): bool
    {
        if (!$this->BuildId) {
            Log::error('BuildId is not set', [
                'function' => __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__,
            ]);
            return false;
        }

        if (!$this->Filename) {
            Log::error('Filename is not set', [
                'function' => __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__,
            ]);
            return false;
        }

        if (!$this->Sha1Sum) {
            Log::error('Sha1Sum is not set', [
                'function' => __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__,
            ]);
            return false;
        }

        if (!$this->Filesize) {
            Log::error('Filesize is not set', [
                'function' => __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__,
            ]);
            return false;
        }

        if (empty($this->IsUrl)) {
            $this->IsUrl = 0;
        }

        if (!$this->IsUrl) {
            $filename = basename($this->Filename);
        } else {
            $filename = $this->Filename;
        }

        $db = Database::getInstance();

        // Check if the file already exists
        $filequery = $db->executePreparedSingleRow('
                         SELECT id
                         FROM uploadfile
                         WHERE
                             sha1sum = ?
                             AND filename = ?
                     ', [$this->Sha1Sum, $filename]);

        if (empty($filequery)) {
            // Insert the file into the database
            $this->Id = DB::table('uploadfile')
                ->insertGetId([
                    'filename' => $filename,
                    'filesize' => intval($this->Filesize),
                    'sha1sum' => $this->Sha1Sum,
                    'isurl' => $this->IsUrl,
                ]);
        } else {
            $this->Id = $filequery['id'];
        }

        if (!$this->Id) {
            Log::error('No Id', [
                'function' => __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__,
            ]);
            return false;
        }
        $this->Id = intval($this->Id);

        $query = $db->executePrepared('
                     INSERT INTO build2uploadfile (fileid, buildid)
                     VALUES (?, ?)
                 ', [$this->Id, $this->BuildId]);

        if ($query === false) {
            add_last_sql_error('UploadFile::Insert', 0, $this->BuildId);
            return false;
        }
        return true;
    }

    public function Fill(): bool
    {
        if (!$this->Id) {
            Log::error('Id not set', [
                'function' => __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__,
            ]);
            return false;
        }

        $db = Database::getInstance();
        $query = $db->executePreparedSingleRow('
                     SELECT filename, filesize, sha1sum, isurl
                     FROM uploadfile
                     WHERE id=?
                 ', [intval($this->Id)]);

        if ($query === false) {
            add_last_sql_error('Uploadfile::Fill', 0, $this->Id);
            return false;
        }
        if (!empty($query)) {
            $this->Sha1Sum = $query['sha1sum'];
            $this->Filename = $query['filename'];
            $this->Filesize = $query['filesize'];
            $this->IsUrl = $query['isurl'];
        } else {
            Log::error('Invalid id', [
                'function' => __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__,
            ]);
            return false;
        }
        return true;
    }
}
