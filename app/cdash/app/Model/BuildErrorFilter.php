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
use CDash\Model\Project;

class BuildErrorFilter
{
    private $ErrorsFilter;
    private $WarningsFilter;

    public $Project;

    public function __construct(Project $project)
    {
        $this->ErrorsFilter = null;
        $this->WarningsFilter = null;

        $this->PDO = Database::getInstance();

        $this->Project = $project;
        $this->Fill();
    }

    public function Exists()
    {
        $stmt = $this->PDO->prepare(
            'SELECT projectid FROM build_filters
                 WHERE projectid = :projectid');
        $this->PDO->execute($stmt, [':projectid' => $this->Project->Id]);
        if ($stmt->fetchColumn()) {
            return true;
        }
        return false;
    }

    public function AddOrUpdateFilters($warnings, $errors)
    {
        if ($this->Exists()) {
            $stmt = $this->PDO->prepare(
                'UPDATE build_filters
                SET warnings = :warnings, errors = :errors
                WHERE projectid = :projectid');
        } else {
            $stmt = $this->PDO->prepare(
                'INSERT INTO build_filters(projectid, warnings, errors)
                    VALUES (:projectid, :warnings, :errors)');
        }

        $query_params = [
            ':errors' => $errors,
            ':projectid' => $this->Project->Id,
            ':warnings' => $warnings
        ];
        if ($this->PDO->execute($stmt, $query_params)) {
            $this->Fill();
            return true;
        }
        return false;
    }

    public function FilterWarning($warning)
    {
        return $this->FilterText($warning, $this->WarningsFilter);
    }

    public function FilterError($error)
    {
        return $this->FilterText($error, $this->ErrorsFilter);
    }

    public function GetErrorsFilter()
    {
        return $this->ErrorsFilter;
    }

    public function SetErrorsFilter($filter)
    {
        $this->ErrorsFilter = $filter;
    }

    public function GetWarningsFilter()
    {
        return $this->WarningsFilter;
    }

    public function SetWarningsFilter($filter)
    {
        $this->WarningsFilter = $filter;
    }

    private function Fill()
    {
        $stmt = $this->PDO->prepare(
            'SELECT * FROM build_filters WHERE projectid = :projectid');
        $this->PDO->execute($stmt, [':projectid' => $this->Project->Id]);
        $row = $stmt->fetch();
        if ($row) {
            $this->ErrorsFilter = $row['errors'];
            $this->WarningsFilter = $row['warnings'];
        }
    }

    private function FilterText($subject, $filterString)
    {
        if ($filterString) {
            foreach (preg_split("/\R/", $filterString) as $filter) {
                if (strpos($subject, $filter) !== false) {
                    return true;
                }
            }
        }
        return false;
    }
}
