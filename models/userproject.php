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

// It is assumed that appropriate headers should be included before including this file
class UserProject
{
    public $Role;
    public $RepositoryCredential;
    public $EmailType;
    public $EmailCategory;
    public $EmailMissingSites; // send email when a site is missing for the project (expected builds)
    public $EmailSuccess; // email when my checkin are fixing something
    public $UserId;
    public $ProjectId;
    private $PDO;

    public function __construct()
    {
        $this->Role = 0;
        $this->EmailType = 1;
        $this->ProjectId = 0;
        $this->UserId = 0;
        $this->EmailCategory = 126;
        $this->EmailMissingSites = 0;
        $this->EmailSuccess = 0;
        $this->PDO = get_link_identifier()->getPdo();
    }

    /** Return if a project exists */
    public function Exists()
    {
        // If no id specify return false
        if (!$this->ProjectId || !$this->UserId) {
            return false;
        }

        $query = pdo_query("SELECT count(*) FROM user2project WHERE userid='" . $this->UserId . "' AND projectid='" . $this->ProjectId . "'");
        $query_array = pdo_fetch_array($query);
        if ($query_array[0] > 0) {
            return true;
        }
        return false;
    }

    // Save the project in the database
    public function Save()
    {
        if (!$this->ProjectId) {
            echo 'UserProject::Save(): no ProjectId specified';
            return false;
        }

        if (!$this->UserId) {
            echo 'UserProject::Save(): no UserId specified';
            return false;
        }

        // Check if the project is already
        if ($this->Exists()) {
            // Update the project
            $query = 'UPDATE user2project SET';
            $query .= " role='" . $this->Role . "'";
            $query .= ",emailtype='" . $this->EmailType . "'";
            $query .= ",emailcategory='" . $this->EmailCategory . "'";
            $query .= ",emailsuccess='" . $this->EmailSuccess . "'";
            $query .= ",emailmissingsites='" . $this->EmailMissingSites . "'";
            $query .= " WHERE userid='" . $this->UserId . "' AND projectid='" . $this->ProjectId . "'";
            if (!pdo_query($query)) {
                add_last_sql_error('User2Project Update');
                return false;
            }
        } else {
            // insert

            $query = "INSERT INTO user2project (userid,projectid,role,emailtype,emailcategory,
                                          emailsuccess,emailmissingsites)
                VALUES ($this->UserId,$this->ProjectId,$this->Role,
                        $this->EmailType,$this->EmailCategory,$this->EmailSuccess,$this->EmailMissingSites)";
            if (!pdo_query($query)) {
                add_last_sql_error('User2Project Create');
                echo $query;
                return false;
            }
        }
        return true;
    }

    /** Get the users of the project */
    public function GetUsers($role = -1)
    {
        if (!$this->ProjectId) {
            echo 'UserProject GetUsers(): ProjectId not set';
            return false;
        }

        $sql = '';
        if ($role != -1) {
            $sql = ' AND role=' . $role;
        }

        $project = pdo_query('SELECT userid FROM user2project WHERE projectid=' . qnum($this->ProjectId) . $sql);
        if (!$project) {
            add_last_sql_error('UserProject GetUsers');
            return false;
        }

        $userids = array();
        while ($project_array = pdo_fetch_array($project)) {
            $userids[] = $project_array['userid'];
        }
        return $userids;
    }

    /** Update the credentials for a project */
    public function UpdateCredentials($credentials)
    {
        if (!$this->UserId) {
            add_log('UserId not set', 'UserProject UpdateCredentials()', LOG_ERR,
                $this->ProjectId, 0, CDASH_OBJECT_USER, $this->UserId);
            return false;
        }

        // Insert the new credentials
        $credential_string = '';
        foreach ($credentials as $credential) {
            $this->AddCredential($credential);
            if ($credential_string != '') {
                $credential_string .= ',';
            }
            $credential = pdo_real_escape_string($credential);
            $credential_string .= "'" . $credential . "'";
        }

        // Remove the one that have been removed
        pdo_query('DELETE FROM user2repository WHERE userid=' . qnum($this->UserId) . '
                  AND projectid=' . qnum($this->ProjectId) . '
                  AND credential NOT IN (' . $credential_string . ')');
        add_last_sql_error('UserProject UpdateCredentials');
        return true;
    }

    /** Add a credential for a given project */
    public function AddCredential($credential)
    {
        if (empty($credential)) {
            return false;
        }

        if (!$this->UserId) {
            add_log('UserId not set', 'UserProject AddCredential()', LOG_ERR,
                $this->ProjectId, 0, CDASH_OBJECT_USER, $this->UserId);
            return false;
        }

        // Check if the credential exists for all the project or the given project
        $credential = pdo_real_escape_string($credential);
        $query = pdo_query('SELECT userid FROM user2repository WHERE userid=' . qnum($this->UserId) . '
                        AND (projectid=' . qnum($this->ProjectId) . " OR projectid=0)
                        AND credential='" . $credential . "'");
        add_last_sql_error('UserProject AddCredential');

        if (pdo_num_rows($query) == 0) {
            pdo_query('INSERT INTO user2repository (userid,projectid,credential)
                 VALUES(' . qnum($this->UserId) . ',' . qnum($this->ProjectId) . ",'" . $credential . "')");
            add_last_sql_error('UserProject AddCredential');
            return true;
        }
        return false;
    }

    /** Fill in the information given a projectid and a repository credential.
     *  This function expects the emailtype>0 */
    public function FillFromRepositoryCredential()
    {
        if (!$this->ProjectId) {
            add_log('ProjectId not set', 'UserProject FillFromRepositoryCredential()', LOG_ERR,
                $this->ProjectId, 0, CDASH_OBJECT_USER, $this->UserId);
            return false;
        }

        if (!$this->RepositoryCredential) {
            add_log('RepositoryCredential not set', 'UserProject FillFromRepositoryCredential()', LOG_ERR,
                $this->ProjectId, 0, CDASH_OBJECT_USER, $this->UserId);
            return false;
        }

        $sql = 'SELECT up.emailcategory,up.userid,up.emailsuccess
               FROM user2project AS up,user2repository AS ur
               WHERE up.projectid=' . qnum($this->ProjectId) . "
               AND up.userid=ur.userid
               AND (ur.projectid=0 OR ur.projectid=up.projectid)
               AND ur.credential='" . $this->RepositoryCredential . "'
               AND up.emailtype>0";

        $user = pdo_query($sql);
        if (!$user) {
            add_last_sql_error('UserProject FillFromRepositoryCredential');
            return false;
        }

        if (pdo_num_rows($user) == 0) {
            return false;
        }
        $user_array = pdo_fetch_array($user);
        $this->EmailCategory = $user_array['emailcategory'];
        $this->UserId = $user_array['userid'];
        $this->EmailSuccess = $user_array['emailsuccess'];
        return true;
    }

    public function FillFromUserId()
    {
        if (!$this->ProjectId) {
            add_log('ProjectId not set', 'UserProject FillFromUserId()', LOG_ERR,
                $this->ProjectId, 0, CDASH_OBJECT_USER, $this->UserId);
            return false;
        }

        if (!$this->UserId) {
            add_log('UserId not set', 'UserProject FillFromUserId()', LOG_ERR,
                $this->ProjectId, 0, CDASH_OBJECT_USER, $this->UserId);
            return false;
        }

        $stmt = $this->PDO->prepare(
            'SELECT * FROM user2project
            WHERE projectid = :projectid
            AND userid = :userid
            AND emailtype > 0');
        $stmt->bindParam(':projectid', $this->ProjectId);
        $stmt->bindParam(':userid', $this->UserId);
        if (!pdo_execute($stmt)) {
            return false;
        }

        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        $this->EmailCategory = $row['emailcategory'];
        $this->EmailMissingSites = $row['emailmissingsites'];
        $this->EmailSuccess = $row['emailsuccess'];
        $this->EmailType = $row['emailtype'];
        $this->Role = $row['role'];
        return true;
    }

    /** Get the email category from the user id */
    public function GetEmailCategory()
    {
        if (!$this->UserId) {
            echo 'UserProject GetEmailCategory(): UserId not set';
            return false;
        }

        if (!$this->ProjectId) {
            echo 'UserProject GetEmailCategory(): ProjectId not set';
            return false;
        }

        $category = pdo_query('SELECT emailcategory FROM user2project WHERE
                          userid=' . qnum($this->UserId) . ' AND projectid=' . qnum($this->ProjectId));
        if (!$category) {
            add_last_sql_error('UserProject GetEmailCategory');
            return false;
        }
        $category_array = pdo_fetch_array($category);
        return $category_array['emailcategory'];
    }

    /** Get information about the projects associated with this user. */
    public function GetProjects()
    {
        if (!$this->UserId) {
            echo 'UserProject GetProjects(): UserId not set';
            return false;
        }

        $stmt = $this->PDO->prepare(
            'SELECT u2p.projectid AS id, role, name, p.authenticatesubmissions
            FROM user2project u2p
            JOIN project p on u2p.projectid = p.id
            WHERE userid = ?
            ORDER BY p.name ASC');
        pdo_execute($stmt, [$this->UserId]);
        return $stmt->fetchAll();
    }
}
