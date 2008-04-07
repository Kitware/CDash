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
@$importUsers = $_POST["importUsers"];
@$registerUsers = $_POST["registerUsers"];

// Register users
if($registerUsers)
{
  $cvslogins = $_POST["cvslogin"];
  $emails = $_POST["email"];
  $firstnames = $_POST["firstname"];
  $lastnames = $_POST["lastname"];
  $cvsuser = $_POST["cvsuser"];
  
  $project = mysql_query("SELECT name FROM project WHERE id='$projectid'");
  $project_array = mysql_fetch_array($project);
  $projectname = $project_array['name'];

  for($i=0;$i<count($cvslogins);$i++)
    {
    if(!isset($cvsuser[$i]))
      {
      continue;
      }

    
    $cvslogin = $cvslogins[$i];
    $email = $emails[$i];
    $firstName = $firstnames[$i];
    $lastName = $lastnames[$i];
    
    // Check if the user is already registered
    $user = mysql_query("SELECT id FROM user WHERE email='$email'");
    echo mysql_error();
        
    if(mysql_num_rows($user)>0)
      {
      // Check if the user has been registered to the project
      $user_array2 = mysql_fetch_array($user);
      $userid = $user_array2["id"];
      $user = mysql_query("SELECT userid FROM user2project WHERE userid='$userid'");
      if(mysql_num_rows($user)==0) // not registered
        {
        // We register the user to the project
        mysql_query("INSERT INTO user2project (userid,projectid,role,cvslogin,emailtype) 
                                      VALUES ('$userid','$projectid','0','$cvslogin','1')");
        echo mysql_error();
        continue;
        }
      echo "User ".$cvslogin." already registered.<br>";
      continue;
      } // already registered
    
    // Check if the cvslogin exists for this project
    $usercvslogin = mysql_query("SELECT userid FROM user2project WHERE cvslogin='$cvslogin'");
    if(mysql_num_rows($usercvslogin)>0)
      {
      echo $cvslogin." was already registered for this project under a different email address<br>";
      continue;
      }
    
    // Register the user
    // Create a new password 
    $keychars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $length = 10;
  
    function make_seed_recoverpass()
      {
      list($usec, $sec) = explode(' ', microtime());
      return (float) $sec + ((float) $usec * 100000);
      }
    srand(make_seed_recoverpass());
      
    $pass = "";
    $max=strlen($keychars)-1;
    for ($i=0;$i<=$length;$i++) 
      {
      $pass .= substr($keychars, rand(0, $max), 1);
      }
    $encrypted = md5($pass);

    mysql_query("INSERT INTO user (email,password,firstname,lastname,institution,admin) 
                 VALUES ('$email','$encrypted','$firstName','$lastName','','0')");
    echo mysql_error();
    $userid = mysql_insert_id();
    
    // Insert the user into the project
    mysql_query("INSERT INTO user2project (userid,projectid,role,cvslogin,emailtype) 
                                  VALUES ('$userid','$projectid','0','$cvslogin','1')");
    echo mysql_error();
    
    $currentPort="";
    if($_SERVER['SERVER_PORT']!=80)
      {
      $currentPort=":".$_SERVER['SERVER_PORT'];
      }
    
    $serverName = $CDASH_SERVER_NAME;
    if(strlen($serverName) == 0)
      {
      $serverName = $_SERVER['SERVER_NAME'];
      }
    
    $currentURI =  "http://".$serverName.$currentPort.$_SERVER['REQUEST_URI']; 
    $currentURI = substr($currentURI,0,strrpos($currentURI,"/"));
    
    $prefix = "";
    if(strlen($firstName)>0)
      {
      $prefix = " ";
      }
    
    // Send the email      
    $text = "Hello".$prefix.$firstName.",<br><br>";
    $text .= "You have been registered to CDash because you have CVS/SVN access to the repository for ".$projectname." <br>";
    $text .= "To access your CDash account: ".$currentURI."/user.php<br>";
    $text .= "Your login is: ".$email."<br>";
    $text .= "Your password is: ".$pass."<br>";
    $text .= "<br>Generated by CDash";
        
    /*if(  @mail("$email", "CDash - ".$projectname." : Subscription","$text","From: $CDASH_EMAILADMIN\nReply-To: no-reply\nX-Mailer: PHP/" . phpversion()."\nMIME-Version: 1.0\nContent-type: text/html; charset=iso-8859-1"))  
      {
      echo "Email sent for: ".$cvslogin."<br>";
      }*/

    //echo $text;
    return;
    } // end loop through users
} // end register users

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

// Import the users from CVS
if($importUsers)
  {
  $contents = file_get_contents($_FILES['cvsUserFile']['tmp_name']);
  if(strlen($contents)>0)
    {  
    $id = 0;
    $pos = 0;
    $pos2 = strpos($contents,"\n");
    while($pos !== false)
      {
      $line = substr($contents,$pos,$pos2-$pos);
      
      $email = "";
      $svnlogin = "";
      $firstname = "";
      $lastname = "";
      
      // first is the svnuser
      $possvn = strpos($line,":");
      if($possvn !== false)
        {
        $svnlogin = trim(substr($line,0,$possvn));
       
        $posemail = strpos($line,":",$possvn+1);
        if($posemail !== false)
          {
          $email = trim(substr($line,$possvn+1,$posemail-$possvn-1));
          
          $name = substr($line,$posemail+1);
          $posname = strpos($name,",");
          if($posname !== false)
            {
            $name = substr($name,0,$posname);
            }
          
          $name = trim($name);
          
          // Find the firstname
          $posfirstname = strrpos($name," ");
          if($posfirstname !== false)
            {
            $firstname = trim(substr($name,0,$posfirstname));
            $lastname = trim(substr($name,$posfirstname));
            }
          else
            {
            $firstname = $name;
            }
          }
        else
          {
          $email = trim(substr($line,$possvn+1));
          }
        }
     
      if(strlen($email)>0 && $email != "kitware@kitware.com")
        {
        $xml .= "<cvsuser>";
        $xml .= "<email>".$email."</email>";
        $xml .= "<cvslogin>".$svnlogin."</cvslogin>";
        $xml .= "<firstname>".$firstname."</firstname>";
        $xml .= "<lastname>".$lastname."</lastname>";
        $xml .= "<id>".$id."</id>";
        $xml .= "</cvsuser>";
        $id++;
        }
     
      $pos = $pos2;
      $pos2 = strpos($contents,"\n",$pos2+1);
      }
    }
  else
    {
    echo "Cannot parse CVS users file";
    }   
} // end import users

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

