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
use CDash\Database;

$config = Config::getInstance();

$projectid = $_GET['projectid'];
if (!isset($projectid) || !is_numeric($projectid)) {
    echo 'Not a valid projectid!';
    return;
}
$projectid = intval($projectid);

$config = Config::getInstance();

$search = $_GET['search'];
$params = [];
if (intval($config->get('CDASH_FULL_EMAIL_WHEN_ADDING_USER')) === 1) {
    $sql = 'email=?';
    $params[] = $search;
} else {
    $sql = "(email LIKE '%' || ? || '%' OR firstname LIKE '%' || ? || '%' OR lastname LIKE '%' || ? || '%')";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

$db = Database::getInstance();
$params[] = $projectid;
$user = $db->executePrepared('
            SELECT id, email, firstname, lastname
            FROM user
            WHERE
                ' . $sql . '
                AND id NOT IN (
                    SELECT userid as id
                    FROM user2project
                    WHERE projectid=?
                )
        ', $params);
echo pdo_error();

?>

<table width="100%" border="0">
    <?php
    if (count($user) === 0) {
        echo '<tr><td>[none]</tr></td>';
    }
    foreach ($user as $user_array) {
        ?>
        <tr>
            <td width="20%" bgcolor="#EEEEEE">
                <font size="2"><?php echo $user_array['firstname'] . ' ' . $user_array['lastname'] . ' (' . $user_array['email'] . ')'; ?></font>
            </td>
            <td bgcolor="#EEEEEE">
                <font size="2">
                    <form method="post" action="" name="formuser_<?php echo $user_array['id'] ?>">
                        <input name="userid" type="hidden" value="<?php echo $user_array['id'] ?>">
                        role: <select name="role">
                            <option value="0">Normal User</option>
                            <option value="1">Site maintainer</option>
                            <option value="2">Project administrator</option>
                        </select>
                        Repository credential: <input name="repositoryCredential" type="text" size="20"/>
                        <input name="adduser" type="submit" value="add user">
                    </form>
                </font>
            </td>
        </tr>
    <?php } ?>
</table>

