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

    public function __construct()
    {
        $this->OutOfOrder = 0;
    }

    public function SetInformation($information)
    {
        $information->SiteId = $this->Id;
        $information->Save();
    }

    /** Check if the site already exists */
    public function Exists()
    {
        // If no id specify return false
        if (!$this->Id && !$this->Name) {
            return false;
        }

        if ($this->Id) {
            $query = pdo_query('SELECT count(*) AS c FROM site WHERE id=' . qnum($this->Id));
            $query_array = pdo_fetch_array($query);
            if ($query_array['c'] > 0) {
                return true;
            }
        }
        if ($this->Name) {
            $query = pdo_query("SELECT id FROM site WHERE name='" . $this->Name . "'");
            if (pdo_num_rows($query) > 0) {
                $query_array = pdo_fetch_array($query);
                $this->Id = $query_array['id'];
                return true;
            }
        }
        return false;
    }

    /** Update a site */
    public function Update()
    {
        if (!$this->Exists()) {
            return;
        }

        // Update the project
        $query = 'UPDATE site SET';
        $query .= " name='" . $this->Name . "'";
        $query .= ",ip='" . $this->Ip . "'";
        $query .= ",latitude='" . $this->Latitude . "'";
        $query .= ",longitude='" . $this->Longitude . "'";
        $query .= ",outoforder='" . $this->OutOfOrder . "'";

        $query .= " WHERE id='" . $this->Id . "'";

        if (!pdo_query($query)) {
            add_last_sql_error('Site Update');
            return false;
        }
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
            $this->Ip = pdo_get_field_value(
                'SELECT ip FROM submission2ip WHERE submissionid=' . qnum($submission_id),
                'ip', ''
            );
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

        $query =
            "INSERT INTO site (name,ip,latitude,longitude)
            VALUES
            ('$this->Name','$this->Ip','$this->Latitude','$this->Longitude')";
        if (!pdo_query($query)) {
            $error = pdo_error();
            // This error might be due to a unique constraint violation.
            // Query for a previously existing site with this name & ip.
            $existing_id_result = pdo_single_row_query(
                "SELECT id FROM site WHERE name='$this->Name'
                    AND ip='$this->Ip'");
            if ($existing_id_result &&
                array_key_exists('id', $existing_id_result)
            ) {
                $this->Id = $existing_id_result['id'];
                return true;
            }
            add_log("SQL error: $error", 'Site Insert', LOG_ERR);
            return false;
        } else {
            $this->Id = pdo_insert_id('site');
        }
    }

    // Get the name of the size
    public function GetName()
    {
        if (!$this->Id) {
            echo 'Site::GetName(): Id not set';
            return false;
        }

        $query = pdo_query('SELECT name FROM site WHERE id=' . qnum($this->Id));
        if (!$query) {
            add_last_sql_error('Site GetName');
            return false;
        }

        $site_array = pdo_fetch_array($query);
        return $site_array['name'];
    }
}
