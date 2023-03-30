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
            add_log('BuildId is not set', __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_ERR);
            return false;
        }

        if (!$this->Filename) {
            add_log('Filename is not set', __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_ERR);
            return false;
        }

        if (!$this->Sha1Sum) {
            add_log('Sha1Sum is not set', __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_ERR);
            return false;
        }

        if (!$this->Filesize) {
            add_log('Filesize is not set', __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_ERR);
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
            $query = $db->executePrepared('
                         INSERT INTO uploadfile (filename, filesize, sha1sum, isurl)
                         VALUES (?, ?, ?, ?)
                     ', [$filename, intval($this->Filesize), $this->Sha1Sum, $this->IsUrl]);

            if ($query === false) {
                add_last_sql_error('Uploadfile::Insert', 0, $this->BuildId);
                return false;
            }
            $this->Id = pdo_insert_id('uploadfile');
        } else {
            $this->Id = $filequery['id'];
        }

        if (!$this->Id) {
            add_log('No Id', __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_ERR);
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
            add_log('Id not set', __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_ERR);
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
            add_log('Invalid id', __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_ERR);
            return false;
        }
        return true;
    }
}
