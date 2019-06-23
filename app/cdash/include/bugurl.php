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

require_once 'include/common.php';

function get_bugid_and_pos_from_log($log)
{
    // If the input is empty, return false straight away:
    //
    if ($log == '') {
        return false;
    }

    // Init for false return in case there is no match:
    //
    $bugid = '';
    $pos = -1;

    // See if it matches "BUG: 12345" or "issue23456" or "#78910"
    //
    $matches = array();
    if (preg_match(
        '/^.*([Bb][Uu][Gg]:*|[Ii][Ss][Ss][Uu][Ee]:*|#) *#* *([0-9]+).*$/',
        $log, $matches)) {
        //echo "count(matches)='".count($matches)."'<br/>";
        //echo "matches[0]='$matches[0]'<br/>";
        //echo "matches[1]='$matches[1]'<br/>";
        //echo "matches[2]='$matches[2]'<br/>";

        $bugid = $matches[2];
        $pos = strpos($log, $bugid, 0);

        //echo "bugid='$bugid'<br/>";
        //echo "pos='$pos'<br/>";
    }

    if ($bugid == '') {
        return false;
    }
    return array($bugid, $pos);
}

function get_bug_from_log($log, $baseurl)
{
    $bugurl = '';
    $bugid = -1;
    $pos = -1;

    if ($baseurl != '') {
        $bugid_and_pos = get_bugid_and_pos_from_log($log);

        if ($bugid_and_pos !== false) {
            $bugid = $bugid_and_pos[0];
            $pos = $bugid_and_pos[1];
            $bugurl = XMLStrFormat($baseurl . $bugid);
        }
    }

    if ($bugurl == '') {
        return false;
    }
    return array($bugurl, $bugid, $pos);
}
