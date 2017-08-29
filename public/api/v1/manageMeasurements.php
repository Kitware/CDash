<?php
/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

include dirname(dirname(dirname(__DIR__))) . '/config/config.php';
require_once 'include/pdo.php';
include 'public/login.php';
include_once 'include/common.php';
include_once 'models/project.php';
include_once 'models/user.php';

if ($session_OK) {
    $projectid = pdo_real_escape_numeric($_REQUEST['projectid']);
    checkUserPolicy(@$_SESSION['cdash']['loginid'], $projectid);

    // Checks
    if (!isset($projectid) || !is_numeric($projectid)) {
        echo 'Not a valid projectid!';
        return;
    }

    $project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
    if (pdo_num_rows($project) > 0) {
        $project_array = pdo_fetch_array($project);
        $projectname = $project_array['name'];
        $nightlytime = $project_array['nightlytime'];
    }

    if (array_key_exists('submit', $_POST)) {
        $submit = $_POST['submit'];
        $nameN = htmlspecialchars(pdo_real_escape_string($_POST['nameN']));
        $showTN = htmlspecialchars(pdo_real_escape_string($_POST['showTN']));
        $showSN = htmlspecialchars(pdo_real_escape_string($_POST['showSN']));

        $id = $_POST['id'];
        $name = $_POST['name'];

        // Start operation if it is submitted
        if ($submit == 'Save') {
            if ($nameN) {
                pdo_query("INSERT INTO measurement (projectid,name,testpage,summarypage) VALUES ('$projectid','$nameN','$showTN','$showSN')"); // only write a new entry if new field is filled
            }
            $i = 0;

            if (count($_POST['name'])) {
                foreach ($name as $newName) { // everytime update all test attributes
                    $showT = $_POST['showT'];
                    $showS = $_POST['showS'];
                    if ($showT[$id[$i]] == '') {
                        $showT[$id[$i]] = 0;
                    }
                    if ($showS[$id[$i]] == '') {
                        $showS[$id[$i]] = 0;
                    }
                    pdo_query("UPDATE measurement SET name='$newName', testpage='" . $showT[$id[$i]] . "', summarypage='" . $showS[$id[$i]] . "' WHERE id='" . $id[$i] . "'");
                    $i++;
                }
            }
        }
        $selection = $_POST['select'];
        if ($_POST['del'] && count($selection) > 0) { // if user chose any named measurement delete them
            foreach ($selection as $del) {
                pdo_query("DELETE FROM measurement WHERE id='$del'");
            }
        }
    }


    $xml = begin_XML_for_XSLT();
    $xml .= '<backurl>user.php</backurl>';
    $xml .= '<title>CDash - ' . $projectname . ' Measurements</title>';
    $xml .= '<menutitle>' . $projectname . '</menutitle>';
    $xml .= '<menusubtitle>Measurements</menusubtitle>';

    if ($projectid > 0) {
        $Project = new Project;
        $Project->Id = $projectid;
        $xml .= '<project>';
        $xml .= add_XML_value('id', $projectid);
        $xml .= add_XML_value('name', $Project->GetName());
        $xml .= add_XML_value('name_encoded', urlencode($Project->GetName()));

        $xml .= '</project>';
    }

    // Menu
    $xml .= '<menu>';
    $xml .= add_XML_value('noprevious', '1');
    $xml .= add_XML_value('nonext', '1');
    $xml .= '</menu>';
    {
        $userid = $_SESSION['cdash']['loginid'];
        $user = new User();
        $user->Id = $userid;
        $user->Fill();
        $xml .= '<user>';
        $xml .= add_XML_value('id', $userid);
        $xml .= add_XML_value('admin', $user->Admin);
        $xml .= '</user>';
    }

    //get any measurements associated with this test
    $xml .= '<measurements>';
    $query = "SELECT id,name,testpage,summarypage FROM measurement WHERE projectid='$projectid' ORDER BY name ASC";
    $result = pdo_query($query);
    while ($row = pdo_fetch_array($result)) {
        $xml .= '<measurement>';
        $xml .= add_XML_value('id', $row['id']);
        $xml .= add_XML_value('name', $row['name']);
        $xml .= add_XML_value('showT', $row['testpage']);
        $xml .= add_XML_value('showS', $row['summarypage']);
        $xml .= '</measurement>';
    }
    $xml .= '</measurements>';
    $xml .= '</cdash>';

    // Now doing the xslt transition
    generate_XSLT($xml, 'manageMeasurements');
}
