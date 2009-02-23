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
class LabelEmail
{
  var $UserId;
  var $ProjectId;
  var $LabelId;
  
  function __construct()
    {
    $this->ProjectId = 0;
    $this->UserId = 0;
    $this->LabelId = 0;
    }
  
  /** Return if a project exists */
  function Exists()
    {
    // If no id specify return false
    if(!$this->ProjectId || !$this->UserId)
      {
      return false;    
      }
      
    $query = pdo_query("SELECT count(*) FROM labelemail WHERE userid=".qnum($this->UserId).
                        " AND projectid=".qnum($this->ProjectId).
                        " AND labelid=".qnum($this->LabelId));  
    $query_array = pdo_fetch_array($query);
    if($query_array[0]>0)
      {
      return true;
      }
    return false;
    }      
  
  
  /** Remove */
  function Remove()
    {
   if(!$this->ProjectId)
      {
      echo "LabelEmail Remove(): ProjectId not set";
      return false;
      } 
      
    if(!$this->UserId)
      {
      echo "LabelEmail Remove(): UserId not set";
      return false;
      }
      
    if(!$this->LabelId)
      {
      echo "LabelEmail Remove(): LabelId not set";
      return false;
      } 
      
    $query = pdo_query("DELETE FROM labelemail WHERE userid=".qnum($this->UserId).
                        " AND projectid=".qnum($this->ProjectId).
                        " AND labelid=".qnum($this->LabelId));  
 
    if(!$query)
      {
      return false;
      }
    return true;
    }      
    
  function Insert()
    {
    if(!$this->ProjectId)
      {
      echo "LabelEmail Insert(): ProjectId not set";
      return false;
      } 
      
    if(!$this->UserId)
      {
      echo "LabelEmail Insert(): UserId not set";
      return false;
      }
      
    if(!$this->LabelId)
      {
      echo "LabelEmail Insert(): LabelId not set";
      return false;
      } 
  
    if(!$this->Exists())
      {
      $query = pdo_query("INSERT INTO labelemail (userid,projectid,labelid) VALUES(".qnum($this->UserId).
                          ",".qnum($this->ProjectId).
                          ",".qnum($this->LabelId).")");  
      if(!$query)
        {
        return false;
        }
      }
      
    return true;
    } // end insert() function
  
   
  /** Get the labels given a projectidd and userid */
  function GetLabels()
    {
    if(!$this->ProjectId)
      {
      echo "LabelEmail GetLabels(): ProjectId not set";
      return false;
      } 
      
    if(!$this->UserId)
      {
      echo "LabelEmail GetLabels(): UserId not set";
      return false;
      } 
    
    $labels = pdo_query("SELECT labelid FROM labelemail WHERE projectid=".qnum($this->ProjectId)." AND userid=".qnum($this->UserId));
    if(!$labels)
      {
      add_last_sql_error("LabelEmail GetLabels");
      return false;
      }
    
    $labelids = array();
    while($labels_array = pdo_fetch_array($labels))
      {
      $labelids[] = $labels_array['labelid'];
      }
      
    return $labelids;  
    }
      
} // end class LabelEmail
?>
