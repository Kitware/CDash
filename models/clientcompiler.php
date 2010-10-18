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
class ClientCompiler
{
  var $Id;
  var $Name;
  var $Version;
  var $SiteId;
  var $Command;
  var $Generator;
  
  /** Get id from name */
  function GetIdFromName()
    {
    if(!$this->Name)
      {
      add_log("ClientCompiler::GetName()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT name FROM client_compiler WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row['name'];
    }
  
  /** Get name */
  function GetName()
    {
    if(!$this->Id)
      {
      add_log("clientCompiler::GetName()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT name FROM client_compiler WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row['name'];
    }

  /** Get all the compilers */  
  function GetAll()
    {
    $ids = array();
    $sql = "SELECT id FROM client_compiler ORDER BY name";
    $query = pdo_query($sql);
    while($query_array = pdo_fetch_array($query))
      {
      $ids[] = $query_array['id'];
      }
    return $ids;    
    }    
      
    
  /** Get version */
  function GetVersion()
    {
    if(!$this->Id)
      {
      add_log("clientCompiler::GetVersion()","Id not set");
      return;
      }
    $sys = pdo_query("SELECT version FROM client_compiler WHERE id=".qnum($this->Id));
    $row = pdo_fetch_array($sys);
    return $row['version'];
    }
    
  /** Save */
  function Save()
    {
    // Check if the version already exists
    $query = pdo_query("SELECT id FROM client_compiler WHERE name='".$this->Name."' AND version='".$this->Version."'");
    if(pdo_num_rows($query) == 0)
      {
      $sql = "INSERT INTO client_compiler (name,version) 
              VALUES ('".$this->Name."','".$this->Version."')";
      pdo_query($sql);
      $this->Id = pdo_insert_id('client_compiler');
      add_last_sql_error("clientCompiler::Save()");
      }
    else // update
      {
      $query_array = pdo_fetch_array($query);
      $this->Id = $query_array['id'];
      $sql = "UPDATE client_compiler SET name='".$this->Name."',version='".$this->Version."' WHERE id=".qnum($this->Id);
      pdo_query($sql);
      add_last_sql_error("clientCompiler::Save");
      }
      
    // Insert into the siteid  
    $query = pdo_query("SELECT compilerid FROM client_site2compiler WHERE compilerid=".qnum($this->Id)." AND siteid=".qnum($this->SiteId));
    if(pdo_num_rows($query) == 0)
      {
      $sql = "INSERT INTO client_site2compiler (siteid,compilerid,command,generator) 
              VALUES (".qnum($this->SiteId).",".qnum($this->Id).",'".$this->Command."','".$this->Generator."')";
      pdo_query($sql);
      add_last_sql_error("clientCompiler::Save2");
      }
    else // update
      {
      $sql = "UPDATE client_site2compiler SET command='".$this->Command."',generator='".$this->Generator
          ."' WHERE compilerid=".qnum($this->Id)." AND siteid=".qnum($this->SiteId);
      pdo_query($sql);
      add_last_sql_error("clientCompiler::Save3");
      }
    } 

  /** Delete unused compilers */
  function DeleteUnused($compilers)
    {  
    if(!$this->SiteId)
      {
      add_log("clientCompiler::DeleteUnused()","SiteId not set");
      return;
      }

    // Delete the old libraries
    $query = pdo_query("SELECT name,command,version,generator,compilerid FROM client_compiler,client_site2compiler 
              WHERE client_site2compiler.compilerid=client_compiler.id
              AND siteid=".qnum($this->SiteId));
    
    add_last_sql_error("clientCompiler::DeleteUnused()");
    while($query_array = pdo_fetch_array($query))
      {
      $delete = 1;  
      foreach($compilers as $compiler)
        {
        if($compiler['name'] == $query_array['name'] && $compiler['version'] == $query_array['version'] 
           && $compiler['command'] == $query_array['command'] && $compiler['generator'] == $query_array['generator'])
          {
          $delete = 0;
          break;    
          }
        }
      if($delete)  
        {
        pdo_query("DELETE FROM client_site2compiler WHERE compilerid='".$query_array['compilerid'].
              "' AND command='".$query_array['command'].
              "' AND generator='".$query_array['generator'].
              "' AND siteid=".qnum($this->SiteId)); 
        add_last_sql_error("clientCompiler::DeleteUnused()");       
        }   
      } 
    
    // Delete the client_compiler not attached to anything
    pdo_query("DELETE FROM client_compiler WHERE id NOT IN(SELECT compilerid AS id FROM client_site2compiler)");

    }  // end DeleteUnused
}    
?>
