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
require_once("cdash/log.php");


  //
  // ATTENTION CDash devs:
  //   See also new file "pdocore.php"
  //
  // This file contains pdo functions that use the "add_log" function.
  // The functions in pdocore.php are not allowed to use "add_log"
  //
  // The separation is necessary now because add_log logs its calls
  // into the database, and needs to call pdocore.php functions
  // to do it.
  //


// pdo_single_row_query returns a single row. Useful for SELECT
// queries that are expected to return 0 or 1 rows.
//
function pdo_single_row_query($qry)
{
  $result = pdo_query($qry);
  if (FALSE === $result)
  {
    add_log('error: pdo_query failed: ' . pdo_error(),
      'pdo_single_row_query', LOG_ERR);
    return array();
  }

  $num_rows = pdo_num_rows($result);
  if (0 !== $num_rows && 1 !== $num_rows)
  {
    add_log('error: at most 1 row should be returned, not ' . $num_rows,
      'pdo_single_row_query', LOG_ERR);
    add_log('warning: returning the first row anyway even though result ' .
      'contains ' . $num_rows . ' rows',
      'pdo_single_row_query', LOG_WARNING);
  }

  $row = pdo_fetch_array($result);
  pdo_free_result($result);

  return $row;
}


// pdo_all_rows_query returns all rows. Useful for SELECT
// queries that return any number of rows. Only use
// this one on queries expected to return small result
// sets.
//
function pdo_all_rows_query($qry)
{
  $result = pdo_query($qry);
  if (FALSE === $result)
  {
    add_log('error: pdo_query failed: ' . pdo_error(),
      'pdo_all_rows_query', LOG_ERR);
    return array();
  }

  $all_rows = array();
  while ($row = pdo_fetch_array($result))
    {
    $all_rows[] = $row;
    }
  pdo_free_result($result);

  return $all_rows;
}


// pdo_get_field_value executes the given query, expected to return 0 rows
// or 1 row. If it gets a row, it retrieves the value of the named field
// and returns it. Otherwise, it returns the passed in default value.
//
function pdo_get_field_value($qry, $fieldname, $default)
{
  $row = pdo_single_row_query($qry);

  if (!empty($row))
  {
    $f = $row["$fieldname"];
  }
  else
  {
    $f = $default;
  }

  return $f;
}


?>
