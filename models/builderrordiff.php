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

/** BuildErrorDiff */
class BuildErrorDiff
{
  var $Type;
  var $Difference;
  
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "BUILDERRORDIFF": $this->Difference = $value;break;
      }
    } 
    
  /** Return if exists */
  function Exists()
    {
    $query = pdo_query("SELECT count(*) FROM builderrordiff WHERE buildid='".$this->BuildId."'");  
    $query_array = pdo_fetch_array($query);
    if($query_array['count(*)']>0)
      {
      return true;
      }
    return false;
    }      
      
  // Save in the database
  function Save()
    {
    if(!$this->BuildId)
      {
      echo "BuildErrorDiff::Save(): BuildId not set<br>";
      return false;    
      }

    if($this->Exists())
      {
      // Update
      $query = "UPDATE builderrordiff SET";
      $query .= " type='".$this->Type."'";
      $query .= ",difference='".$this->Difference."'";
      $query .= " WHERE buildid='".$this->BuildId."'";
      if(!pdo_query($query))
        {
        add_last_sql_error("BuildErrorDiff Update");
        return false;
        }
      }
    else // insert
      {    
      $query = "INSERT INTO builderrordiff (buildid,type,difference)
                 VALUES ('$this->BuildId','$this->Type','$this->Difference')";                     
       if(!pdo_query($query))
         {
         add_last_sql_error("BuildErrorDiff Create");
         return false;
         }  
       }
    return true;
    }      
}
?>
