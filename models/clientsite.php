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
class ClientSite
{
  var $Id;
  var $Name;
  var $OsId;
  
  var $SystemName;
  var $Host;
  var $BaseDirectory;
  
  /** get name*/
  function GetName()
    {
    if(!$this->Id)
      {
      add_log("ClientSite::GetName()","Id not set");
      return;
      }
    $name = pdo_query("SELECT name FROM client_site WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($name);
    return $row[0];
    }
  
  /** get name*/
  function GetSystemName()
    {
    if(!$this->Id)
      {
      add_log("ProSite::Name()","Id not set");
      return;
      }
    $name = pdo_query("SELECT systemname FROM client_site WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($name);
    return $row[0];
    }
    
  /** get the OS */
  function GetOS()
    {
    if(!$this->Id)
      {
      add_log("ProSite::GetOS()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT osid FROM client_site WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }
    
  /** get host*/
  function GetHost()
    {
    if(!$this->Id)
      {
      add_log("ProSite::GetName()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT host FROM client_site WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row['host'];
    }
    
  /** get base directory */
  function GetBaseDirectory()
    {
    if(!$this->Id)
      {
      add_log("ProSite::GetBaseDirectory()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT basedirectory FROM client_site WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    
    // If we have a source we update and build
    $baseDir =  $row[0];
    if($baseDir[strlen($baseDir)-1] == '/')
      {
      $baseDir = substr($baseDir,0,strlen($baseDir)-1);
      }
    return $baseDir;
    }
    
  /** Return a list of compiler ids */
  function GetCompilerIds()
    {
    if(!$this->Id)
      {
      add_log("ProSite::GetCompilerIds()","Id not set");
      return;
      }
      
    $ids = array();  
    $query = pdo_query("SELECT compilerid FROM client_site2compiler WHERE siteid=".qnum($this->Id));
    while($query_array = pdo_fetch_array($query))
      {
      $ids[] = $query_array['compilerid'];
      }
    return $ids;  
    }
  
  /** get name*/
  function GetCompilerGenerator($compilerid)
    {
    if(!$this->Id)
      {
      add_log("ProSite::GetCompilerGenerator()","Id not set");
      return;
      }
    $name = pdo_query("SELECT generator FROM client_site2compiler WHERE siteid=".qnum($this->Id)." AND compilerid='".$compilerid."'");
    $row = pdo_fetch_array($name);
    return $row[0];
    }
    
  /** Return the CMake path */
  function GetCMakePath($cmakeid)
    {
    if(!$this->Id)
      {
      add_log("ProSite::GetCMakePath()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT path FROM client_site2cmake WHERE siteid=".qnum($this->Id)." AND cmakeid=".qnum($cmakeid));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }
  
  /** Get Library Source */
  function GetLibrarySource($libraryid)
    {
    if(!$this->Id)
      {
      add_log("clientSite::GetLibrarySource()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT source FROM client_site2library WHERE siteid=".qnum($this->Id)." AND libraryid=".qnum($libraryid));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }
  
  /** Get Library Repository */
  function GetLibraryRepository($libraryid)
    {
    if(!$this->Id)
      {
      add_log("clientSite::GetLibraryRepository()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT repository FROM client_site2library WHERE siteid=".qnum($this->Id)." AND libraryid=".qnum($libraryid));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }
  
  /** Get Library Path */
  function GetLibraryPath($libraryid)
    {
    if(!$this->Id)
      {
      add_log("clientLibrary::GetLibraryPath()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT path FROM client_site2library WHERE siteid=".qnum($this->Id)." AND libraryid=".qnum($libraryid));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }

  /** Get Library Module */
  function GetLibraryModule($libraryid)
    {
    if(!$this->Id)
      {
      add_log("clientSite::GetLibraryModule()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT module FROM client_site2library WHERE siteid=".qnum($this->Id)." AND libraryid=".qnum($libraryid));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }
    
      
  /** Return a list of cmake ids */
  function GetCMakeIds()
    {
    if(!$this->Id)
      {
      add_log("ProSite::GetCMakeIds()","Id not set");
      return;
      }
      
    $ids = array();  
    $query = pdo_query("SELECT cmakeid FROM client_site2cmake WHERE siteid=".qnum($this->Id));
    while($query_array = pdo_fetch_array($query))
      {
      $ids[] = $query_array['cmakeid'];
      }
    return $ids;  
    }
  
  /** Return a list of cmake ids */
  function GetLibraryIds()
    {
    if(!$this->Id)
      {
      add_log("ProSite::GetLibraryIds()","Id not set");
      return;
      }
      
    $ids = array();  
    $query = pdo_query("SELECT libraryid FROM client_site2library WHERE siteid=".qnum($this->Id));
    while($query_array = pdo_fetch_array($query))
      {
      $ids[] = $query_array['libraryid'];
      }
    return $ids;  
    }
  
  /** Get the id of a site from the sitename and systemname */  
  function GetId($sitename,$systemname)
    {
     $query = pdo_query("SELECT id FROM client_site WHERE name='".$sitename."' AND systemname='".$systemname."'");
    if(!$query)
      {
      add_last_sql_error("clientSite::GetId()");
      return 0;
      }
    
    if(pdo_num_rows($query) == 0)
      {
      return 0;
      }
      
    $row = pdo_fetch_array($query);
    return $row['0'];
    }
  
    
  /** Save a site */  
  function Save()
    {
    // Check if the name or system already exists
    $query = pdo_query("SELECT id FROM client_site WHERE name='".$this->Name."' AND systemname='".$this->SystemName."'");
    if(pdo_num_rows($query) == 0)
      {
      $sql = "INSERT INTO client_site (name,osid,systemname,host,basedirectory) 
              VALUES ('".$this->Name."','".$this->OsId."','".$this->SystemName."','".$this->Host."','".$this->BaseDirectory."')";
      pdo_query($sql);
      $this->Id = pdo_insert_id('client_site');
      add_last_sql_error("clientSite::Save()");
      }
    else // update
      {
      $query_array = pdo_fetch_array($query);
      $this->Id = $query_array['id'];
      $sql = "UPDATE client_site SET osid='".$this->OsId."',host='".$this->Host."',basedirectory='".$this->BaseDirectory."' WHERE id=".qnum($this->Id);
      pdo_query($sql);
      add_last_sql_error("clientSite::Save()");
      }
    }   // end Save
      
  /** Get all the site */  
  function GetAll()
    {
    $ids = array();
    $sql = "SELECT id FROM client_site";
    $query = pdo_query($sql);
    while($query_array = pdo_fetch_array($query))
      {
      $ids[] = $query_array['id'];
      }
    return $ids;    
    } 
    
  /** Return all the sites that match this os */
  function GetAllByOS($osid)
    {
    $ids = array();
    $sql = "SELECT id FROM client_site WHERE osid=".qnum($osid);
    $query = pdo_query($sql);
    while($query_array = pdo_fetch_array($query))
      {
      $ids[] = $query_array['id'];
      }
    return $ids;  
    }   
}
?>
