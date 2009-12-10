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
class ClientCMake
{
  var $Id;  
  var $SiteId;
  var $Version;
  var $Path;
  
  /** Get Version */
  function GetVersion()
    {
    if(!$this->Id)
      {
      add_log("clientCMake::GetVersion()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT version FROM client_cmake WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row['version'];
    }

  /** Get all the cmake */  
  function GetAll()
    {
    $ids = array();
    $sql = "SELECT id FROM client_cmake ORDER BY version";
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
    // Check if the version already exists
    $query = pdo_query("SELECT id FROM client_cmake WHERE version='".$this->Version."'");
    if(pdo_num_rows($query) == 0)
      {
      $sql = "INSERT INTO client_cmake (version) 
              VALUES ('".$this->Version."')";
      pdo_query($sql);
      $this->Id = pdo_insert_id('client_cmake');
      add_last_sql_error("clientCMake::Save()");
      }
    else // update
      {
      $query_array = pdo_fetch_array($query);
      $this->Id = $query_array['id'];
      $sql = "UPDATE client_cmake SET version='".$this->Version."' WHERE id=".qnum($this->Id);
      pdo_query($sql);
      add_last_sql_error("clientCMake::Save()");
      }
      
    // Insert into the siteid  
    $query = pdo_query("SELECT cmakeid FROM client_site2cmake WHERE cmakeid=".qnum($this->Id)." AND siteid=".qnum($this->SiteId));
    if(pdo_num_rows($query) == 0)
      {
      $sql = "INSERT INTO client_site2cmake (siteid,cmakeid,path) 
              VALUES (".qnum($this->SiteId).",".qnum($this->Id).",'".$this->Path."')";
      pdo_query($sql);
      $this->Id = pdo_insert_id('client_site2cmake');
      add_last_sql_error("clientCMake::Save()");
      }
    else // update
      {
      $sql = "UPDATE client_site2cmake SET path='".$this->Path."' WHERE cmakeid=".qnum($this->Id)." AND siteid=".qnum($this->SiteId);
      pdo_query($sql);
      add_last_sql_error("clientCMake::Save()");
      }
    }  
}    
?>
