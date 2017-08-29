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

class Measurement
{
    public $Id;
    public $ProjectId;
    public $Name;
    // Should this measurement be shown on viewTest.php?
    public $TestPage;
    // Should this measurement be shown on testSummary.php?
    public $SummaryPage;
    private $PDO;

    public function __construct()
    {
        $this->ProjectId = 0;
        $this->Name = '';
        $this->TestPage = 0;
        $this->SummaryPage = 0;
        $this->PDO = get_link_identifier()->getPdo();
    }

    // Save this measurement in the database.
    public function Insert()
    {
        if ($this->ProjectId < 1 || !$this->Name) {
            return false;
        }

        $stmt = $this->PDO->prepare(
            'INSERT INTO measurement
            (projectid, name, testpage, summarypage)
            VALUES (:projectid, :name, :testpage, :summarypage)');
        $stmt->bindValue(':projectid', $this->ProjectId);
        $stmt->bindValue(':name', $this->Name);
        $stmt->bindValue(':testpage', $this->TestPage);
        $stmt->bindValue(':summarypage', $this->SummaryPage);
        return pdo_execute($stmt);
    }

    // Update an existing measurement.
    public function Update()
    {
        if ($this->ProjectId < 1 || $this->Id < 1 || !$this->Name) {
            return false;
        }

        $stmt = $this->PDO->prepare(
            'UPDATE measurement SET name = :name, testpage = :testpage, summarypage = :summarypage WHERE id = :id');
        $stmt->bindValue(':name', $this->Name);
        $stmt->bindValue(':testpage', $this->TestPage);
        $stmt->bindValue(':summarypage', $this->SummaryPage);
        $stmt->bindValue(':id', $this->Id);
        return pdo_execute($stmt);
    }

    // Delete an existing measurement.
    public function Delete()
    {
        if ($this->Id < 1) {
            return false;
        }
        $stmt = $this->PDO->prepare('DELETE FROM measurement WHERE id = ?');
        return pdo_execute($stmt, [$this->Id]);
    }

    // Get all measurements for a project.
    public function GetMeasurementsForProject($fetchStyle = PDO::FETCH_ASSOC)
    {
        if ($this->ProjectId < 1) {
            return false;
        }

        $stmt = $this->PDO->prepare(
            'SELECT * FROM measurement WHERE projectid = ? ORDER BY name ASC');
        pdo_execute($stmt, [$this->ProjectId]);

        return $stmt->fetchAll($fetchStyle);
    }

}
