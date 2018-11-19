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
use PDO;

class BuildErrorFilter
{
    private $WarningsFilter;
    private $ErrorsFilter;
    public $ProjectId;

    public function __construct($projectid)
    {
        $this->WarningsFilter = null;
        $this->ErrorsFilter = null;
        $this->ProjectId = $projectid;

        $this->PDO = Database::getInstance()->getPdo();
    }

    public function AddOrUpdateFilters($warnings, $errors)
    {
        // Check if it exists
        $build_filters = pdo_query('SELECT projectid FROM build_filters WHERE projectid=' . qnum($this->ProjectId));
        if (pdo_num_rows($build_filters) > 0) {
            return $this->UpdateFilters($warnings, $errors);
        } else {
            return $this->AddFilters($WarningsFilter, $ErrorsFilter);
        }
    }

    public function AddFilters($warnings, $errors)
    {
        $query = 'INSERT INTO build_filters(projectid,warnings,errors)
            VALUES (' . qnum($this->ProjectId) . ",'" . $warnings . "','" . $errors . "')";
        return pdo_query($query);
    }

    public function UpdateFilters($warnings, $errors)
    {
        $query = "UPDATE build_filters SET warnings='" . $warnings . "',errors='" . $errors .
            "' WHERE projectid=" . qnum($this->ProjectId);
        if (!pdo_query($query)) {
            add_last_sql_error('Project Update', $this->ProjectId);
            return false;
        } else {
            return true;
        }
    }

    public function FilterWarning($warning)
    {
        if (is_null($this->WarningsFilter)) {
            $stmt = $this->PDO->prepare(
                'SELECT warnings FROM build_filters WHERE projectid = ?');
            pdo_execute($stmt, [$this->ProjectId]);
            $this->WarningsFilter = $stmt->fetchColumn();
        }
        if ($this->WarningsFilter) {
            foreach (preg_split("/((\r?\n)|(\r\n?))/", $this->WarningsFilter) as $filter) {
                if (preg_match($filter, $warning) === 1) {
                    return true;
                }
            }
        }
        return false;
    }

    public function FilterError($error)
    {
        if (is_null($this->ErrorsFilter)) {
            $stmt = $this->PDO->prepare(
                'SELECT errors FROM build_filters WHERE projectid = ?');
            pdo_execute($stmt, [$this->ProjectId]);
            $this->ErrorsFilter = $stmt->fetchColumn();
        }
        if ($this->ErrorsFilter) {
            foreach (preg_split("/((\r?\n)|(\r\n?))/", $this->ErrorsFilter) as $filter) {
                if (preg_match($filter, $error) === 1) {
                    return true;
                }
            }
        }
        return false;
    }
}
