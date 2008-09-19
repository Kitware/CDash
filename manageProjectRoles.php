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
require_once("pdo.php");
include('login.php');
include_once('common.php');
include('version.php');

if ($session_OK) 
  {
  @$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  pdo_select_db("$CDASH_DB_NAME",$db);

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
   $project = pdo_query("SELECT id FROM project");
   if(pdo_num_rows($project)==1)
    {
    $project_array = pdo_fetch_array($project);
    $projectid = $project_array["id"];
    }
  }
    
  $role = 0;
 
  $user_array = pdo_fetch_array(pdo_query("SELECT admin FROM ".qid("user")." WHERE id='$usersessionid'"));
  if($projectid && is_numeric($projectid))
    {
    $user2project = pdo_query("SELECT role FROM user2project WHERE userid='$usersessionid' AND projectid='$projectid'");
    if(pdo_num_rows($user2project)>0)
      {
      $user2project_array = pdo_fetch_array($user2project);
      $role = $user2project_array["role"];
      }  
    }  

  if(!(isset($_SESSION['cdash']['user_can_create_project']) && 
     $_SESSION['cdash']['user_can_create_project'] == 1)
     && ($user_array["admin"]!=1 && $role<=1))
    {
    echo "You don't have the permissions to access this page";
    return;
    }

  $xml = "<cdash>";
  $xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
  $xml .= "<version>".$CDASH_VERSION."</version>";
  $xml .= "<backurl>user.php</backurl>";
  $xml .= "<title>CDash - Project Roles</title>";
  $xml .= "<menutitle>CDash</menutitle>";
  $xml .= "<menusubtitle>Project Roles</menusubtitle>";


// Form post
@$adduser = $_POST["adduser"];
@$removeuser = $_POST["removeuser"];
@$userid = $_POST["userid"];
@$role = $_POST["role"];
@$cvslogin = $_POST["cvslogin"];
@$updateuser = $_POST["updateuser"];
@$importUsers = $_POST["importUsers"];
@$registerUsers = $_POST["registerUsers"];

@$registerUser = $_POST["registerUser"];

function make_seed_recoverpass()
  {
  list($usec, $sec) = explode(' ', microtime());
  return (float) $sec + ((float) $usec * 100000);
  }
    
