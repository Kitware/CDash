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
namespace CDash\Model;

use CDash\Database;
use App\Models\User;

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

    const NORMAL_USER = 0;
    const SITE_MAINTAINER = 1;
    const PROJECT_ADMIN = 2;

    public function __construct()
    {
        $this->Role = 0;
        $this->EmailType = 1;
        $this->ProjectId = 0;
        $this->UserId = 0;
        $this->EmailCategory = 126;
        $this->EmailMissingSites = 0;
        $this->EmailSuccess = 0;
        $this->PDO = Database::getInstance()->getPdo();
    }

    /**
     * TODO: this is a non-static method on UserProject, pls mv asap
     */
    public static function GetProjectsForUser(User $user): array
    {
        /** @var \PDO $pdo */
        $pdo = Database::getInstance()->getPdo();
        $sql = 'SELECT id, name FROM project';
        $admin = $user->IsAdmin();
        if (!$admin) {
            $sql .= "
                WHERE id IN (
                    SELECT projectid AS id
                    FROM user2project
                    WHERE userid=:userid
                      AND role > 0
                )
            ";
        }
        $sql .= ' ORDER BY name ASC';

        /** @var \PDOStatement $stmt */
        $stmt = $pdo->prepare($sql);
        if (!$admin) {
            $stmt->bindParam(':userid', $id);
        }
        $stmt->execute();
        $projects = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return is_array($projects) ? $projects : [];
    }

    /** Return if a project exists */
    public function Exists(): bool
    {
        // If no id specify return false
        if (!$this->ProjectId || !$this->UserId) {
            return false;
        }

        $db = Database::getInstance();
        $query = $db->executePreparedSingleRow('
                     SELECT count(*) AS c
                     FROM user2project
                     WHERE userid=? AND projectid=?
                 ', [intval($this->UserId), intval($this->ProjectId)]);

        return intval($query['c']) > 0;
    }

    /** Save the project in the database */
    public function Save(): bool
    {
        if (!$this->ProjectId) {
            echo 'UserProject::Save(): no ProjectId specified';
            return false;
        }

        if (!$this->UserId) {
            echo 'UserProject::Save(): no UserId specified';
            return false;
        }

        $db = Database::getInstance();

        // Check if the project is already
        if ($this->Exists()) {
            // Update the project
            $query = $db->executePrepared('
                         UPDATE user2project
                         SET
                             role=?,
                             emailtype=?,
                             emailcategory=?,
                             emailsuccess=?,
                             emailmissingsites=?
                         WHERE
                             userid=?
                             AND projectid=?
                     ', [
                         $this->Role,
                         $this->EmailType,
                         $this->EmailCategory,
                         $this->EmailSuccess,
                         $this->EmailMissingSites,
                         $this->UserId,
                         $this->ProjectId
                     ]);
            if ($query === false) {
                add_last_sql_error('User2Project Update');
                return false;
            }
        } else {
            // insert

            $query = $db->executePrepared('
                         INSERT INTO user2project (
                             userid,
                             projectid,
                             role,
                             emailtype,
                             emailcategory,
                             emailsuccess,
                             emailmissingsites
                         )
                         VALUES (?, ?, ?, ?, ?, ?, ?)
                     ', [
                         $this->UserId,
                         $this->ProjectId,
                         $this->Role,
                         $this->EmailType,
                         $this->EmailCategory,
                         $this->EmailSuccess,
                         $this->EmailMissingSites
                     ]);
            if ($query === false) {
                add_last_sql_error('User2Project Create');
                return false;
            }
        }
        return true;
    }

    /**
     * Get the users of the project
     *
     * @return array<int>|false
     */
    public function GetUsers(int $role = -1): array|false
    {
        if (!$this->ProjectId) {
            echo 'UserProject GetUsers(): ProjectId not set';
            return false;
        }

        $db = Database::getInstance();

        $sql = '';
        $params = [];
        if ($role != -1) {
            $sql = ' AND role=?';
            $params[] = $role;
        }

        $project = $db->executePrepared("
                       SELECT userid
                       FROM user2project
                       WHERE
                           projectid=?
                           $sql
                   ", array_merge([$this->ProjectId], $params));
        if ($project === false) {
            add_last_sql_error('UserProject GetUsers');
            return false;
        }

        $userids = [];
        foreach ($project as $project_array) {
            $userids[] = intval($project_array['userid']);
        }
        return $userids;
    }

    /** Update the credentials for a project */
    public function UpdateCredentials(array $credentials): bool
    {
        if (!$this->UserId) {
            add_log('UserId not set', 'UserProject UpdateCredentials()', LOG_ERR,
                $this->ProjectId, 0, ModelType::USER, $this->UserId);
            return false;
        }

        // Insert the new credentials
        foreach ($credentials as $credential) {
            $this->AddCredential($credential);
        }

        $db = Database::getInstance();

        // Remove the one that have been removed
        $prepared_array = $db->createPreparedArray(count($credentials));
        $db->executePrepared("
            DELETE FROM user2repository
            WHERE
                userid=?
                AND projectid=?
                AND credential NOT IN $prepared_array
        ", array_merge([$this->UserId, $this->ProjectId], $credentials));
        add_last_sql_error('UserProject UpdateCredentials');
        return true;
    }

    /** Add a credential for a given project */
    public function AddCredential($credential): bool
    {
        if (empty($credential)) {
            return false;
        }

        if (!$this->UserId) {
            add_log('UserId not set', 'UserProject AddCredential()', LOG_ERR,
                $this->ProjectId, 0, ModelType::USER, $this->UserId);
            return false;
        }

        $db = Database::getInstance();

        // Check if the credential exists for all the project or the given project
        $query = $db->executePreparedSingleRow('
                     SELECT COUNT(*) AS c
                     FROM user2repository
                     WHERE
                         userid=?
                         AND (projectid=? OR projectid=0)
                         AND credential=?
                 ', [intval($this->UserId), intval($this->ProjectId), $credential]);
        add_last_sql_error('UserProject AddCredential');

        if (intval($query['c']) === 0) {
            $db->executePrepared('
                INSERT INTO user2repository (userid, projectid, credential)
                VALUES(?, ?, ?)
            ', [intval($this->UserId), intval($this->ProjectId), $credential]);
            add_last_sql_error('UserProject AddCredential');
            return true;
        }
        return false;
    }

    /**
     *  Fill in the information given a projectid and a repository credential.
     *  This function expects the emailtype > 0
     */
    public function FillFromRepositoryCredential(): bool
    {
        if (!$this->ProjectId) {
            add_log('ProjectId not set', 'UserProject FillFromRepositoryCredential()', LOG_ERR,
                $this->ProjectId, 0, ModelType::USER, $this->UserId);
            return false;
        }

        if (!$this->RepositoryCredential) {
            add_log('RepositoryCredential not set', 'UserProject FillFromRepositoryCredential()', LOG_ERR,
                $this->ProjectId, 0, ModelType::USER, $this->UserId);
            return false;
        }

        $db = Database::getInstance();

        $user = $db->executePreparedSingleRow('
                   SELECT up.emailcategory, up.userid, up.emailsuccess
                   FROM user2project AS up, user2repository AS ur
                   WHERE up.projectid=?
                       AND up.userid=ur.userid
                       AND (ur.projectid=0 OR ur.projectid=up.projectid)
                       AND ur.credential=?
                       AND up.emailtype>0
               ', [intval($this->ProjectId), $this->RepositoryCredential]);

        if ($user === false) {
            add_last_sql_error('UserProject FillFromRepositoryCredential');
            return false;
        }

        if (empty($user)) {
            return false;
        }
        $this->EmailCategory = $user['emailcategory'];
        $this->UserId = $user['userid'];
        $this->EmailSuccess = $user['emailsuccess'];
        return true;
    }

    public function FillFromUserId(): bool
    {
        if (!$this->ProjectId) {
            add_log('ProjectId not set', 'UserProject FillFromUserId()', LOG_ERR,
                $this->ProjectId, 0, ModelType::USER, $this->UserId);
            return false;
        }

        if (!$this->UserId) {
            add_log('UserId not set', 'UserProject FillFromUserId()', LOG_ERR,
                $this->ProjectId, 0, ModelType::USER, $this->UserId);
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

    /** Get information about the projects associated with this user. */
    public function GetProjects(): array|false
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
