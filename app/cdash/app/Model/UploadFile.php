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

class UploadFile
{
    public $Id;
    public $Filename;
    public $Filesize;
    public $Sha1Sum;
    public $IsUrl;
    public $BuildId;

    // Insert in the database
    public function Insert()
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
            $filename = pdo_real_escape_string(basename($this->Filename));
        } else {
            $filename = pdo_real_escape_string($this->Filename);
        }

        // Check if the file already exists
        $filequery = pdo_query("SELECT id FROM uploadfile WHERE sha1sum = '" . $this->Sha1Sum . "' AND filename ='$filename'");
        if (pdo_num_rows($filequery) == 0) {
            // Insert the file into the database
            $query = "INSERT INTO uploadfile (filename, filesize, sha1sum, isurl) VALUES ('$filename','$this->Filesize','$this->Sha1Sum', '$this->IsUrl')";
            if (!pdo_query($query)) {
                add_last_sql_error('Uploadfile::Insert', 0, $this->BuildId);
                return false;
            }
            $this->Id = pdo_insert_id('uploadfile');
        } else {
            $filequery_array = pdo_fetch_array($filequery);
            $this->Id = $filequery_array['id'];
        }

        if (!$this->Id) {
            add_log('No Id', __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_ERR);
            return false;
        }

        if (!pdo_query("INSERT INTO build2uploadfile (fileid, buildid)
                   VALUES ('$this->Id','$this->BuildId')")
        ) {
            add_last_sql_error('UploadFile::Insert', 0, $this->BuildId);
            return false;
        }
        return true;
    }

    public function Fill()
    {
        if (!$this->Id) {
            add_log('Id not set', __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_ERR);
            return false;
        }
        $query = pdo_query("SELECT filename, filesize, sha1sum, isurl FROM uploadfile WHERE id='$this->Id'");
        if (!$query) {
            add_last_sql_error('Uploadfile::Fill', 0, $this->Id);
            return false;
        }
        if (pdo_num_rows($query) > 0) {
            $fileArray = pdo_fetch_array($query);
            $this->Sha1Sum = $fileArray['sha1sum'];
            $this->Filename = $fileArray['filename'];
            $this->Filesize = $fileArray['filesize'];
            $this->IsUrl = $fileArray['isurl'];
        } else {
            add_log('Invalid id', __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__, LOG_ERR);
            return false;
        }
        return true;
    }
}
