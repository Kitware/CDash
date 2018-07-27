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

include dirname(__DIR__) . '/config/config.php';
require_once 'include/pdo.php';
include 'public/login.php';
include 'include/version.php';

use CDash\Config;
$config = Config::getInstance();

if ($session_OK) {
    include_once 'include/common.php';
    include_once 'include/ctestparser.php';

    @set_time_limit(0);

    checkUserPolicy(@$_SESSION['cdash']['loginid'], 0); // only admin
    $xml = begin_XML_for_XSLT();
    $xml .= '<title>CDash - Import Backups</title>';
    $xml .= '<menutitle>CDash</menutitle>';
    $xml .= '<menusubtitle>Backups</menusubtitle>';
    $xml .= '<backurl>manageBackup.php</backurl>';
    $alert = '';

    @$Submit = $_POST['Submit'];

    @$filemask = $_POST['filemask'];
    if ($filemask == '') {
        $filemask = '*.xml';
    }

    if ($Submit && $filemask) {
        $filelist = glob($config->get('CDASH_BACKUP_DIRECTORY') . "/$filemask");
        $i = 0;
        $n = count($filelist);

        add_log(
            'before loop n=' . $n,
            'importBackup.php',
            LOG_INFO);

        foreach ($filelist as $filename) {
            ++$i;
            $projectid = -1;

            add_log(
                'looping i=' . $i . ' filename=' . $filename,
                'importBackup.php',
                LOG_INFO);

            # split on path separator
            $pathParts = explode(PATH_SEPARATOR, $filename);

            # split on cdash separator "_"
            if (count($pathParts) >= 1) {
                $cdashParts = preg_split('#_#', $pathParts[count($pathParts) - 1]);
                $projectid = get_project_id($cdashParts[0]);
            }

            if ($projectid != -1) {
                $name = get_project_name($projectid);
                $handle = fopen($filename, 'r');
                if ($handle) {
                    ctest_parse($handle, $projectid);
                    fclose($handle);
                    unset($handle);
                } else {
                    add_log(
                        'could not open file filename=' . $filename,
                        'importBackup.php',
                        LOG_ERR);
                }
            } else {
                add_log(
                    'could not determine projectid from filename=' . $filename,
                    'importBackup.php',
                    LOG_ERR);
            }
        }

        add_log(
            'after loop n=' . $n,
            'importBackup.php',
            LOG_INFO);

        $alert = 'Import backup complete. ' . $i . ' files processed.';
        $xml .= add_XML_value('alert', $alert);
    }

    // Now doing the xslt transition
    $xml .= '</cdash>';
    generate_XSLT($xml, 'importBackup');
}
