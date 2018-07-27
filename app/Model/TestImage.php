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

/** Test Image
 *  Actually stores just the image id. The image is supposed to be already in the image table */
class TestImage
{
    public $Id;
    public $Role;
    public $TestId;

    /** Return if exists */
    public function Exists()
    {
        $query = pdo_query("SELECT count(*) AS c FROM test2image WHERE imgid='" . $this->Id . "' AND testid='" . $this->TestId . "' AND role='" . $this->Role . "'");
        $query_array = pdo_fetch_array($query);
        if ($query_array['c'] > 0) {
            return true;
        }
        return false;
    }

    // Save in the database
    public function Insert()
    {
        $role = pdo_real_escape_string($this->Role);

        $query = "INSERT INTO test2image (imgid,testid,role)
              VALUES ('$this->Id','$this->TestId','$role')";
        if (!pdo_query($query)) {
            add_last_sql_error('TestImage Insert');
            return false;
        }
        return true;
    }
}
