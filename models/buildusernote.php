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

// It is assumed that appropriate headers should be included before including this file
class BuildUserNote
{
    public $UserId;
    public $Note;
    public $TimeStamp;
    public $Status;
    public $BuildId;

    // Insert in the database
    public function Insert()
    {
        if (!$this->BuildId) {
            echo 'BuildUserNote::Insert(): BuildId is not set<br>';
            return false;
        }

        if (!$this->UserId) {
            echo 'BuildUserNote::Insert(): UserId is not set<br>';
            return false;
        }

        if (!$this->Note) {
            echo 'BuildUserNote::Insert(): Note is not set<br>';
            return false;
        }

        if (!$this->TimeStamp) {
            echo 'BuildUserNote::Insert(): TimeStamp is not set<br>';
            return false;
        }

        if (!$this->Status) {
            echo 'BuildUserNote::Insert(): Status is not set<br>';
            return false;
        }

        $query = "INSERT INTO buildnote (buildid,userid,note,timestamp,status)
              VALUES ('$this->BuildId','$this->UserId','$this->Note','$this->TimeStamp','$this->Status')";
        if (!pdo_query($query)) {
            add_last_sql_error('BuildUserNote Insert', 0, $this->BuildId);
            return false;
        }
        return true;
    }
}
