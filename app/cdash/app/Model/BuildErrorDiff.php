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

/** BuildErrorDiff */
class BuildErrorDiff
{
    public $BuildId;
    public $Type;
    public $DifferencePositive;
    public $DifferenceNegative;

    /** Return if exists */
    public function Exists()
    {
        if (!$this->BuildId || !is_numeric($this->BuildId)) {
            echo 'BuildErrorDiff::Save(): BuildId not set<br>';
            return false;
        }

        if (!$this->Type || !is_numeric($this->Type)) {
            echo 'BuildErrorDiff::Save(): Type not set<br>';
            return false;
        }

        $query = pdo_query("SELECT count(*) AS c FROM builderrordiff WHERE buildid='" . $this->BuildId . "' AND type='" . $this->Type . "'");
        $query_array = pdo_fetch_array($query);
        if ($query_array['c'] > 0) {
            return true;
        }
        return false;
    }

    // Save in the database
    public function Save()
    {
        if (!$this->BuildId || !is_numeric($this->BuildId)) {
            echo 'BuildErrorDiff::Save(): BuildId not set<br>';
            return false;
        }

        if (!$this->Type || !is_numeric($this->Type)) {
            echo 'BuildErrorDiff::Save(): Type not set<br>';
            return false;
        }

        if (!$this->DifferencePositive || !is_numeric($this->DifferencePositive)) {
            echo 'BuildErrorDiff::Save(): DifferencePositive not set<br>';
            return false;
        }

        if (!$this->DifferenceNegative || !is_numeric($this->DifferenceNegative)) {
            echo 'BuildErrorDiff::Save(): DifferenceNegative not set<br>';
            return false;
        }
        if ($this->Exists()) {
            // Update
            $query = 'UPDATE builderrordiff SET ';
            $query .= "difference_positive='" . $this->DifferencePositive . "'";
            $query .= ", difference_negative='" . $this->DifferenceNegative . "'";
            $query .= " WHERE buildid='" . $this->BuildId . "' AND type='" . $this->Type . "'";
            if (!pdo_query($query)) {
                add_last_sql_error('BuildErrorDiff Update', 0, $this->BuildId);
                return false;
            }
        } else {
            // insert

            $query = "INSERT INTO builderrordiff (buildid,type,difference_positive,difference_negative)
                 VALUES ('" . $this->BuildId . "','" . $this->Type . "','" . $this->DifferencePositive . "','" .
                $this->DifferenceNegative . "')";
            if (!pdo_query($query)) {
                add_last_sql_error('BuildErrorDiff Create', 0, $this->BuildId);
                return false;
            }
        }
        return true;
    }
}
