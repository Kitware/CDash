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

use CDash\Config;
use CDash\Database;

class Site
{
    public $Id;
    public $Name;
    public $Ip;
    public $Latitude;
    public $Longitude;
    public $OutOfOrder;
    private $Filled;
    private $Information;
    private $PDO;

    public function __construct()
    {
        $this->Ip = '';
        $this->Latitude = '';
        $this->Longitude = '';
        $this->OutOfOrder = 0;
        $this->Filled = false;
        $this->PDO = Database::getInstance()->getPdo();
    }

    /**
     * @param SiteInformation $information
     */
    public function SetInformation(SiteInformation $information)
    {
        $information->SiteId = $this->Id;
        $information->Save();
        $this->Information = $information;
    }

    /**
     * @return SiteInformation
     */
    public function GetInformation()
    {
        return $this->Information;
    }

    /** Check if the site already exists */
    public function Exists()
    {
        // If no id or name were specified return false.
        if (!$this->Id && !$this->Name) {
            return false;
        }

        if ($this->Id) {
            $stmt = $this->PDO->prepare(
                'SELECT COUNT(*) AS c FROM site WHERE id = ?');
            pdo_execute($stmt, [$this->Id]);
            if ($stmt->fetchColumn() > 0) {
                return true;
            }
        }
        $stmt = $this->PDO->prepare(
            'SELECT id FROM site WHERE name = :name');
        pdo_execute($stmt, [':name' => $this->Name]);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            $this->Id = $id;
            return true;
        }
        return false;
    }

    /** Update a site */
    public function Update()
    {
        if (!$this->Exists()) {
            return false;
        }

        // Update the site.
        $stmt = $this->PDO->prepare(
            'UPDATE site
             SET name = :name, ip = :ip, latitude = :latitude,
                 longitude = :longitude, outoforder = :outoforder
            WHERE id= :id');
        $stmt->bindParam(':name', $this->Name);
        $stmt->bindParam(':ip', $this->Ip);
        $stmt->bindParam(':latitude', $this->Latitude);
        $stmt->bindParam(':longitude', $this->Longitude);
        $stmt->bindParam(':outoforder', $this->OutOfOrder);
        $stmt->bindParam(':id', $this->Id);

        try {
            if ($stmt->execute()) {
                return true;
            }
            // The UPDATE statement didn't execute cleanly.
            $error_info = $stmt->errorInfo();
            $error = $error_info[2];
            throw new \Exception($error);
        } catch (\Exception $e) {
            // This error might be due to a unique key violation.
            // Check for an existing site with this name.
            $site = new Site();
            $site->Name = $this->Name;
            if ($site->Exists()) {
                $this->Id = $site->Id;
                return true;
            }

            // Otherwise log the error and return false.
            add_log($e->getMessage() . PHP_EOL . $e->getTraceAsString(),
                'Site::Update', LOG_ERR);
            return false;
        }
    }

    public function LookupIP()
    {
        global $PHP_ERROR_SUBMISSION_ID;
        $submission_id = $PHP_ERROR_SUBMISSION_ID;

        $config = Config::getInstance();
        // In the async case, look up the IP recorded when the file was
        // originally submitted...
        if ($submission_id) {
            $stmt = $this->PDO->prepare(
                'SELECT ip FROM submission2ip WHERE submissionid = ?');
            pdo_execute($stmt, [$submission_id]);
            $this->Ip = $stmt->fetchColumn();
        } elseif ($config->get('CDASH_REMOTE_ADDR')) {
            $this->Ip = $config->get('CDASH_REMOTE_ADDR');
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $this->Ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $this->Ip = '';
        }
    }

    /** Insert a new site */
    public function Insert()
    {
        // Don't attempt to save a Site that doesn't have a name.
        if (!$this->Name) {
            return false;
        }

        $justSetIP = false;

        if (strlen($this->Ip) == 0) {
            $this->LookupIP();
            $justSetIP = true;
        }

        if ($this->Exists()) {
            if ($justSetIP) {
                $this->Update();
            }
            return $this->Id;
        }

        // Get the geolocation
        if (strlen($this->Latitude) == 0) {
            $location = get_geolocation($this->Ip);
            $this->Latitude = $location['latitude'];
            $this->Longitude = $location['longitude'];
        }

        $stmt = $this->PDO->prepare(
            'INSERT INTO site (name, ip, latitude, longitude)
            VALUES (:name, :ip, :latitude, :longitude)');
        $stmt->bindParam(':name', $this->Name);
        $stmt->bindParam(':ip', $this->Ip);
        $stmt->bindParam(':latitude', $this->Latitude);
        $stmt->bindParam(':longitude', $this->Longitude);
        try {
            if ($stmt->execute()) {
                $this->Id = pdo_insert_id('site');
                return true;
            }
            // The INSERT statement didn't execute cleanly.
            $error_info = $stmt->errorInfo();
            $error = $error_info[2];
            throw new \Exception($error);
        } catch (\Exception $e) {
            // This error might be due to a unique constraint violation.
            // Query for a previously existing site with this name.
            $site = new Site();
            $site->Name = $this->Name;
            if ($site->Exists()) {
                $this->Id = $site->Id;
                return true;
            }
            // Otherwise log the error and return false.
            add_log($e->getMessage() . PHP_EOL . $e->getTraceAsString(),
                'Site::Update', LOG_ERR);
            return false;
        }
    }

    // Get the name of this site.
    public function GetName()
    {
        if (!$this->Fill()) {
            return false;
        }
        return $this->Name;
    }

    public function Fill()
    {
        if ($this->Filled) {
            return true;
        }
        if (!$this->Id) {
            add_log('Id not set', 'Site::Fill', LOG_ERR);
            return false;
        }
        $stmt = $this->PDO->prepare(
            'SELECT * FROM site WHERE id = ?');
        if (!pdo_execute($stmt, [$this->Id])) {
            return false;
        }
        $row = $stmt->fetch();
        $this->Name = $row['name'];
        $this->Ip = $row['ip'];
        $this->Latitude = $row['latitude'];
        $this->Longitude = $row['longitude'];
        $this->OutOfOrder = $row['outoforder'];
        $this->Filled = true;
        return true;
    }
}
