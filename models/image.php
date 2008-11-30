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
class Image
{
  var $Id;
  var $Filename;
  var $Extension;
  var $Checksum;
  var $Data; // In the file refered by Filename  

  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "FILENAME": $this->Filename = $value;break;
      case "EXTENSION": $this->Extension = $value;break;
      case "CHECKSUM": $this->Checksum = $value;break;
      }
    }
    
  private function GetData()
    {
    if(strlen($this->Filename)>0)
      {
      $h = fopen($this->Filename,"rb");
      $this->Data = addslashes(fread($h,filesize($this->Filename)));
      fclose($h);
      }
    }  

  /** Check if exists */  
  function Exists()
    {
    // If no id specify return false
    if(!$this->Id)
      {
      return false;    
      }
    
    $query = pdo_query("SELECT count(*) FROM image WHERE id='".$this->Id."'");
    $query_array = pdo_fetch_array($query);
    if($query_array['count(*)']==0)
      {
      return false;
      }
    
    return true;  
    }
    
  /** Save the group */
  function Save()
    {
    // Get the data from the file if necessary
    $this->GetData();
      
    if($this->Exists())
      {
      $query = "UPDATE image SET";
      $query .= " img='".$this->Data."'";
      $query .= ",extension='".$this->Extension."'";
      $query .= ",checksum='".$this->Checksum."'";
      $query .= " WHERE id='".$this->Id."'";
      
      if(!pdo_query($query))
        {
        add_last_sql_error("Image Update");
        return false;
        }
      }
    else
      {
      $id = "";
      $idvalue = "";
      if($this->Id)
        {
        $id = "id,";
        $idvalue = "'".$this->Id."',";
        }
                                            
      if(pdo_query("INSERT INTO image (".$id."img,extension,checksum)
                     VALUES (".$idvalue."'$this->Data','$this->Extension','$this->Checksum')"))
         {
         $this->Id = pdo_insert_id("image");
         }
       else
         {
         add_last_sql_error("Image Insert");
         return false;
         }
      }
  } // end function save
      
}

?>
