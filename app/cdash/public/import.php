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

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Auth;
use CDash\Database;

require_once 'include/pdo.php';

if (Auth::check()) {
    include_once 'include/common.php';
    include_once 'include/ctestparser.php';

    @set_time_limit(0);

    checkUserPolicy(0); // only admin

    //get date info here
    @$dayFrom = $_POST['dayFrom'];
    if (!isset($dayFrom)) {
        $dayFrom = date('d', strtotime('yesterday'));
        $monthFrom = date('m', strtotime('yesterday'));
        $yearFrom = date('Y', strtotime('yesterday'));
        $dayTo = date('d');
        $yearTo = date('Y');
        $monthTo = date('m');
    } else {
        $dayFrom = intval($dayFrom);
        $monthFrom = intval($_POST['monthFrom']);
        $yearFrom = intval($_POST['yearFrom']);
        $dayTo = intval($_POST['dayTo']);
        $monthTo = intval($_POST['monthTo']);
        $yearTo = intval($_POST['yearTo']);
    }

    $xml = begin_XML_for_XSLT();
    $xml .= '<backurl>manageBackup.php</backurl>';
    $xml .= '<title>CDash - Import</title>';
    $xml .= '<menutitle>CDash</menutitle>';
    $xml .= '<menusubtitle>Import Dart1</menusubtitle>';

    $db = Database::getInstance();

    $project = $db->executePrepared('SELECT name, id FROM project ORDER BY id');
    $projName = '';
    foreach ($project as $project_array) {
        $projName = $project_array['name'];
        $xml .= '<project>';
        $xml .= '<name>' . $projName . '</name>';
        $xml .= '<id>' . $project_array['id'] . '</id>';
        $xml .= '</project>';
    }

    $xml .= '<dayFrom>' . $dayFrom . '</dayFrom>';
    $xml .= '<monthFrom>' . $monthFrom . '</monthFrom>';
    $xml .= '<yearFrom>' . $yearFrom . '</yearFrom>';
    $xml .= '<dayTo>' . $dayTo . '</dayTo>';
    $xml .= '<monthTo>' . $monthTo . '</monthTo>';
    $xml .= '<yearTo>' . $yearTo . '</yearTo>';
    $xml .= '</cdash>';

    @$Submit = $_POST['Submit'];
    if ($Submit) {
        $directory = htmlspecialchars($_POST['directory']);
        $projectid = intval($_POST['project']);

        // Checks
        if (!isset($directory) || strlen($directory) < 3) {
            echo 'Not a valid directory!';
            return;
        }

        if ($projectid === 0) {
            echo("Use your browser's Back button, and select a valid project.<br>");
            ob_flush();
            return;
        }

        $output = 'Import for Project: ' . get_project_name($projectid) . '<br>';

        $directory = str_replace('\\\\', '/', $directory);
        if (!file_exists($directory) || !str_contains($directory, 'Sites')) {
            echo("Error: $directory is not a valid path to Dart XML data on the server.<br>\n");
            generate_XSLT($xml, 'import_dart_classic');
            return;
        }
        $startDate = mktime(0, 0, 0, $monthFrom, $dayFrom, $yearFrom);
        $endDate = mktime(0, 0, 0, $monthTo, $dayTo, $yearTo);
        $numDays = ($endDate - $startDate) / (24 * 3600) + 1;
        for ($i = 0; $i < $numDays; $i++) {
            $currentDay = date(FMT_DATE, mktime(0, 0, 0, $monthFrom, $dayFrom + $i, $yearFrom));
            $output .= "Gathering XML files for $currentDay...  $directory/*/*/$currentDay-*/XML/*.xml <br>\n";
            $files = glob($directory . "/*/*/$currentDay-*/XML/*.xml");
            $numFiles = count($files);
            $output .= "$numFiles found<br>\n";

            foreach ($files as $file) {
                if (strlen($file) == 0) {
                    continue;
                }
                $handle = fopen($file, 'r');
                ctest_parse($handle, $projectid);
                fclose($handle);
                unset($handle);
            }
            $output .= '<br>Done for the day ' . $currentDay . "<br>\n";
        }
        $output .= '<a href=index.php?project=' . urlencode($projName) . ">Back to $projName dashboard</a>\n";
        return $output;
    }

    // Now doing the xslt transition
    generate_XSLT($xml, 'import');
} else {
    return LoginController::staticShowLoginForm();
}
