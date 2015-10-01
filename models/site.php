<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
// It is assumed that appropriate headers should be included before including this file
include_once('models/siteinformation.php');

class site
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
          $query = pdo_query("SELECT count(*) AS c FROM site WHERE id=".qnum($this->Id));
          $query_array = pdo_fetch_array($query);
          if ($query_array['c']>0) {
              return true;
          }
      }
      if ($this->Name) {
          $query = pdo_query("SELECT id FROM site WHERE name='".$this->Name."'");
          if (pdo_num_rows($query)>0) {
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
    $query = "UPDATE site SET";
      $query .= " name='".$this->Name."'";
      $query .= ",ip='".$this->Ip."'";
      $query .= ",latitude='".$this->Latitude."'";
      $query .= ",longitude='".$this->Longitude."'";
      $query .= ",outoforder='".$this->OutOfOrder."'";

      $query .= " WHERE id='".$this->Id."'";

      if (!pdo_query($query)) {
          add_last_sql_error("Site Update");
          return false;
      }
  }

    public function LookupIP()
    {
        $this->Ip = $_SERVER['REMOTE_ADDR'];

    // In the async case, look up the IP recorded when the file was
    // originally submitted...
    global $PHP_ERROR_SUBMISSION_ID;
        $submission_id = $PHP_ERROR_SUBMISSION_ID;
        if ($submission_id) {
            $this->Ip = pdo_get_field_value(
        "SELECT ip FROM submission2ip WHERE submissionid=".qnum($submission_id),
        'ip', ''
        );
        }
    }

  /** Insert a new site */
  public function Insert()
  {
      $justSetIP = false;

      if (strlen($this->Ip)==0) {
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
    if (strlen($this->Latitude)==0) {
        $location = get_geolocation($this->Ip);
        $this->Latitude = $location['latitude'];
        $this->Longitude = $location['longitude'];
    }

      if (pdo_query("INSERT INTO site (name,ip,latitude,longitude)
                  VALUES ('$this->Name','$this->Ip','$this->Latitude','$this->Longitude')")) {
          $this->Id = pdo_insert_id("site");
      } else {
          add_last_sql_error("Site Insert");
          return false;
      }
  } // end function save

  // Get the name of the size
  public function GetName()
  {
      if (!$this->Id) {
          echo "Site::GetName(): Id not set";
          return false;
      }

      $query = pdo_query("SELECT name FROM site WHERE id=".qnum($this->Id));
      if (!$query) {
          add_last_sql_error("Site GetName");
          return false;
      }

      $site_array = pdo_fetch_array($query);
      return $site_array['name'];
  } // end GetName()
}
