<?php
/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once 'include/pdo.php';
include 'public/login.php';
require_once 'include/common.php';

if (!$session_OK) {
    echo 'Not a valid session';
    return;
}

$userid = $_SESSION['cdash']['loginid'];
// Checks
if (!isset($userid) || !is_numeric($userid)) {
    echo 'Not a valid user';
    return;
}

$user_array = pdo_fetch_array(pdo_query('SELECT admin FROM ' . qid('user') . " WHERE id='$userid'"));

if ($user_array['admin'] != 1) {
    echo "You don't have permission to access this page";
    return;
}

$search = pdo_real_escape_string($_GET['search']);
$config = \CDash\Config::getInstance();
if ($config->get('CDASH_FULL_EMAIL_WHEN_ADDING_USER') == 1) {
    $sql = "email='$search'";
} else {
    $sql = "(email LIKE '%$search%' OR firstname LIKE '%$search%' OR lastname LIKE '%$search%')";
}
$user = pdo_query('SELECT id,email,firstname,lastname,admin FROM ' . qid('user') . ' WHERE ' . $sql);
echo pdo_error();

?>

<table width="100%" border="0">
    <?php
    if (pdo_num_rows($user) == 0) {
        echo '<tr><td>[none]</tr></td>';
    }
    while ($user_array = pdo_fetch_array($user)) {
        ?>
        <tr>
            <td width="20%" bgcolor="#EEEEEE"><font
                    size="2"><?php echo $user_array['firstname'] . ' ' . $user_array['lastname'] . ' (' . $user_array['email'] . ')'; ?></font></td>
            <td bgcolor="#EEEEEE"><font size="2">
                    <form method="post" action="" name="formuser_<?php echo $user_array['id'] ?>">
                        <input name="userid" type="hidden" value="<?php echo $user_array['id'] ?>">
                        <?php
                        if ($user_array['admin']) {
                            echo 'Administrator';
                            if ($user_array['id'] > 1) {
                                echo ' <input name="makenormaluser" type="submit" value="make normal user">';
                            }
                        } else {
                            echo 'Normal User';
                            echo ' <input name="makeadmin"  type="submit" value="make admin">';
                        }

        if ($user_array['id'] > 1) {
            ?>
                            <input name="removeuser" type="submit" onclick="return confirmRemove()" value="remove user">
                            <?php
        } ?>
                        <input name="search" type="hidden" value='<?php echo $search ?>'>
                    </form>
                </font></td>
        </tr>

        <?php
    }
    ?>

</table>
