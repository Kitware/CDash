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
include_once 'models/siteinformation.php';

class Site
{
    public $Id;
    public $Name;
    public $Ip;
    public $Latitude;
    public $Longitude;
    public $OutOfOrder;
    private $PDO;

    public function __construct()
    {
        $this->Ip = '';
        $this->Latitude = '';
        $this->Longitude = '';
        $this->OutOfOrder = 0;
        $this->PDO = get_link_identifier()->getPdo();
    }

    public function SetInformation($information)
    {
        $information->SiteId = $this->Id;
        $information->Save();
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
        if ($this->Name) {
            $stmt = $this->PDO->prepare(
                'SELECT id FROM site WHERE name = ?');
            pdo_execute($stmt, [$this->Name]);
            $id = $stmt->fetchColumn();
            if ($id !== false) {
                $this->Id = $id;
                return true;
            }
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

        if (!pdo_execute($stmt)) {
            return false;
        }
        return true;
    }

    public function LookupIP()
    {
        global $CDASH_REMOTE_ADDR;
        $this->Ip = ($CDASH_REMOTE_ADDR) ? $CDASH_REMOTE_ADDR : $_SERVER['REMOTE_ADDR'];

        // In the async case, look up the IP recorded when the file was
        // originally submitted...
        global $PHP_ERROR_SUBMISSION_ID;
        $submission_id = $PHP_ERROR_SUBMISSION_ID;
        if ($submission_id) {
            $stmt = $this->PDO->prepare(
                'SELECT ip FROM submission2ip WHERE submissionid = ?');
            pdo_execute($stmt, [$submission_id]);
            $this->Ip = $stmt->fetchColumn();
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
        if (!$stmt->execute()) {
            $error = pdo_error();
            // This error might be due to a unique constraint violation.
            // Query for a previously existing site with this name & ip.
            $exists_stmt = $this->PDO->prepare(
                'SELECT id FROM site WHERE name = ? AND ip = ?');
            pdo_execute($exists_stmt, [$this->Name, $this->Ip]);
            $id = $exists_stmt->fetchColumn();
            if ($id !== false) {
                $this->Id = $id;
                return true;
            }
            add_log("SQL error: $error", 'Site Insert', LOG_ERR);
            return false;
        } else {
            $this->Id = pdo_insert_id('site');
        }
        return true;
    }

    // Get the name of the size
    public function GetName()
    {
        if (!$this->Id) {
            echo 'Site::GetName(): Id not set';
            return false;
        }

        $stmt = $this->PDO->prepare(
            'SELECT name FROM site WHERE id = ?');
        if (!pdo_execute($stmt, [$this->Id])) {
            return false;
        }
        return $stmt->fetchColumn();
    }
}