// Register a user and send the email 
function register_user($projectid,$email,$firstName,$lastName,$cvslogin)
{
  include("config.php");
  // Check if the user is already registered
  $user = pdo_query("SELECT id FROM ".qid("user")." WHERE email='$email'");
        
  if(pdo_num_rows($user)>0)
    {
    // Check if the user has been registered to the project
    $user_array2 = pdo_fetch_array($user);
    $userid = $user_array2["id"];
    $user = pdo_query("SELECT userid FROM user2project WHERE userid='$userid' AND projectid='$projectid'");
    if(pdo_num_rows($user)==0) // not registered
      {
      // We register the user to the project
      pdo_query("INSERT INTO user2project (userid,projectid,role,cvslogin,emailtype) 
                                  VALUES ('$userid','$projectid','0','$cvslogin','1')");
      echo pdo_error();
      return false;
        }
    return "<error>User ".$email." already registered.</error>";
    } // already registered
    
  // Check if the cvslogin exists for this project
  $usercvslogin = pdo_query("SELECT userid FROM user2project WHERE cvslogin='$cvslogin' AND projectid='$projectid'");
  if(pdo_num_rows($usercvslogin)>0)
    {
    return "<error>".$cvslogin." was already registered for this project under a different email address</error>";
    }
    
  // Register the user
  // Create a new password 
  $keychars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
  $length = 10;
  
  srand(make_seed_recoverpass());
      
  $pass = "";
  $max=strlen($keychars)-1;
  for ($i=0;$i<=$length;$i++) 
    {
    $pass .= substr($keychars, rand(0, $max), 1);
    }
  $encrypted = md5($pass);

  pdo_query("INSERT INTO ".qid("user")." (email,password,firstname,lastname,institution,admin) 
                 VALUES ('$email','$encrypted','$firstName','$lastName','','0')");
  echo pdo_error();
  $userid = pdo_insert_id("user");
    
  // Insert the user into the project
  pdo_query("INSERT INTO user2project (userid,projectid,role,cvslogin,emailtype) 
                                VALUES ('$userid','$projectid','0','$cvslogin','1')");
  echo pdo_error();
    
  $currentPort="";
  $httpprefix="http://";
  if($_SERVER['SERVER_PORT']!=80)
    {
    $currentPort=":".$_SERVER['SERVER_PORT'];
    if($_SERVER['SERVER_PORT']==443)
      {
      $httpprefix = "https://";
      }
    }
    
  $serverName = $CDASH_SERVER_NAME;
  if(strlen($serverName) == 0)
    {
    $serverName = $_SERVER['SERVER_NAME'];
    }
    
  $currentURI =  $httpprefix.$serverName.$currentPort.$_SERVER['REQUEST_URI']; 
  $currentURI = substr($currentURI,0,strrpos($currentURI,"/"));
    
  $prefix = "";
  if(strlen($firstName)>0)
    {
    $prefix = " ";
    }
     
  $project = pdo_query("SELECT name FROM project WHERE id='$projectid'");
  $project_array = pdo_fetch_array($project);
  $projectname = $project_array['name'];

  // Send the email      
  $text = "Hello".$prefix.$firstName.",<br><br>";
  $text .= "You have been registered to CDash because you have CVS/SVN access to the repository for ".$projectname." <br>";
  $text .= "To access your CDash account: ".$currentURI."/user.php<br>";
  $text .= "Your login is: ".$email."<br>";
  $text .= "Your password is: ".$pass."<br>";
  $text .= "<br>Generated by CDash.";
      
  if(  @mail("$email", "CDash - ".$projectname." : Subscription","$text","From: $CDASH_EMAILADMIN\nReply-To: no-reply\nX-Mailer: PHP/" . phpversion()."\nMIME-Version: 1.0\nContent-type: text/html; charset=iso-8859-1"))  
    {
    echo "Email sent to: ".$cvslogin."<br>";
    }
  return true;
}


if(isset($_POST["sendEmailToSiteMaintainers"]))
  {
  $emailMaintainers = $_POST["emailMaintainers"];
  if(strlen($emailMaintainers)<50)
    {
    $xml .= "<error>The email should be more than 50 characters.</error>";
    }
  else
    {
    $maintainerids = find_site_maintainers($projectid);
    
    $email = "";
    foreach($maintainerids as $maintainerid)
      {
      $user2 = pdo_query("SELECT email FROM ".qid("user")." WHERE id='$maintainerid'");
      $user_array2 = pdo_fetch_array($user2);
      if(strlen($email)>0)
        {
        $email .= ", ";
        }
      $email .= $user_array2['email'];
      }
  
    $projectname = get_project_name($projectid);    
    if($email != "")
      {
      if(  @mail("$email", "CDash - ".$projectname." : To Site Maintainers","$emailMaintainers","From: $CDASH_EMAILADMIN\nReply-To: no-reply\nX-Mailer: PHP/" . phpversion()."\nMIME-Version: 1.0\nContent-type: text/html; charset=iso-8859-1"))  
        {
        $xml .= "<warning>Email sent to site maintainers.</warning>";
        }
      else
        {
        $xml .= "<error>Cannot send email to site maintainers.</error>";
        }  
      }
    else
      {
      $xml .= "<error>There is no site maintainers for this project.</error>";
      }  
    }
  }

// Register a user
if($registerUser)
{
  @$email = $_POST["registeruseremail"];
  @$firstName = $_POST["registeruserfirstname"];
  @$lastName = $_POST["registeruserlastname"];
  @$cvslogin = $_POST["registerusercvslogin"];
  
  if(strlen($email)<3 || strlen($firstName)<2 || strlen($lastName)<2 ||  strlen($cvslogin)<2)
    {
    $xml .= "<error>Empty fields</error>";
    }
  else
    {
    // Call the register_user function
    $xml .= register_user($projectid,$email,$firstName,$lastName,$cvslogin);
    }
} // end register user


// Register CVS users
if($registerUsers)
{
  $cvslogins = $_POST["cvslogin"];
  $emails = $_POST["email"];
  $firstnames = $_POST["firstname"];
  $lastnames = $_POST["lastname"];
  $cvsuser = $_POST["cvsuser"];
  
  for($logini=0;$logini<count($cvslogins);$logini++)
    {
    if(!isset($cvsuser[$logini]))
      {
      continue;
      }
    
    $cvslogin = $cvslogins[$logini];
    $email = $emails[$logini];
    $firstName = $firstnames[$logini];
    $lastName = $lastnames[$logini];
    
    // Call the register_user function
    $xml .= register_user($projectid,$email,$firstName,$lastName,$cvslogin);
    
    } // end loop through users
} // end register users

// Add a user
if($adduser)
{
  $user2project = pdo_query("SELECT userid FROM user2project WHERE userid='$userid' AND projectid='$projectid'");
    
  if(pdo_num_rows($user2project) == 0)
    {
    pdo_query("INSERT INTO user2project (userid,role,cvslogin,projectid) VALUES ('$userid','$role','$cvslogin','$projectid')");
    }
}

// Remove the user
if($removeuser)
  {
  pdo_query("DELETE FROM user2project WHERE userid='$userid' AND projectid='$projectid'");
  echo pdo_error();
  }

// Update the user
if($updateuser)
  {
  pdo_query("UPDATE user2project SET role='$role',cvslogin='$cvslogin' WHERE userid='$userid' AND projectid='$projectid'");
  echo pdo_error();
  }

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
$projects = pdo_query($sql);
while($project_array = pdo_fetch_array($projects))
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
  
$project = pdo_query("SELECT id,name FROM project WHERE id='$projectid'");
$project_array = pdo_fetch_array($project);
$xml .= "<project>";
$xml .= add_XML_value("id",$project_array['id']);
$xml .= add_XML_value("name",$project_array['name']);
$xml .= "</project>";

// List the users for that project
$user = pdo_query("SELECT u.id,u.firstname,u.lastname,u.email,up.cvslogin,up.role
                     FROM user2project AS up, ".qid("user")." as u  
                     WHERE u.id=up.userid  AND up.projectid='$projectid' 
                     ORDER BY u.firstname ASC");
                         
$i=0;                         
while($user_array = pdo_fetch_array($user))
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

if(isset($CDASH_FULL_EMAIL_WHEN_ADDING_USER) && $CDASH_FULL_EMAIL_WHEN_ADDING_USER==1)
  {
  $xml .= add_XML_value("fullemail","1");
  }
$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"manageProjectRoles");
  } // end session
?>

