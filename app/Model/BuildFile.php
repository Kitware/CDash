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

/** BuildFile */
class BuildFile
{
    public $Type;
    public $Filename;
    public $md5;
    public $BuildId;

    // Insert in the database (no update possible)
    public function Insert()
    {
        if (!$this->BuildId) {
            echo 'BuildFile::Insert(): BuildId not set<br>';
            return false;
        }

        if (!$this->Type) {
            echo 'BuildFile::Insert(): Type not set<br>';
            return false;
        }

        if (!$this->md5) {
            echo 'BuildFile::Insert(): md5 not set<br>';
            return false;
        }

        if (!$this->Filename) {
            echo 'BuildFile::Insert(): Filename not set<br>';
            return false;
        }

        $filename = pdo_real_escape_string($this->Filename);
        $type = pdo_real_escape_string($this->Type);
        $md5 = pdo_real_escape_string($this->md5);

        // Check if we already have a row
        $query = 'SELECT buildid FROM buildfile WHERE buildid=' . qnum($this->BuildId) . " AND md5='" . $md5 . "'";
        $query_result = pdo_query($query);
        if (!$query_result) {
            add_last_sql_error('BuildFile Insert', 0, $this->BuildId);
            return false;
        }

        if (pdo_num_rows($query_result) > 0) {
            return false;
        }

        $query = 'INSERT INTO buildfile (buildid,type,filename,md5)
              VALUES (' . qnum($this->BuildId) . ",'" . $type . "','" . $filename . "','" . $md5 . "')";
        if (!pdo_query($query)) {
            add_last_sql_error('BuildFile Insert', 0, $this->BuildId);
            return false;
        }
        return true;
    }

    // Returns the buildid associated with this file's MD5 if it has been
    // uploaded previously, false otherwise.
    public function MD5Exists()
    {
        $md5 = pdo_real_escape_string($this->md5);

        $row = pdo_single_row_query(
            "SELECT buildid FROM buildfile WHERE md5='" . $md5 . "'");

        if (empty($row)) {
            return false;
        }
        return $row[0];
    }

    /** Delete this BuildFile */
    public function Delete()
    {
        if (!$this->BuildId || !$this->md5) {
            return false;
        }
        pdo_query("DELETE FROM buildfile WHERE buildid=$this->BuildId AND md5='$this->md5'");
    }
}
