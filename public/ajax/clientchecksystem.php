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
require_once 'include/common.php';

$noforcelogin = 1;
include 'public/login.php';

if (!isset($_SESSION['cdash'])) {
    echo 'Not valid id';
    return;
}

$siteids = $_POST['site'];
$cmakeids = $_POST['cmake'];
$compilerids = $_POST['compiler'];
$osids = $_POST['os'];
$libraryids = $_POST['library'];

// Checks
if (!isset($siteids) || !isset($cmakeids) || !isset($compilerids) || !isset($osids)
    || !isset($libraryids)
) {
    echo 'Not a valid request!';
    return;
}

$siteids = explode(',', $siteids);
$cmakeids = explode(',', $cmakeids);
$compilerids = explode(',', $compilerids);
$osids = explode(',', $osids);
$libraryids = explode(',', $libraryids);

$extrasql = '';
$tables = '';
if (!empty($siteids[0])) {
    $extrasql .= ' AND (';
}
foreach ($siteids as $key => $siteid) {
    if (!empty($siteid)) {
        if ($key > 0) {
            $extrasql .= ' OR ';
        }
        $extrasql .= 's.id=' . qnum($siteid);
    }
}
if (!empty($siteids[0])) {
    $extrasql .= ')';
}

// CMake
if (!empty($cmakeids[0])) {
    $extrasql .= ' AND (';
}
foreach ($cmakeids as $key => $cmakeid) {
    if (!empty($cmakeid)) {
        if ($key > 0) {
            $extrasql .= ' OR ';
        }
        $extrasql .= 'client_site2cmake.cmakeid=' . qnum($cmakeid);
    }
}
if (!empty($cmakeids[0])) {
    $extrasql .= ')';
}

// Compiler
if (!empty($compilerids[0])) {
    $extrasql .= ' AND (';
}
foreach ($compilerids as $key => $compilerid) {
    if (!empty($compilerid)) {
        if ($key > 0) {
            $extrasql .= ' OR ';
        }
        $extrasql .= 'client_site2compiler.compilerid=' . qnum($compilerid);
    }
}
if (!empty($compilerids[0])) {
    $extrasql .= ')';
}

// OS
if (!empty($osids[0])) {
    $extrasql .= ' AND (';
}
foreach ($osids as $key => $osid) {
    if (!empty($osid)) {
        if ($key > 0) {
            $extrasql .= ' OR ';
        }
        $extrasql .= 'os.id=' . qnum($osid);
    }
}
if (!empty($osids[0])) {
    $extrasql .= ')';
}

// Libraries (should have all of them)

if (!empty($libraryids[0])) {
    $tables .= ',client_site2library ';
    $extrasql .= ' AND client_site2library.siteid=s.id AND (';
}
foreach ($libraryids as $key => $libraryid) {
    if (!empty($libraryid)) {
        if ($key > 0) {
            $extrasql .= ' AND ';
        }
        $extrasql .= 'client_site2library.libraryid=' . qnum($libraryid);
    }
}
if (!empty($libraryids[0])) {
    $extrasql .= ')';
}

// Check for the last 5 minutes
$now = date(FMT_DATETIMESTD, time() - 5 * 60);
$sql = 'SELECT COUNT(DISTINCT s.id) FROM client_site AS s, client_os AS os,
                    client_site2cmake,client_site2compiler' . $tables . '
                    WHERE s.osid=os.id AND client_site2cmake.siteid=s.id
                    AND client_site2compiler.siteid=s.id ' . $extrasql . " AND s.lastping>'" . $now . "'";

$query = pdo_query($sql);
echo pdo_error();
$query_array = pdo_fetch_array($query);
if ($query_array[0] == 0) {
    echo '<br/><b>* No site matching these settings is currently available.</b><br/>';
} else {
    echo '<br/><b>* ' . $query_array[0] . '</b> site';
    $word = 'is';
    if ($query_array[0] > 1) {
        echo 's';
        $word = 'are';
    }
    echo ' matching these settings ' . $word . ' currently available.<br/>';
}

// Check for the last 24 hours
$now = date(FMT_DATETIMESTD, time() - 24 * 60);
$sql = 'SELECT COUNT(DISTINCT s.id) FROM client_site AS s, client_os AS os,
                    client_site2cmake,client_site2compiler' . $tables . '
                    WHERE s.osid=os.id AND client_site2cmake.siteid=s.id
                    AND client_site2compiler.siteid=s.id ' . $extrasql . " AND s.lastping>'" . $now . "'";

$query = pdo_query($sql);
echo pdo_error();
$query_array = pdo_fetch_array($query);
if ($query_array[0] == 0) {
    echo '<b>* No site matching these settings has been responding in the last 24 hours.</b><br/>';
} else {
    echo '<b>* ' . $query_array[0] . '</b> site';
    $word = 'has';
    if ($query_array[0] > 1) {
        echo 's';
        $word = 'have';
    }
    echo ' matching these settings ' . $word . ' been responding in the last 24 hours.<br/>';
}
