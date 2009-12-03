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
class ClientToolkit
{
  var $Id;
  var $Name;
  var $ProjectId;
    
  function __construct()
    {
    $this->ProjectId = 0;
    }
    
 
  /** Get All */
  function GetAll()
    {
    $toolkitids = array();
    $query = pdo_query("SELECT id FROM client_toolkit ORDER BY name ASC");
    while($query_array = pdo_fetch_array($query))
      {
      $toolkitids[] = $query_array["id"];
      }
    return $toolkitids;
    }
    
  /** Get Name */
  function GetName()
    {
    if(!$this->Id)
      {
      add_log("clientToolkit::GetName()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT name FROM client_toolkit WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }
 
  /** Get Version */
  function GetVersions()
    {
    if(!$this->Id)
      {
      add_log("clientToolkit::GetVersion()","Id not set");
      return;
      }
    $versions = array();  
    $query = pdo_query("SELECT id FROM client_toolkitversion WHERE toolkitid=".qnum($this->Id));
    if(!$query)
      {
      return $version;
      }
    while($query_array = pdo_fetch_array($query))
      {
      $versions[] = $query_array['id'];
      }
    return $versions;    
    }
  
  /** Get toolkitid from versionid */
  function GetToolkitIdFromVersionId($versionid)
    {
    $ids = array();  
    $query = pdo_query("SELECT toolkitid FROM client_toolkitversion WHERE id=".qnum($versionid));
    $row = pdo_fetch_array($query);
    return $row[0];   
    }
      
  /** Save */
  function Save()
    {   
    // Check if the name already exists
    $query = pdo_query("SELECT id FROM client_toolkit WHERE name='".$this->Name."'");
    if(pdo_num_rows($query) == 0)
      {
      $sql = "INSERT INTO client_toolkit (name,clientjectid) VALUES ('".$this->Name."',".qnum($this->ProjectId).")";
      pdo_query($sql);
      $this->Id = pdo_insert_id('client_toolkit');
      add_last_sql_error("clientToolkit::Save()");
      }
    else // update
      {
      $query_array = pdo_fetch_array($query);
      $this->Id = $query_array['id'];
      add_last_sql_error("clientToolkit::Save()");
      }
    return true;  
    }  

  /** Remove a toolkit */
  function Remove()
    {
    if(!$this->Id)
      {
      add_log("clientToolkit::Remove()","Id not set");
      return;
      }
    
    $versionids = $this->GetVersions();
    
    foreach($versionids as $versionid)
      {
      // Remove all the configuration2os
      $query = pdo_query("SELECT id FROM client_toolkit2configuration WHERE toolkitversionid=".qnum($versionid));
      if(pdo_num_rows($query)>0)
        {
        while($query_array = pdo_fetch_array($query))
          {
          $configurationid = $query_array['id'];
          pdo_query("DELETE FROM client_toolkitconfiguration2os WHERE toolkitconfigurationid=".qnum($configurationid));
          }
        } 
      pdo_query("DELETE FROM client_toolkit2os WHERE toolkitversionid=".qnum($versionid));
      pdo_query("DELETE FROM client_toolkitversion WHERE id=".qnum($versionid));
      pdo_query("DELETE FROM client_toolkit2configuration WHERE toolkitversionid=".qnum($versionid));
      }
      
    pdo_query("DELETE FROM client_toolkit WHERE id=".qnum($this->Id));  
    }

}    
?>
