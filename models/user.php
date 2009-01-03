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

class UserProject
{
  var $Role;
  var $CvsLogin;
  var $EmailType;
  var $EmailCategory;
  var $UserId;
  var $ProjectId;
  
  function __construct()
    {
    $this->Role = 0;
    $this->EmailType = 1;
    $this->ProjectId = 0;
    $this->UserId = 0;
    $this->EmailCategory=62;
    }
  
  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "ROLE": $this->Role = $value;break;
      case "CVSLOGIN": $this->CvsLogin = $value;break;
      case "EMAILTYPE": $this->EmailType = $value;break;
      case "EMAILCATEGORY": $this->EmailCategory = $value;break;
      }
    }
  
  /** Return if a project exists */
  function Exists()
    {
    // If no id specify return false
    if(!$this->ProjectId || !$this->UserId)
      {
      return false;    
      }
      
    $query = pdo_query("SELECT count(*) FROM user2project WHERE userid='".$this->UserId."' AND projectid='".$this->ProjectId."'");  
    $query_array = pdo_fetch_array($query);
    if($query_array[0]>0)
      {
      return true;
      }
    return false;
    }      
      
  // Save the project in the database
  function Save()
    {
    if(!$this->ProjectId)
      {
      echo "UserProject::Save(): no ProjectId specified";
      return false;    
      }
      
    if(!$this->UserId)
      {
      echo "UserProject::Save(): no UserId specified";
      return false;    
      }
      
    // Check if the project is already
    if($this->Exists())
      {
      // Update the project
      $query = "UPDATE user2project SET";
      $query .= " role='".$this->Role."'";
      $query .= ",cvslogin='".$this->CvsLogin."'";
      $query .= ",emailtype='".$this->EmailType."'";
      $query .= ",emailcategory='".$this->EmailCategory."'";
      $query .= " WHERE userid='".$this->UserId."' AND projectid='".$this->ProjectId."'";
      if(!pdo_query($query))
        {
        add_last_sql_error("User2Project Update");
        return false;
        }
      }
    else // insert
      {    
      $query = "INSERT INTO user2project (userid,projectid,role,cvslogin,emailtype,emailcategory)
                VALUES ($this->UserId,$this->ProjectId,$this->Role,'$this->CvsLogin',$this->EmailType,$this->EmailCategory)";                     
       if(!pdo_query($query))
         {
         add_last_sql_error("User2Project Create");
         echo $query;
         return false;
         }  
       }
    return true;
    } 
  
  /** Get the users of the project */
  function GetUsers()
    {
    if(!$this->ProjectId)
      {
      echo "UserProject GetUsers(): ProjectId not set";
      return false;
      }
  
    $project = pdo_query("SELECT userid FROM user2project WHERE projectid=".qnum($this->ProjectId));
    if(!$project)
      {
      add_last_sql_error("UserProject GetUsers");
      return false;
      }
    
    $userids = array();  
    while($project_array = pdo_fetch_array($project))
      {
      $userids[] = $project_array['userid'];
      }
    return $userids;
    }     
    
    
} // end class UserProject

class User
{
  var $Id;
  var $Email;
  var $Password;  
  var $FirstName;
  var $LastName;
  var $Institution;  
  var $Admin;  
  
  function AddSite($id)
    {
    $this->SiteIds[] = $id;
    }
    
  /** Add a project to the user */
  function AddProject($project)
    {
    $project->UserId = $this->Id;
    $project->Save();
    }

  function SetValue($tag,$value)  
    {
    switch($tag)
      {
      case "EMAIL": $this->Email = $value;break;
      case "PASSWORD": $this->Password = $value;break;
      case "FIRSTNAME": $this->FirstName = $value;break;
      case "LASTNAME": $this->LastName = $value;break;
      case "INSTITUTION": $this->Institution = $value;break;
      case "ADMIN": $this->Admin = $value;break;
      }
    }
  
  /** Return if the user is admin */
  function IsAdmin()
    {
    if(!$this->Id || !is_numeric($this->Id))
      {
      return false;
      }
    $user_array = pdo_fetch_array(pdo_query("SELECT admin FROM ".qid("user")." WHERE id='".$this->Id."'"));
    if($user_array['admin'] == 1)
      {
      return true;
      }
    return false;  
    }
  
  /** Return if a project exists */
  function Exists()
    {
    // If no id specify return false
    if(!$this->Id)
      {
      return false;    
      }
      
    $query = pdo_query("SELECT count(*) FROM user WHERE id='".$this->Id."' OR (firstname='".$this->FirstName."' AND lastname='".$this->LastName."')");  
    $query_array = pdo_fetch_array($query);
    if($query_array[0]>0)
      {
      return true;
      }
    return false;
    }      
      
  // Save the project in the database
  function Save()
    {
    // Check if the project is already
    if($this->Exists())
      {
      // Update the project
      $query = "UPDATE user SET";
      $query .= " email='".$this->Email."'";
      $query .= ",password='".$this->Password."'";
      $query .= ",firstname='".$this->FirstName."'";
      $query .= ",lastname='".$this->LastName."'";
      $query .= ",institution='".$this->Institution."'";
      $query .= ",admin='".$this->Admin."'";
      $query .= " WHERE id='".$this->Id."'";
      if(!pdo_query($query))
        {
        add_last_sql_error("User Update");
        return false;
        }
      }
    else // insert
      {    
      $id = "";
      $idvalue = "";
      if($this->Id)
        {
        $id = "id,";
        $idvalue = "'".$this->Id."',";
        }
        
      $query = "INSERT INTO user (".$id."email,password,firstname,lastname,institution,admin)
                 VALUES (".$idvalue."'$this->Email','$this->Password','$this->FirstName','$this->LastName','$this->Institution','$this->Admin')";                     
       if(!pdo_query($query))
         {
         add_last_sql_error("User Create");
         return false;
         }  
       }
    return true;
    } 
    
  /** Get the name */
  function GetName()
    {
    // If no id specify return false
    if(!$this->Id)
      {
      return false;    
      }
      
    $query = pdo_query("SELECT firstname,lastname FROM user WHERE id=".qnum($this->Id));  
    $query_array = pdo_fetch_array($query);
    
    return $query_array['firstname']." ".$query_array['lastname'];
    }       
}
?>
