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

require_once 'include/pdo.php';
require_once 'include/common.php';

use CDash\Config;
use Illuminate\Support\Facades\Auth;
use CDash\Database;

$config = Config::getInstance();

if (!Auth::check()) {
    echo 'Not a valid session';
    return;
}

$userid = Auth::id();
// Checks
if (!isset($userid) || !is_numeric($userid)) {
    echo 'Not a valid user';
    return;
}

$db = Database::getInstance();
$user_array = $db->executePreparedSingleRow('SELECT admin FROM user WHERE id=?', [intval($userid)]);

if ($user_array['admin'] != 1) {
    echo "You don't have permission to access this page";
    return;
}

$search = $_GET['search'];
if ($config->get('CDASH_FULL_EMAIL_WHEN_ADDING_USER') == 1) {
    $sql = "email=?";
    $params = [$search];
} else {
    $sql = "email LIKE '%' || ? || '%' OR firstname LIKE '%' || ? || '%' OR lastname LIKE '%' || ? || '%'";
    $params = [$search, $search, $search];
}
$user = $db->executePrepared('SELECT id, email, firstname, lastname, admin FROM user WHERE ' . $sql, $params);
echo pdo_error();

?>

<table width="100%" border="0">
    <?php
    if (count($user) === 0) {
        echo '<tr><td>[none]</tr></td>';
    }
    foreach ($user as $user_array) { ?>
        <tr>
            <td width="20%" bgcolor="#EEEEEE">
                <font size="2"><?php echo $user_array['firstname'] . ' ' . $user_array['lastname'] . ' (' . $user_array['email'] . ')'; ?></font>
            </td>
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

                    if (intval($user_array['id']) > 1) {
                        ?>
                        <input name="removeuser" type="submit" onclick="return confirmRemove()" value="remove user">
                    <?php } ?>
                    <input name="search" type="hidden" value='<?php echo $search ?>'>
                </form>
                </font></td>
        </tr>
    <?php } ?>
</table>
