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
class ClientToolkitVersion
{
  var $Id;
  var $ToolkitId;
  var $Name;
  var $RepositoryURL;
  var $RepositoryType;
  var $RepositoryModule;
  var $Tag;
  var $SourcePath;
  var $CTestProjectName;
  
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

  /** Get name */
  function GetName()
    {
    if(!$this->Id)
      {
      add_log("clientToolkitVersion::GetName()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT name FROM client_toolkitversion WHERE id=".qnum($this->Id));
    add_last_sql_error("clientToolkitVersion::GetName()");
    $row = pdo_fetch_array($sys);
    return $row[0];
    }
      
   /** Get the repository URL */
  function GetRepositoryURL()
    {
    if(!$this->Id)
      {
      add_log("clientToolkitVersion::GetRepositoryURL()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT repositoryurl FROM client_toolkitversion WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }
    
  /** Get the repository Type */
  function GetRepositoryType()
    {
    if(!$this->Id)
      {
      add_log("clientToolkitVersion::GetRepositoryType()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT repositorytype FROM  client_toolkitversion WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }
  
  /** Get the repository module */
  function GetRepositoryModule()
    {
    if(!$this->Id)
      {
      add_log("clientToolkitVersion::GetRepositoryModule()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT repositorymodule FROM client_toolkitversion WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }
    
  /** Get the source direcotry */
  function GetSourcePath()
    {
    if(!$this->Id)
      {
      add_log("clientToolkitVersion::GetRepositoryURL()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT sourcepath FROM client_toolkitversion WHERE id=".qnum($this->Id));
    add_last_sql_error("clientToolkit::GetSourcePath()");
    $row = pdo_fetch_array($sys);
    return $row[0];
    }

  /** Get the ctest clientject name */
  function GetCTestProjectName()
    {
    if(!$this->Id)
      {
      add_log("clientToolkitVersion::GetCTestProjectName()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT ctestclientjectname FROM client_toolkitversion WHERE id=".qnum($this->Id));
    add_last_sql_error("clientToolkit::GetCTestProjectName()");
    $row = pdo_fetch_array($sys);
    return $row[0];
    }
    
   /** Get toolkitid from versionid */
  function GetToolkitId()
    {
    if(!$this->Id)
      {
      add_log("clientToolkitVersion::GetToolkitId()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT toolkitid FROM client_toolkitversion WHERE id=".qnum($this->Id));
    add_last_sql_error("clientToolkit::GetToolkitId()");
    $row = pdo_fetch_array($sys);
    return $row[0];
    }
    
  /** Get all by OS */
  function GetIdByOS($osid)
    {
    $ids = array();  
    $query = pdo_query("SELECT toolkitversionid FROM client_toolkit2os WHERE osid=".qnum($osid));
    while($query_array = pdo_fetch_array($query))
      {
      $ids[] = $query_array['toolkitversionid'];
      }
    return $ids;    
    }
  
  /** Get all the configuration */
  function GetConfigurations()
    {
    $ids = array();  
    $query = pdo_query("SELECT id FROM client_toolkitconfiguration WHERE toolkitversionid=".qnum($this->Id));
    while($query_array = pdo_fetch_array($query))
      {
      $ids[] = $query_array['id'];
      }
    return $ids;    
    } 
    
  /** Save */
  function Save()
    {       
    $tag = $this->Tag;
    if(strlen($tag) == 0)
      {
      $tag = 'NULL';
       }
    else
      {
      $tag = "'".$this->Tag."'";
      } 
    
    $module = $this->RepositoryModule;
    if(strlen($module) == 0)
      {
      $module = 'NULL';
       }
    else
      {
      $module = "'".$this->RepositoryModule."'";
      } 
       
    // Insert into the siteid  
    $query = pdo_query("SELECT id FROM client_toolkitversion WHERE toolkitid=".qnum($this->ToolkitId)." AND name='".$this->Name."'");
    if(pdo_num_rows($query) == 0)
      {
      $sql = "INSERT INTO client_toolkitversion (toolkitid,name,repositoryurl,repositorytype,repositorymodule,tag,
                                              sourcepath,ctestclientjectname) 
              VALUES (".qnum($this->ToolkitId).",'".$this->Name."','".$this->RepositoryURL."',"
              .qnum($this->RepositoryType).",".$module.",".$tag.",'".$this->SourcePath."','".$this->CTestProjectName."')";

      pdo_query($sql);
      $this->Id = pdo_insert_id('client_toolkitversion');
      add_last_sql_error("clientToolkitVersion::Save()");
      }
    else // update
      {
      $query_array = pdo_fetch_array($query);
      $this->Id = $query_array['id'];
      $sql = "UPDATE client_toolkitversion SET repositoryurl='".$this->RepositoryURL.",repositorytype=".qnum($this->RepositoryType)
              .",tag='".$this->Tag.",repositorymodule=".qnum($this->RepositoryModule)
              ."',sourcepath='".$this->SourcePath.",ctestclientjectname='".$this->CTestProjectName."' WHERE id=".qnum($this->Id);
      pdo_query($sql);
      add_last_sql_error("clientToolkitVersion::Save()");
      }
    
    // Insert in the toolkit2os
    foreach($this->OSArray as $osid)
      {
      $query = pdo_query("SELECT osid FROM client_toolkit2os WHERE toolkitversionid=".qnum($this->Id));
      if(pdo_num_rows($query) == 0)
        {
        $sql = "INSERT INTO client_toolkit2os (toolkitversionid,osid) 
                VALUES (".qnum($this->Id).",".qnum($osid).")";
        pdo_query($sql);
        add_last_sql_error("clientToolkitVersion::Save os");
        }
      } // end foreach os
    // Delete the OS that are not in the array
    $sql = "DELETE FROM client_toolkit2os WHERE toolkitversionid=".qnum($this->Id);
    foreach($this->OSArray as $osid)
      { 
      $sql .= " AND osid!=".qnum($osid);
      }
      
    pdo_query($sql);
    add_last_sql_error("clientToolkitVersion::Save os");
      
    return true;  
    }  

  /** Remove a toolkit version */
  function Remove()
    {
    if(!$this->Id)
      {
      add_log("clientToolkitVersion::Remove()","Id not set");
      return;
      }
    
    // Remove all the configuration2os
    $query = pdo_query("SELECT id FROM client_toolkit2configuration WHERE toolkitversionid=".qnum($this->Id));
    if(pdo_num_rows($query)>0)
      {
      while($query_array = pdo_fetch_array($query))
        {
        $configurationid = $query_array['id'];
        pdo_query("DELETE FROM client_toolkitconfiguration2os WHERE toolkitconfigurationid=".qnum($configurationid));
        }
      }
    pdo_query("DELETE FROM client_toolkit2os WHERE toolkitversionid=".qnum($this->Id));
    pdo_query("DELETE FROM client_toolkitversion WHERE id=".qnum($this->Id));
    pdo_query("DELETE FROM client_toolkit2configuration WHERE toolkitversionid=".qnum($this->Id));
    }

}    
?>
