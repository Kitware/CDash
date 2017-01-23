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
require_once 'include/common.php';
require_once 'include/version.php';
require_once 'models/userproject.php';
require_once 'include/cdashmail.php';

if ($session_OK) {
    @$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
    pdo_select_db("$CDASH_DB_NAME", $db);

    $usersessionid = $_SESSION['cdash']['loginid'];
    // Checks
    if (!isset($usersessionid) || !is_numeric($usersessionid)) {
        echo 'Not a valid usersessionid!';
        return;
    }

    @$projectid = $_GET['projectid'];
    if ($projectid != null) {
        $projectid = pdo_real_escape_numeric($projectid);
    }

    // If the projectid is not set and there is only one project we go directly to the page
    if (!isset($projectid)) {
        $project = pdo_query('SELECT id FROM project');
        if (pdo_num_rows($project) == 1) {
            $project_array = pdo_fetch_array($project);
            $projectid = $project_array['id'];
        }
    }

    $role = 0;
    $user_array = pdo_fetch_array(pdo_query('SELECT admin FROM ' . qid('user') . " WHERE id='$usersessionid'"));
    if ($projectid && is_numeric($projectid)) {
        $user2project = pdo_query("SELECT role FROM user2project WHERE userid='$usersessionid' AND projectid='$projectid'");
        if (pdo_num_rows($user2project) > 0) {
            $user2project_array = pdo_fetch_array($user2project);
            $role = $user2project_array['role'];
        }
    }

    if ($user_array['admin'] != 1 && $role <= 1) {
        echo "You don't have the permissions to access this page";
        return;
    }

    $xml = begin_XML_for_XSLT();
    $xml .= '<backurl>user.php</backurl>';
    $xml .= '<title>CDash - Project Roles</title>';
    $xml .= '<menutitle>CDash</menutitle>';
    $xml .= '<menusubtitle>Project Roles</menusubtitle>';

// Form post
    @$adduser = $_POST['adduser'];
    @$removeuser = $_POST['removeuser'];

    @$userid = $_POST['userid'];
    if ($userid != null) {
        $userid = pdo_real_escape_numeric($userid);
    }

    @$role = $_POST['role'];
    if ($role != null) {
        $role = pdo_real_escape_numeric($role);
    }

    @$emailtype = $_POST['emailtype'];
    if ($emailtype != null) {
        $emailtype = pdo_real_escape_numeric($emailtype);
    }

    @$credentials = $_POST['credentials'];
    @$repositoryCredential = $_POST['repositoryCredential'];
    @$updateuser = $_POST['updateuser'];
    @$importUsers = $_POST['importUsers'];
    @$registerUsers = $_POST['registerUsers'];

    @$registerUser = $_POST['registerUser'];

// Register a user and send the email
    function register_user($projectid, $email, $firstName, $lastName, $repositoryCredential)
    {
        include dirname(__DIR__) . '/config/config.php';
        $UserProject = new UserProject();
        $UserProject->ProjectId = $projectid;

        // Check if the user is already registered
        $user = pdo_query('SELECT id FROM ' . qid('user') . " WHERE email='$email'");
        if (pdo_num_rows($user) > 0) {
            // Check if the user has been registered to the project
            $user_array2 = pdo_fetch_array($user);
            $userid = $user_array2['id'];
            $user = pdo_query("SELECT userid FROM user2project WHERE userid='$userid' AND projectid='$projectid'");
            if (pdo_num_rows($user) == 0) {
                // not registered

                // We register the user to the project
                pdo_query("INSERT INTO user2project (userid,projectid,role,emailtype)
                                  VALUES ('$userid','$projectid','0','1')");

                // We add the credentials if not already added
                $UserProject->UserId = $userid;
                $UserProject->AddCredential($repositoryCredential);
                $UserProject->ProjectId = 0;
                $UserProject->AddCredential($email); // Add the email by default

                echo pdo_error();
                return false;
            }
            return '<error>User ' . $email . ' already registered.</error>';
        } // already registered

        // Check if the repositoryCredential exists for this project
        $UserProject->RepositoryCredential = $repositoryCredential;
        if ($UserProject->FillFromRepositoryCredential() === true) {
            return '<error>' . $repositoryCredential . ' was already registered for this project under a different email address</error>';
        }

        // Register the user
        // Create a new password
        $keychars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $length = 10;

        $pass = '';
        $max = strlen($keychars) - 1;
        for ($i = 0; $i <= $length; $i++) {
            // random_int is available in PHP 7 and the random_compat PHP 5.x
            // polyfill included in the Composer package.json dependencies.
            $pass .= substr($keychars, random_int(0, $max), 1);
        }
        $encrypted = md5($pass);

        pdo_query('INSERT INTO ' . qid('user') . " (email,password,firstname,lastname,institution,admin)
                 VALUES ('$email','$encrypted','$firstName','$lastName','','0')");
        add_last_sql_error('register_user');
        $userid = pdo_insert_id('user');

        // Insert the user into the project
        pdo_query("INSERT INTO user2project (userid,projectid,role,emailtype)
                                VALUES ('$userid','$projectid','0','1')");
        add_last_sql_error('register_user');

        // We add the credentials if not already added
        $UserProject->UserId = $userid;
        $UserProject->AddCredential($repositoryCredential);
        $UserProject->ProjectId = 0;
        $UserProject->AddCredential($email); // Add the email by default

        $currentURI = get_server_URI();

        $prefix = '';
        if (strlen($firstName) > 0) {
            $prefix = ' ';
        }

        $project = pdo_query("SELECT name FROM project WHERE id='$projectid'");
        $project_array = pdo_fetch_array($project);
        $projectname = $project_array['name'];

        // Send the email
        $text = 'Hello' . $prefix . $firstName . ",\n\n";
        $text .= 'You have been registered to CDash because you have CVS/SVN access to the repository for ' . $projectname . "\n";
        $text .= 'To access your CDash account: ' . $currentURI . "/user.php\n";
        $text .= 'Your login is: ' . $email . "\n";
        $text .= 'Your password is: ' . $pass . "\n\n";
        $text .= 'Generated by CDash.';

        if (cdashmail("$email", 'CDash - ' . $projectname . ' : Subscription', "$text")) {
            echo 'Email sent to: ' . $email . '<br>';
        } else {
            add_log("cannot send email to: $email", 'register_user', LOG_ERR);
        }
        return true;
    }

    if (isset($_POST['sendEmailToSiteMaintainers'])) {
        $emailMaintainers = htmlspecialchars(pdo_real_escape_string($_POST['emailMaintainers']));
        if (strlen($emailMaintainers) < 50) {
            $xml .= '<error>The email should be more than 50 characters.</error>';
        } else {
            $maintainerids = find_site_maintainers($projectid);

            $email = '';
            foreach ($maintainerids as $maintainerid) {
                $user2 = pdo_query('SELECT email FROM ' . qid('user') . " WHERE id='$maintainerid'");
                $user_array2 = pdo_fetch_array($user2);
                if (strlen($email) > 0) {
                    $email .= ', ';
                }
                $email .= $user_array2['email'];
            }

            $projectname = get_project_name($projectid);
            if ($email != '') {
                if (cdashmail("$email", 'CDash - ' . $projectname . ' : To Site Maintainers', "$emailMaintainers")) {
                    $xml .= '<warning>Email sent to site maintainers.</warning>';
                } else {
                    $xml .= '<error>Cannot send email to site maintainers.</error>';
                }
            } else {
                $xml .= '<error>There is no site maintainers for this project.</error>';
            }
        }
    }

// Register a user
    if ($registerUser) {
        @$email = $_POST['registeruseremail'];
        if ($email != null) {
            $email = htmlspecialchars(pdo_real_escape_string($email));
        }
        @$firstName = $_POST['registeruserfirstname'];
        if ($firstName != null) {
            $firstName = htmlspecialchars(pdo_real_escape_string($firstName));
        }
        @$lastName = $_POST['registeruserlastname'];
        if ($lastName != null) {
            $lastName = htmlspecialchars(pdo_real_escape_string($lastName));
        }
        @$repositoryCredential = $_POST['registeruserrepositorycredential'];

        if (strlen($email) < 3 || strlen($firstName) < 2 || strlen($lastName) < 2) {
            $xml .= '<error>Email, first name and last name should be filled out.</error>';
        } else {
            // Call the register_user function
            $xml .= register_user($projectid, $email, $firstName, $lastName, $repositoryCredential);
        }
    }

// Register CVS users
    if ($registerUsers) {
        $cvslogins = $_POST['cvslogin'];
        $emails = $_POST['email'];
        $firstnames = $_POST['firstname'];
        $lastnames = $_POST['lastname'];
        $cvsuser = $_POST['cvsuser'];

        for ($logini = 0; $logini < count($cvslogins); $logini++) {
            if (!isset($cvsuser[$logini])) {
                continue;
            }

            $cvslogin = $cvslogins[$logini];
            $email = $emails[$logini];
            $firstName = $firstnames[$logini];
            $lastName = $lastnames[$logini];

            // Call the register_user function
            $xml .= register_user($projectid, $email, $firstName, $lastName, $cvslogin);
        }
    }

// Add a user
    if ($adduser) {
        $user2project = pdo_query("SELECT userid FROM user2project WHERE userid='$userid' AND projectid='$projectid'");

        if (pdo_num_rows($user2project) == 0) {
            pdo_query("INSERT INTO user2project (userid,role,projectid,emailtype) VALUES ('$userid','$role','$projectid','1')");

            $UserProject = new UserProject();
            $UserProject->ProjectId = $projectid;
            $UserProject->UserId = $userid;
            $UserProject->AddCredential($repositoryCredential);
        }
    }

// Remove the user
    if ($removeuser) {
        pdo_query("DELETE FROM user2project WHERE userid='$userid' AND projectid='$projectid'");
        pdo_query("DELETE FROM user2repository WHERE userid='$userid' AND projectid='$projectid'");
        echo pdo_error();
    }

// Update the user
    if ($updateuser) {
        // Update the credentials
        $UserProject = new UserProject();
        $UserProject->ProjectId = $projectid;
        $UserProject->UserId = $userid;

        $credentials_array = explode(';', $credentials);
        $UserProject->UpdateCredentials($credentials_array);

        pdo_query("UPDATE user2project SET role='$role',emailtype='$emailtype' WHERE userid='$userid' AND projectid='$projectid'");
        echo pdo_error();
    }

// Import the users from CVS
    if ($importUsers) {
        $contents = file_get_contents($_FILES['cvsUserFile']['tmp_name']);
        if (strlen($contents) > 0) {
            $id = 0;
            $pos = 0;
            $pos2 = strpos($contents, "\n");
            while ($pos !== false) {
                $line = substr($contents, $pos, $pos2 - $pos);

                $email = '';
                $svnlogin = '';
                $firstname = '';
                $lastname = '';

                // first is the svnuser
                $possvn = strpos($line, ':');
                if ($possvn !== false) {
                    $svnlogin = trim(substr($line, 0, $possvn));

                    $posemail = strpos($line, ':', $possvn + 1);
                    if ($posemail !== false) {
                        $email = trim(substr($line, $possvn + 1, $posemail - $possvn - 1));

                        $name = substr($line, $posemail + 1);
                        $posname = strpos($name, ',');
                        if ($posname !== false) {
                            $name = substr($name, 0, $posname);
                        }

                        $name = trim($name);

                        // Find the firstname
                        $posfirstname = strrpos($name, ' ');
                        if ($posfirstname !== false) {
                            $firstname = trim(substr($name, 0, $posfirstname));
                            $lastname = trim(substr($name, $posfirstname));
                        } else {
                            $firstname = $name;
                        }
                    } else {
                        $email = trim(substr($line, $possvn + 1));
                    }
                }

                if (strlen($email) > 0 && $email != 'kitware@kitware.com') {
                    $xml .= '<cvsuser>';
                    $xml .= '<email>' . $email . '</email>';
                    $xml .= '<cvslogin>' . $svnlogin . '</cvslogin>';
                    $xml .= '<firstname>' . $firstname . '</firstname>';
                    $xml .= '<lastname>' . $lastname . '</lastname>';
                    $xml .= '<id>' . $id . '</id>';
                    $xml .= '</cvsuser>';
                    $id++;
                }

                $pos = $pos2;
                $pos2 = strpos($contents, "\n", $pos2 + 1);
            }
        } else {
            echo 'Cannot parse CVS users file';
        }
    }

    $sql = 'SELECT id,name FROM project';
    if ($user_array['admin'] != 1) {
        $sql .= " WHERE id IN (SELECT projectid AS id FROM user2project WHERE userid='$usersessionid' AND role>0)";
    }
    $sql .= ' ORDER BY name';
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

// If we have a project id
    if ($projectid > 0) {
        $project = pdo_query("SELECT id,name FROM project WHERE id='$projectid'");
        $project_array = pdo_fetch_array($project);
        $xml .= '<project>';
        $xml .= add_XML_value('id', $project_array['id']);
        $xml .= add_XML_value('name', $project_array['name']);
        $xml .= add_XML_value('name_encoded', urlencode($project_array['name']));
        $xml .= '</project>';

        // List the users for that project
        $user = pdo_query('SELECT u.id,u.firstname,u.lastname,u.email,up.role,up.emailtype
                       FROM user2project AS up, ' . qid('user') . " as u
                       WHERE u.id=up.userid  AND up.projectid='$projectid'
                       ORDER BY u.firstname ASC");
        add_last_sql_error('ManageProjectRole');

        $i = 0;
        while ($user_array = pdo_fetch_array($user)) {
            $userid = $user_array['id'];
            $xml .= '<user>';

            if ($i % 2 == 0) {
                $xml .= add_XML_value('bgcolor', '#CADBD9');
            } else {
                $xml .= add_XML_value('bgcolor', '#FFFFFF');
            }
            $i++;
            $xml .= add_XML_value('id', $userid);
            $xml .= add_XML_value('firstname', $user_array['firstname']);
            $xml .= add_XML_value('lastname', $user_array['lastname']);
            $xml .= add_XML_value('email', $user_array['email']);

            $credentials = pdo_query("SELECT credential FROM user2repository as ur
                              WHERE ur.userid='" . $userid . "'
                              AND (ur.projectid='$projectid' OR ur.projectid=0)");
            add_last_sql_error('ManageProjectRole');

            while ($credentials_array = pdo_fetch_array($credentials)) {
                $xml .= add_XML_value('repositorycredential', $credentials_array['credential']);
            }

            $xml .= add_XML_value('role', $user_array['role']);
            $xml .= add_XML_value('emailtype', $user_array['emailtype']);

            $xml .= '</user>';
        }

        // Check if a user is committing without being registered to CDash or with email disabled
        $date = date(FMT_DATETIME, strtotime(date(FMT_DATETIME) . ' -30 days'));
        $sql = 'SELECT DISTINCT author,emailtype,' . qid('user') . '.email FROM dailyupdate,dailyupdatefile
            LEFT JOIN user2repository ON (dailyupdatefile.author=user2repository.credential
            AND (user2repository.projectid=0 OR user2repository.projectid=' . qnum($project_array['id']) . ')
            )
            LEFT JOIN user2project ON (user2repository.userid= user2project.userid AND
            user2project.projectid=' . qnum($project_array['id']) . ')
            LEFT JOIN ' . qid('user') . ' ON (user2project.userid=' . qid('user') . '.id)
            WHERE
             dailyupdatefile.dailyupdateid=dailyupdate.id
             AND dailyupdate.projectid=' . qnum($project_array['id']) .
            " AND dailyupdatefile.checkindate>'" . $date . "' AND (emailtype=0 OR emailtype IS NULL)";

        $query = pdo_query($sql);
        add_last_sql_error('ManageProjectRole');
        while ($query_array = pdo_fetch_array($query)) {
            $xml .= '<baduser>';
            $xml .= add_XML_value('author', $query_array['author']);
            $xml .= add_XML_value('emailtype', $query_array['emailtype']);
            $xml .= add_XML_value('email', $query_array['email']);
            $xml .= '</baduser>';
        }
    }

    if (isset($CDASH_FULL_EMAIL_WHEN_ADDING_USER) && $CDASH_FULL_EMAIL_WHEN_ADDING_USER == 1) {
        $xml .= add_XML_value('fullemail', '1');
    }
    $xml .= '</cdash>';

// Now doing the xslt transition
    generate_XSLT($xml, 'manageProjectRoles');
}
