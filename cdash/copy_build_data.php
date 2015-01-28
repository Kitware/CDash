<?php
/*=========================================================================

  Copyright (c) Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/

/**
 * Entry point function.
 * When you add support for a new type of submission, make sure that
 * it is handled in the switch statement below.
 **/
function copy_build_data($old_buildid, $new_buildid, $type)
{
  switch ($type)
    {
    case "GcovTar":
      copy_coverage_data($old_buildid, $new_buildid);
    }
}

/**
 * Copy coverage info from one build to another.
 **/
function copy_coverage_data($old_buildid, $new_buildid)
{
  $tables_to_copy =
    ["coverage", "coveragefilelog", "coveragesummary", "coveragesummarydiff"];

  foreach ($tables_to_copy as $table)
    {
    copy_build_table($old_buildid, $new_buildid, $table);
    }
}

/**
 * Copy table rows by assigning them a new buildid.
 **/
function copy_build_table($old_buildid, $new_buildid, $table)
{
  $result = pdo_query("SELECT * FROM $table WHERE buildid=$old_buildid");
  while($result_array = pdo_fetch_array($result))
    {
    // Remove the old buildid from our SELECT results, as we will be replacing
    // that with the new buildid.
    unset($result_array['buildid']);

    // Generate an INSERT query by listing all of the table columns
    // and their values.  This is slightly complicated by the fact that
    // our array has both string and integer keys.
    $keys = array();
    $values = array();
    foreach ($result_array as $key => $val)
      {
      if (!is_int($key))
        {
        $keys[] = $key;
        $values[] = $val;
        }
      }
    $insert_query = "INSERT INTO $table (buildid,";
    $insert_query .= implode(",", $keys) . ")";
    $insert_query .= " VALUES ('$new_buildid','";
    $insert_query .= implode("','", $values) . "')";
    pdo_query($insert_query);
    }
}

?>
