<html>
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

// To be able to access files in this CDash installation regardless
// of getcwd() value:
//
$cdashpath = str_replace('\\', '/', dirname(dirname(__FILE__)));
set_include_path($cdashpath . PATH_SEPARATOR . get_include_path());

require_once("cdash/config.php");
require_once("cdash/pdo.php");
require_once("cdash/common.php");

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

$search = pdo_real_escape_string($_GET["search"]);

if(isset($CDASH_FULL_EMAIL_WHEN_ADDING_USER) && $CDASH_FULL_EMAIL_WHEN_ADDING_USER==1)
  {
  $sql = "email='$search'";
  }
else
  {
  $sql = "(email LIKE '%$search%' OR firstname LIKE '%$search%' OR lastname LIKE '%$search%')";
  }
$user = pdo_query("SELECT id,email,firstname,lastname,admin FROM ".qid("user")." WHERE ".$sql);
echo pdo_error();

?>
   
  <table width="100%"  border="0">
  <?php
  if(pdo_num_rows($user)==0)
    {
    echo "<tr><td>[none]</tr></td>";
    }
  while($user_array = pdo_fetch_array($user))
  {
  ?>
  <tr>
  <td width="20%" bgcolor="#EEEEEE"><font size="2"><?php echo $user_array["firstname"]." ".$user_array["lastname"]." (".$user_array["email"].")"; ?></font></td>
  <td bgcolor="#EEEEEE"><font size="2">
  <form method="post" action="" name="formuser_<?php echo $user_array["id"]?>">
  <input name="userid" type="hidden" value="<?php echo $user_array["id"]?>">
  <?php 
  if($user_array["admin"])
    {
    echo "Administrator";
    if($user_array["id"]>1)
      {
      echo " <input name=\"makenormaluser\" type=\"submit\" value=\"make normal user\">";
      }
    }
  else
    {
    echo "Normal User";
    echo " <input name=\"makeadmin\"  type=\"submit\" value=\"make admin\">";
    }   
  ?>
   <?php 
  if($user_array["id"]>1)
    {
    ?>
    <input name="removeuser" type="submit" onclick="return confirmRemove()" value="remove user">
  <?php } ?>
  <input name="search" type="hidden" value='<?php echo $search ?>'>
  </form></font></td>
  </tr>

  <?php
  }
  ?>

</table>
  
</html>

