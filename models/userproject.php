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
  var $EmailMissingSites; // send email when a site is missing for the project (expected builds)
  var $EmailSuccess; // email when my checkin are fixing something
  var $UserId;
  var $ProjectId;
  
  function __construct()
    {
    $this->Role = 0;
    $this->EmailType = 1;
    $this->ProjectId = 0;
    $this->UserId = 0;
    $this->EmailCategory=62;
    $this->EmailMissingSites=0;
    $this->EmailSuccess=0;
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
      $query .= ",emailsuccess='".$this->EmailSuccess."'";
      $query .= ",emailmissingsites='".$this->EmailMissingSites."'";
      $query .= " WHERE userid='".$this->UserId."' AND projectid='".$this->ProjectId."'";
      if(!pdo_query($query))
        {
        add_last_sql_error("User2Project Update");
        return false;
        }
      }
    else // insert
      {    
      $query = "INSERT INTO user2project (userid,projectid,role,cvslogin,emailtype,emailcategory,
                                          emailsuccess,emailmissingsites)
                VALUES ($this->UserId,$this->ProjectId,$this->Role,'$this->CvsLogin',
                        $this->EmailType,$this->EmailCategory,$this->EmailSuccess,$this->EmailMissingSites)";                     
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
  function GetUsers($role=-1)
    {
    if(!$this->ProjectId)
      {
      echo "UserProject GetUsers(): ProjectId not set";
      return false;
      }
  
    $sql = "";
    if($role != -1)
      {
      $sql = " AND role=".$role;
      }
  
    $project = pdo_query("SELECT userid FROM user2project WHERE projectid=".qnum($this->ProjectId).$sql);
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
   
  /** Fill in the information given a projectid and a CVS login. 
   *  This function expects the emailtype>0 */
  function FillFromCVSLogin()
    {
    if(!$this->ProjectId)
      {
      add_log('ProjectId not set',"UserProject FillFromCVSLogin()",LOG_ERR,
              $this->ProjectId,0,CDASH_OBJECT_USER,$this->UserId);
      return false;
      }
     
    if(!$this->CvsLogin)
      {
      add_log("CvsLogin not set","UserProject FillFromCVSLogin()",LOG_ERR,
              $this->ProjectId,0,CDASH_OBJECT_USER,$this->UserId);
      return false;
      }
     
    $sql = "SELECT emailcategory,userid,emailsuccess
               FROM user2project WHERE user2project.projectid=".qnum($this->ProjectId)."
               AND user2project.cvslogin='".$this->CvsLogin."'
               AND user2project.emailtype>0";
               
    $user = pdo_query($sql);
    if(!$user)
      {
      add_last_sql_error("UserProject FillFromCVSLogin");
      return false;
      }

    if(pdo_num_rows($user) == 0)
      {
      return false;
      }   
    $user_array = pdo_fetch_array($user);
    $this->EmailCategory = $user_array['emailcategory'];
    $this->UserId = $user_array['userid'];
    $this->EmailSuccess = $user_array['emailsuccess'];
    return true;
    }
      
  /** Get the email category from the user id */
  function GetEmailCategory()
    {  
    if(!$this->UserId)
      {
      echo "UserProject GetEmailCategory(): UserId not set";
      return false;
      }
      
    if(!$this->ProjectId)
      {
      echo "UserProject GetEmailCategory(): ProjectId not set";
      return false;
      }
        
    $category = pdo_query("SELECT emailcategory FROM user2project WHERE 
                          userid=".qnum($this->UserId)." AND projectid=".qnum($this->ProjectId));
    if(!$category)
      {
      add_last_sql_error("UserProject GetEmailCategory");
      return false;
      } 
    $category_array = pdo_fetch_array($category);
    return $category_array['emailcategory'];    
    }

  /** Get the projects associated with the user */  
  function GetProjects()
    {
    if(!$this->UserId)
      {
      echo "UserProject GetProjects(): UserId not set";
      return false;
      }

    $project = pdo_query("SELECT projectid FROM user2project WHERE userid=".qnum($this->UserId));
    if(!$project)
      {
      add_last_sql_error("UserProject GetProjects");
      return false;
      }
    
    $projectids = array();  
    while($project_array = pdo_fetch_array($project))
      {
      $projectids[] = $project_array['projectid'];
      }
    return $projectids;
    }
} // end class UserProject
?>
