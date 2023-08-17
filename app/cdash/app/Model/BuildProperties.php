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

/** BuildProperties class */
class BuildProperties
{
    public $Build;
    public $Properties;
    private $Filled;
    private $PDO;

    public function __construct(Build $build)
    {
        $this->Build = $build;
        $this->Properties = [];
        $this->Filled = false;
        $this->PDO = Database::getInstance();
    }

    /** Return true if this build already has properties. */
    public function Exists()
    {
        if (!$this->Build) {
            return false;
        }
        $stmt = $this->PDO->prepare(
            'SELECT COUNT(*) FROM buildproperties WHERE buildid = :buildid');
        $this->PDO->execute($stmt, [':buildid' => $this->Build->Id]);
        if ($stmt->fetchColumn() > 0) {
            return true;
        }
        return false;
    }

    /** Save these build properties to the database,
        overwriting any existing content. */
    public function Save()
    {
        $required_params = ['Build', 'Properties'];
        foreach ($required_params as $param) {
            if (!$this->$param) {
                add_log("$param not set", 'BuildProperties::Save', LOG_ERR);
                return false;
            }
        }

        // Delete any previously existing properties for this build.
        if ($this->Exists()) {
            $this->Delete();
        }

        $properties_str = json_encode($this->Properties);
        if ($properties_str === false) {
            add_log('Failed to encode JSON: ' . json_last_error_msg(),
                'BuildProperties::Save', LOG_ERR);
            return false;
        }

        $stmt = $this->PDO->prepare(
            'INSERT INTO buildproperties (buildid, properties)
            VALUES (:buildid, :properties)');
        $query_params = [
            ':buildid' => $this->Build->Id,
            ':properties' => $properties_str,
        ];
        return $this->PDO->execute($stmt, $query_params);
    }

    /** Delete this record from the database. */
    public function Delete()
    {
        if (!$this->Build) {
            add_log('Build not set', 'BuildProperties::Delete', LOG_ERR);
            return false;
        }
        if (!$this->Exists()) {
            add_log('No properties exist for this build',
                'BuildProperties::Delete', LOG_ERR);
            return false;
        }

        $stmt = $this->PDO->prepare(
            'DELETE FROM buildproperties WHERE buildid = :buildid');
        return $this->PDO->execute($stmt, [':buildid' => $this->Build->Id]);
    }

    /** Retrieve properties for a given build. */
    public function Fill()
    {
        if (!$this->Build) {
            add_log('Build not set', 'BuildProperties::Fill', LOG_ERR);
            return false;
        }

        $stmt = $this->PDO->prepare(
            'SELECT properties FROM buildproperties
             WHERE buildid = :buildid');
        if (!$this->PDO->execute($stmt, [':buildid' => $this->Build->Id])) {
            return false;
        }

        $row = $stmt->fetch();
        if (!is_array($row)) {
            return true;
        }

        $properties = json_decode($row['properties'], true);
        if (is_array($properties)) {
            $this->Properties = $properties;
        }
        $this->Filled = true;
        return true;
    }
}
