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

include('login.php');


function echo_svn_output($cmd)
{
  // Assumes being able to run 'svn' on the web server in the CDash
  // directory...
  //
  $svn_output = `svn $cmd`;

  echo '<h3>svn ' . $cmd . '</h3>';
  echo '<pre>';
  echo htmlentities($svn_output);
  echo '</pre>';
  echo '<br/>';
}


if ($session_OK)
  {
  $userid = $_SESSION['cdash']['loginid'];

  $user_is_admin = pdo_get_field_value(
    "SELECT admin FROM " . qid("user") . " WHERE id='$userid'",
    'admin',
    0);

  if ($user_is_admin)
    {
    echo_svn_output('info');
    echo_svn_output('status');
    echo_svn_output('diff');
    }
  else
    {
    echo 'Admin login required to display svn info.';
    }
  }

?>
