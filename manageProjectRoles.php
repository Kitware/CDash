<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $RCSfile: common.php,v $
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
include("config.php");
include('login.php');
include_once('common.php');
include('version.php');

if ($session_OK) 
  {
  @$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  mysql_select_db("$CDASH_DB_NAME",$db);

  $usersessionid = $_SESSION['cdash']['loginid'];
  // Checks
  if(!isset($usersessionid) || !is_numeric($usersessionid))
    {
    echo "Not a valid usersessionid!";
    return;
    }
    
  @$projectid = $_GET["projectid"];
  
  // If the projectid is not set and there is only one project we go directly to the page
  if(!isset($projectid))
  {
   $project = mysql_query("SELECT id FROM project");
   if(mysql_num_rows($project)==1)
    {
    $project_array = mysql_fetch_array($project);
    $projectid = $project_array["id"];
    }
  }
    
  $role = 0;
 
  $user_array = mysql_fetch_array(mysql_query("SELECT admin FROM user WHERE id='$usersessionid'"));
  if($projectid && is_numeric($projectid))
    {
    $user2project = mysql_query("SELECT role FROM user2project WHERE userid='$usersessionid' AND projectid='$projectid'");
    if(mysql_num_rows($user2project)>0)
      {
      $user2project_array = mysql_fetch_array($user2project);
      $role = $user2project_array["role"];
      }  
    }  

  if($user_array["admin"]!=1 && $role<=1)
    {
    echo "You don't have the permissions to access this page";
    return;
    }

// Form post
@$adduser = $_POST["adduser"];
@$removeuser = $_POST["removeuser"];
@$userid = $_POST["userid"];
@$role = $_POST["role"];
@$cvslogin = $_POST["cvslogin"];
@$updateuser = $_POST["updateuser"];

// Add a user
if($adduser)
{
  $user2project = mysql_query("SELECT userid FROM user2project WHERE userid='$userid' AND projectid='$projectid'");
    
  if(mysql_num_rows($user2project) == 0)
    {
    mysql_query("INSERT INTO user2project (userid,role,cvslogin,projectid) VALUES ('$userid','$role','$cvslogin','$projectid')");
    }
}

// Remove the user
if($removeuser)
{
  mysql_query("DELETE FROM user2project WHERE userid='$userid' AND projectid='$projectid'");
  echo mysql_error();
}

// Update the user
if($updateuser)
{
  mysql_query("UPDATE user2project SET role='$role',cvslogin='$cvslogin' WHERE userid='$userid' AND projectid='$projectid'");
  echo mysql_error();
}


  $xml = "<cdash>";
  $xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
  $xml .= "<version>".$CDASH_VERSION."</version>";
  $xml .= "<backurl>user.php</backurl>";
  $xml .= "<title>CDash - Project Roles</title>";
  $xml .= "<menutitle>CDash</menutitle>";
  $xml .= "<menusubtitle>Project Roles</menusubtitle>";



$sql = "SELECT id,name FROM project";
if($user_array["admin"] != 1)
  {
  $sql .= " WHERE id IN (SELECT projectid AS id FROM user2project WHERE userid='$usersessionid' AND role>0)"; 
  }
$projects = mysql_query($sql);
while($project_array = mysql_fetch_array($projects))
   {
   $xml .= "<availableproject>";
   $xml .= add_XML_value("id",$project_array['id']);
   $xml .= add_XML_value("name",$project_array['name']);
   if($project_array['id']==$projectid)
      {
      $xml .= add_XML_value("selected","1");
      }
   $xml .= "</availableproject>";
   }
  
// If we have a project id
if($projectid>0)
  {
  
$project = mysql_query("SELECT id,name FROM project WHERE id='$projectid'");
$project_array = mysql_fetch_array($project);
$xml .= "<project>";
$xml .= add_XML_value("id",$project_array['id']);
$xml .= add_XML_value("name",$project_array['name']);
$xml .= "</project>";

// List the users for that project
$user = mysql_query("SELECT u.id,u.firstname,u.lastname,u.email,up.cvslogin,up.role
                     FROM user2project AS up, user as u  
                     WHERE u.id=up.userid  AND up.projectid='$projectid' 
                     ORDER BY u.firstname ASC");
                         
$i=0;                         
while($user_array = mysql_fetch_array($user))
  {
  $userid = $user_array["id"];
  $xml .= "<user>";
 
  if($i%2==0)
    {
    $xml .= add_XML_value("bgcolor","#CADBD9");
    }
  else
    {
    $xml .= add_XML_value("bgcolor","#FFFFFF");
    }
  $i++;
  $xml .= add_XML_value("id",$userid);
  $xml .= add_XML_value("firstname",$user_array['firstname']);
  $xml .= add_XML_value("lastname",$user_array['lastname']);
  $xml .= add_XML_value("email",$user_array['email']);   
  $xml .= add_XML_value("cvslogin",$user_array['cvslogin']); 
  $xml .= add_XML_value("role",$user_array['role']);  
  $xml .= "</user>";
  }

} // end project=0

$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"manageProjectRoles");
  } // end session
?>

