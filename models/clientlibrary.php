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
class ClientLibrary
{
  var $Id;
  var $Name;
  var $Version;
  var $SiteId;
  var $Path;
  var $Include;
  
  /** Get Name */
  function GetName()
    {
    if(!$this->Id)
      {
      add_log("ClientLibrary::GetName()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT name FROM client_library WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }
 
  /** Get Version */
  function GetVersion()
    {
    if(!$this->Id)
      {
      add_log("ClientLibrary::GetVersion()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT version FROM client_library WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row[0];
    }
  
  /** Get all  */  
  function GetAll()
    {
    $ids = array();
    $sql = "SELECT id FROM client_library ORDER BY name";
    $query = pdo_query($sql);
    while($query_array = pdo_fetch_array($query))
      {
      $ids[] = $query_array['id'];
      }
    return $ids;    
    } 
      
  /** Save */
  function Save()
    {    
    // Check if the name/version already exists
    $query = pdo_query("SELECT id FROM client_library WHERE name='".$this->Name."' AND version='".$this->Version."'");
    if(pdo_num_rows($query) == 0)
      {
      $sql = "INSERT INTO client_library (name,version) 
              VALUES ('".$this->Name."','".$this->Version."')";
      pdo_query($sql);
      $this->Id = pdo_insert_id('client_library');
      add_last_sql_error("ClientLibrary::Save()");
      }
    else // update
      {
      $query_array = pdo_fetch_array($query);
      $this->Id = $query_array['id'];
      $sql = "UPDATE client_library SET version='".$this->Version."' WHERE id=".qnum($this->Id);
      pdo_query($sql);
      add_last_sql_error("ClientLibrary::Save()");
      }
      
    // Insert into the siteid  
    $query = pdo_query("SELECT libraryid FROM client_site2library WHERE libraryid=".qnum($this->Id)." AND siteid=".qnum($this->SiteId));
    if(pdo_num_rows($query) == 0)
      {
      $sql = "INSERT INTO client_site2library (siteid,libraryid,path,include) 
              VALUES (".qnum($this->SiteId).",".qnum($this->Id).",'".$this->Path."','".$this->Include."')";
      pdo_query($sql);
      $this->Id = pdo_insert_id('client_site2library');
      add_last_sql_error("ClientLibrary::Save()");
      }
    else // update
      {
      $sql = "UPDATE client_site2library SET path='".$this->Path."',include='".$this->Include."' WHERE libraryid=".qnum($this->Id)." AND siteid=".qnum($this->SiteId);
      pdo_query($sql);
      add_last_sql_error("ClientLibrary::Save()");
      }
    }  

}    
?>
