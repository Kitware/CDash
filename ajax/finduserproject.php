<html>
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
include("../config.php");
include("../common.php");

$projectid = $_GET["projectid"];
$search = $_GET["search"];

$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);

$user = mysql_query("SELECT id,email,firstname,lastname FROM user WHERE 
                    (email LIKE '%$search%' OR firstname LIKE '%$search%' OR lastname LIKE '%$search%')
                    AND id NOT IN (SELECT userid as id FROM user2project WHERE projectid='$projectid')");
echo mysql_error();

?>
   
  <table width="100%"  border="0">
  <?php
  if(mysql_num_rows($user)==0)
    {
    echo "<tr><td>[none]</tr></td>";
    }
  while($user_array = mysql_fetch_array($user))
  {
  ?>
  <tr>
  <td width="20%" bgcolor="#EEEEEE"><font size="2"><?php echo $user_array["firstname"]." ".$user_array["lastname"]." (".$user_array["email"].")"; ?></font></td>
  <td bgcolor="#EEEEEE"><font size="2"><form method="post" action="" name="formuser_<?php echo $user_array["id"]?>">
  <input name="userid" type="hidden" value="<?php echo $user_array["id"]?>">
  role: <select name="role">
    <option value="0">Normal User</option>
    <option value="1">Dashboard Submitter</option>
    <option value="2">Project administrator</option>
  </select>
  cvslogin: <input name="cvslogin" type="text" size="20"/>
  <input name="adduser" type="submit" value="add user">
  </form></font></td>
  </tr>

  <?php
  }
  ?>

</table>
  
</html>

