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

include_once 'include/common.php';
redirect_to_https();

require_once 'include/version.php';

use CDash\Model\Project;
use CDash\Model\User;
use CDash\Model\Label;
use CDash\Model\LabelEmail;
use CDash\Model\UserProject;

if ($session_OK) {
    $userid = $_SESSION['cdash']['loginid'];

    @$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
    pdo_select_db("$CDASH_DB_NAME", $db);

    $xml = begin_XML_for_XSLT();
    $xml .= '<backurl>user.php</backurl>';
    $xml .= '<title>CDash - Subscribe to a project</title>';
    $xml .= '<menutitle>CDash</menutitle>';
    $xml .= '<menusubtitle>Subscription</menusubtitle>';

    @$projectid = $_GET['projectid'];
    if ($projectid != null) {
        $projectid = pdo_real_escape_numeric($projectid);
    }

    @$edit = $_GET['edit'];
    if ($edit != null) {
        $edit = pdo_real_escape_numeric($edit);
    }

    // Checks
    if (!isset($projectid) || !is_numeric($projectid)) {
        echo 'Not a valid projectid!';
        return;
    }
    if (isset($edit) && $edit != 1) {
        echo 'Not a valid edit!';
        return;
    }

    if ($edit) {
        $xml .= '<edit>1</edit>';
    } else {
        $xml .= '<edit>0</edit>';
    }

    $project = pdo_query("SELECT id,name,public,emailbrokensubmission FROM project WHERE id='$projectid'");
    $project_array = pdo_fetch_array($project);

    $Project = new Project;
    $User = new User;
    $User->Id = $userid;
    $Project->Id = $projectid;
    $role = $Project->GetUserRole($userid);

    // Check if the project is public
    if (!$project_array['public'] && ($User->IsAdmin() === false && $role < 0)) {
        echo "You don't have the permissions to access this page";
        return;
    }

    // Check if the user is not already in the database
    $user2project = pdo_query("SELECT role,emailtype,emailcategory,emailmissingsites,emailsuccess
                             FROM user2project WHERE userid='$userid' AND projectid='$projectid'");
    if (pdo_num_rows($user2project) > 0) {
        $user2project_array = pdo_fetch_array($user2project);
        $xml .= add_XML_value('role', $user2project_array['role']);
        $xml .= add_XML_value('emailtype', $user2project_array['emailtype']);
        $xml .= add_XML_value('emailmissingsites', $user2project_array['emailmissingsites']);
        $xml .= add_XML_value('emailsuccess', $user2project_array['emailsuccess']);
        $emailcategory = $user2project_array['emailcategory'];
        $xml .= add_XML_value('emailcategory_update', check_email_category('update', $emailcategory));
        $xml .= add_XML_value('emailcategory_configure', check_email_category('configure', $emailcategory));
        $xml .= add_XML_value('emailcategory_warning', check_email_category('warning', $emailcategory));
        $xml .= add_XML_value('emailcategory_error', check_email_category('error', $emailcategory));
        $xml .= add_XML_value('emailcategory_test', check_email_category('test', $emailcategory));
        $xml .= add_XML_value('emailcategory_dynamicanalysis', check_email_category('dynamicanalysis', $emailcategory));
    } else {
        // we set the default categories

        $xml .= add_XML_value('emailcategory_update', 1);
        $xml .= add_XML_value('emailcategory_configure', 1);
        $xml .= add_XML_value('emailcategory_warning', 1);
        $xml .= add_XML_value('emailcategory_error', 1);
        $xml .= add_XML_value('emailcategory_test', 1);
        $xml .= add_XML_value('emailcategory_dynamicanalysis', 1);
    }

    // If we ask to subscribe
    @$Subscribe = $_POST['subscribe'];
    @$UpdateSubscription = $_POST['updatesubscription'];
    @$Unsubscribe = $_POST['unsubscribe'];
    @$Role = $_POST['role'];
    @$Credentials = $_POST['credentials'];
    @$EmailType = $_POST['emailtype'];
    if (!isset($_POST['emailmissingsites'])) {
        $EmailMissingSites = 0;
    } else {
        $EmailMissingSites = $_POST['emailmissingsites'];
    }
    if (!isset($_POST['emailsuccess'])) {
        $EmailSuccess = 0;
    } else {
        $EmailSuccess = $_POST['emailsuccess'];
    }

    // Deals with label email
    $LabelEmail = new LabelEmail();
    $Label = new Label();
    $LabelEmail->ProjectId = $projectid;
    $LabelEmail->UserId = $userid;

    if ($Unsubscribe) {
        pdo_query("DELETE FROM user2project WHERE userid='$userid' AND projectid='$projectid'");
        pdo_query("DELETE FROM user2repository WHERE userid='$userid' AND projectid='$projectid'");

        // Remove the claim sites for this project if they are only part of this project
        pdo_query("DELETE FROM site2user WHERE userid='$userid'
               AND siteid NOT IN
              (SELECT build.siteid FROM build,user2project as up WHERE
               up.projectid = build.projectid AND up.userid='$userid' AND up.role>0
               GROUP BY build.siteid)");
        header('location: user.php?note=unsubscribedtoproject');
    } elseif ($UpdateSubscription) {
        @$emailcategory_update = $_POST['emailcategory_update'];
        @$emailcategory_configure = $_POST['emailcategory_configure'];
        @$emailcategory_warning = $_POST['emailcategory_warning'];
        @$emailcategory_error = $_POST['emailcategory_error'];
        @$emailcategory_test = $_POST['emailcategory_test'];
        @$emailcategory_dynamicanalysis = $_POST['emailcategory_dynamicanalysis'];

        $EmailCategory = $emailcategory_update + $emailcategory_configure + $emailcategory_warning + $emailcategory_error + $emailcategory_test + $emailcategory_dynamicanalysis;
        if (pdo_num_rows($user2project) > 0) {
            $Role = pdo_real_escape_numeric($Role);
            $EmailType = pdo_real_escape_numeric($EmailType);
            $EmailCategory = pdo_real_escape_numeric($EmailCategory);
            $EmailMissingSites = pdo_real_escape_numeric($EmailMissingSites);
            $EmailSuccess = pdo_real_escape_numeric($EmailSuccess);
            pdo_query("UPDATE user2project SET role='$Role',emailtype='$EmailType',
                         emailcategory='$EmailCategory',
                         emailmissingsites='$EmailMissingSites',
                         emailsuccess='$EmailSuccess'
                         WHERE userid='$userid' AND projectid='$projectid'");

            // Update the repository credential
            $UserProject = new UserProject();
            $UserProject->ProjectId = $projectid;
            $UserProject->UserId = $userid;
            $UserProject->UpdateCredentials($Credentials);

            if ($Role == 0) {
                // Remove the claim sites for this project if they are only part of this project
                pdo_query("DELETE FROM site2user WHERE userid='$userid'
                 AND siteid NOT IN
                (SELECT build.siteid FROM build,user2project as up WHERE
                 up.projectid = build.projectid AND up.userid='$userid' AND up.role>0
                 GROUP BY build.siteid)");
            }
        }

        if (isset($_POST['emaillabels'])) {
            $LabelEmail->UpdateLabels($_POST['emaillabels']);
        } else {
            $LabelEmail->UpdateLabels(null);
        }
        // Redirect
        header('location: user.php');
    } elseif ($Subscribe) {
        @$emailcategory_update = $_POST['emailcategory_update'];
        @$emailcategory_configure = $_POST['emailcategory_configure'];
        @$emailcategory_warning = $_POST['emailcategory_warning'];
        @$emailcategory_error = $_POST['emailcategory_error'];
        @$emailcategory_test = $_POST['emailcategory_test'];
        @$emailcategory_dynamicanalysis = $_POST['emailcategory_dynamicanalysis'];

        $EmailCategory = $emailcategory_update + $emailcategory_configure + $emailcategory_warning + $emailcategory_error + $emailcategory_test + $emailcategory_dynamicanalysis;
        if (pdo_num_rows($user2project) > 0) {
            pdo_query("UPDATE user2project SET role='$Role',emailtype='$EmailType',
                         emailcategory='$EmailCategory'.
                         emailmissingsites='$EmailMissingSites',
                         emailsuccess='$EmailSuccess'
                         WHERE userid='$userid' AND projectid='$projectid'");

            // Update the repository credential
            $UserProject = new UserProject();
            $UserProject->ProjectId = $projectid;
            $UserProject->UserId = $userid;
            $UserProject->UpdateCredentials($Credentials);

            if ($Role == 0) {
                // Remove the claim sites for this project if they are only part of this project
                pdo_query("DELETE FROM site2user WHERE userid='$userid'
                 AND siteid NOT IN
                (SELECT build.siteid FROM build,user2project as up WHERE
                 up.projectid = build.projectid AND up.userid='$userid' AND up.role>0
                 GROUP BY build.siteid)");
            }
        } else {
            pdo_query("INSERT INTO user2project (role,userid,projectid,emailtype,emailcategory,emailsuccess,
                                           emailmissingsites)
                 VALUES ('$Role','$userid','$projectid','$EmailType','$EmailCategory',
                         '$EmailSuccess','$EmailMissingSites')");

            $UserProject = new UserProject();
            $UserProject->ProjectId = $projectid;
            $UserProject->UserId = $userid;
            foreach ($Credentials as $credential) {
                $UserProject->AddCredential($credential);
            }
        }
        header('location: user.php?note=subscribedtoproject');
    }

    // XML
    // Show the current credentials for the user
    $query = pdo_query("SELECT credential,projectid FROM user2repository WHERE userid='" . $userid . "'
                      AND (projectid='" . $projectid . "' OR projectid=0)");
    $credential_num = 0;
    while ($credential_array = pdo_fetch_array($query)) {
        if ($credential_array['projectid'] == 0) {
            $xml .= add_XML_value('global_credential', $credential_array['credential']);
        } else {
            $xml .= add_XML_value('credential_' . $credential_num++, $credential_array['credential']);
        }
    }

    $xml .= '<project>';
    $xml .= add_XML_value('id', $project_array['id']);
    $xml .= add_XML_value('name', $project_array['name']);
    $xml .= add_XML_value('emailbrokensubmission', $project_array['emailbrokensubmission']);

    $labelavailableids = $Project->GetLabels(7); // Get the labels for the last 7 days
    $labelids = $LabelEmail->GetLabels();

    $labelavailableids = array_diff($labelavailableids, $labelids);

    foreach ($labelavailableids as $labelid) {
        $xml .= '<label>';
        $xml .= add_XML_value('id', $labelid);
        $Label->Id = $labelid;
        $xml .= add_XML_value('text', $Label->GetText());
        $xml .= '</label>';
    }

    foreach ($labelids as $labelid) {
        $xml .= '<labelemail>';
        $xml .= add_XML_value('id', $labelid);
        $Label->Id = $labelid;
        $xml .= add_XML_value('text', $Label->GetText());
        $xml .= '</labelemail>';
    }

    $xml .= '</project>';

    $sql = 'SELECT id,name FROM project';
    if ($User->IsAdmin() == false) {
        $sql .= " WHERE public=1 OR id IN (SELECT projectid AS id FROM user2project WHERE userid='$userid' AND role>0)";
    }
    $projects = pdo_query($sql);
    while ($project_array = pdo_fetch_array($projects)) {
        $xml .= '<availableproject>';
        $xml .= add_XML_value('id', $project_array['id']);
        $xml .= add_XML_value('name', $project_array['name']);
        if ($project_array['id'] == $projectid) {
            $xml .= add_XML_value('selected', '1');
        }
        $xml .= '</availableproject>';
    }

    $xml .= '</cdash>';

    // Now doing the xslt transition
    generate_XSLT($xml, 'subscribeProject');
}
