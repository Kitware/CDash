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

class Measurement
{
    public $Id;
    public $ProjectId;
    public $Name;
    public $Position;
    private $PDO;

    public function __construct()
    {
        $this->Id = 0;
        $this->ProjectId = 0;
        $this->Name = '';
        $this->Position = 0;
        $this->PDO = Database::getInstance()->getPdo();
    }

    // Save this measurement to the database.
    public function Save()
    {
        if ($this->ProjectId < 1 || !$this->Name) {
            return false;
        }

        if ($this->Id) {
            // Update an existing record.
            $stmt = $this->PDO->prepare(
                'UPDATE measurement
                SET name = :name, position = :position
                WHERE id = :id');
            $stmt->bindValue(':id', $this->Id);
        } else {
            // Create a new measurement.
            $stmt = $this->PDO->prepare(
                'INSERT INTO measurement
                (projectid, name, position)
                VALUES (:projectid, :name, :position)');
            $stmt->bindValue(':projectid', $this->ProjectId);
        }

        $stmt->bindValue(':name', $this->Name);
        $stmt->bindValue(':position', $this->Position);
        if (!pdo_execute($stmt)) {
            return false;
        }
        if (!$this->Id) {
            $this->Id = pdo_insert_id('measurement');
        }
        return true;
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
