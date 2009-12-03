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
class ClientToolkitConfigure
{
  var $Id;
  var $Name;
  var $CMakeCache;
  var $Environment;
  var $ToolkitVersionId;
  var $BinaryPath;
  
  // Used internally
  var $OSArray;
  
  function __construct()
    {
    $this->OSArray = array();
    }
    
  /** Add an OS to the toolkit */
  function AddOS($osid)
    {
    $this->OSArray[] = $osid;
    }
 
    
  /** Get Name */
  function GetName()
    {
    if(!$this->Id)
      {
      add_log("clientToolkitConfigure::GetName()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT name FROM client_toolkitconfiguration WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }

   /** Get all by OS */
  function GetIdByOS($osid)
    {
    $ids = array();  
    $query = pdo_query("SELECT toolkitconfigurationid FROM client_toolkitconfiguration2os WHERE osid=".qnum($osid));
    while($query_array = pdo_fetch_array($query))
      {
      $ids[] = $query_array['toolkitconfigurationid'];
      }
    return $ids;    
    }
    
    
  /** Get the binary direcotry */
  function GetBinaryPath()
    {
    if(!$this->Id)
      {
      add_log("clientToolkitConfigure::GetBinaryPath()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT binarypath FROM client_toolkitconfiguration WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }
 
  /** Get the cmake cache */
  function GetCMakeCache()
    {
    if(!$this->Id)
      {
      add_log("clientToolkitConfigure::GetCMakeCache()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT cmakecache FROM client_toolkitconfiguration WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }  
    
  /** Get the environment */
  function GetEnvironment()
    {
   if(!$this->Id)
      {
      add_log("clientToolkitConfigure::GetEnvironment()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT environment FROM client_toolkitconfiguration WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }  

  /** Get the toolkit version id */
  function GetToolkitVersionId()
    {
   if(!$this->Id)
      {
      add_log("clientToolkitConfigure::GetToolkitVersionId()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT toolkitversionid FROM client_toolkitconfiguration WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }  
    
  /** Save */
  function Save()
    {   
    $cmakecache = $this->CMakeCache;
    if(strlen($cmakecache) == 0)
      {
      $cmakecache = 'NULL';
       }
    else
      {
      $cmakecache = "'".$this->CMakeCache."'";
      } 
    
    $environment = $this->Environment;
    if(strlen($environment) == 0)
      {
      $environment = 'NULL';
       }
    else
      {
      $environment = "'".$this->Environment."'";
      } 
       
    // Insert into the siteid  
    $query = pdo_query("SELECT id FROM client_toolkitconfiguration WHERE toolkitversionid=".qnum($this->ToolkitVersionId)." AND name='".$this->Name."'");
    if(pdo_num_rows($query) == 0)
      {
      $sql = "INSERT INTO client_toolkitconfiguration (toolkitversionid,name,cmakecache,environment,binarypath) 
              VALUES (".qnum($this->ToolkitVersionId).",'".$this->Name."',".$cmakecache.",".$environment.",'".$this->BinaryPath."')";

      pdo_query($sql);
      $this->Id = pdo_insert_id('client_toolkitconfiguration');
      add_last_sql_error("clientToolkitConfiguration::Save()");
      }
    else // update
      {
      $query_array = pdo_fetch_array($query);
      $this->Id = $query_array['id'];
      $sql = "UPDATE client_toolkitconfiguration SET cmakecache='".$cmakecache.
             ",environment=".$environment.",binarypath='".$this->BinaryPath."' WHERE id=".qnum($this->Id);
      pdo_query($sql);
      add_last_sql_error("clientToolkitConfiguration::Save()");
      }
    
    // Insert in the toolkit2os
    foreach($this->OSArray as $osid)
      {
      $query = pdo_query("SELECT osid FROM client_toolkitconfiguration2os WHERE toolkitconfigurationid=".qnum($this->Id));
      if(pdo_num_rows($query) == 0)
        {
        $sql = "INSERT INTO client_toolkitconfiguration2os (toolkitconfigurationid,osid) 
                VALUES (".qnum($this->Id).",".qnum($osid).")";
        pdo_query($sql);
        add_last_sql_error("clientToolkitConfiguration::Save os");
        }
      } // end foreach os
    // Delete the OS that are not in the array
    $sql = "DELETE FROM client_toolkitconfiguration2os WHERE toolkitconfigurationid=".qnum($this->Id);
    foreach($this->OSArray as $osid)
      { 
      $sql .= " AND osid!=".qnum($osid);
      }
      
    pdo_query($sql);
    add_last_sql_error("clientToolkitConfiguration::Save OS");
      
    return true;  
    }  

  /** Remove a configuration id */
  function Remove()
    {
    if(!$this->Id)
      {
      add_log("clientToolkitConfigure::Remove()","Id not set");
      return;
      }
    pdo_query("DELETE FROM client_toolkitconfiguration WHERE id=".qnum($this->Id));
    pdo_query("DELETE FROM client_toolkitconfiguration2os WHERE toolkitconfigurationid=".qnum($this->Id));
    add_last_sql_error("clientToolkitConfiguration::Remove");
    }

}    
?>
