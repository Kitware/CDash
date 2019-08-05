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

/** Build Test Diff */
class BuildTestDiff
{
    public $Type;
    public $DifferenceNegative;
    public $DifferencePositive;
    public $BuildId;

    // Insert in the database
    public function Insert()
    {
        if (!$this->BuildId) {
            echo 'BuildTestDiff::Insert(): BuildId is not set<br>';
            return false;
        }

        if ($this->Type != 0 && empty($this->Type)) {
            echo 'BuildTestDiff::Insert(): Type is not set<br>';
            return false;
        }

        if (!is_numeric($this->DifferenceNegative)) {
            echo 'BuildTestDiff::Insert(): DifferenceNegative is not set<br>';
            return false;
        }

        if (!is_numeric($this->DifferencePositive)) {
            echo 'BuildTestDiff::Insert(): DifferencePositive is not set<br>';
            return false;
        }

        $query = "INSERT INTO testdiff (buildid,type,difference_negative,difference_positive)
              VALUES ('$this->BuildId','$this->Type','$this->DifferenceNegative','$this->DifferencePositive')";
        if (!pdo_query($query)) {
            add_last_sql_error('BuildTestDiff Insert', 0, $this->BuildId);
            return false;
        }
        return true;
    }
}
