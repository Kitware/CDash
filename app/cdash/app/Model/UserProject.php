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

use App\Models\User;
use CDash\Database;
use Illuminate\Support\Facades\Log;

class UserProject
{
    public $Role;
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
        $this->PDO = Database::getInstance()->getPdo();
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
            abort(500, 'UserProject::Save(): no ProjectId specified');
        }

        if (!$this->UserId) {
            abort(500, 'UserProject::Save(): no UserId specified');
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
                $this->ProjectId,
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
                $this->EmailMissingSites,
            ]);
            if ($query === false) {
                add_last_sql_error('User2Project Create');
                return false;
            }
        }
        return true;
    }

    public function FillFromUserId(): bool
    {
        if (!$this->ProjectId) {
            Log::error('ProjectId not set', [
                'method' => 'UserProject FillFromUserId()',
                'projectid' => $this->ProjectId,
                'userid' => $this->UserId,
            ]);
            return false;
        }

        if (!$this->UserId) {
            Log::error('UserId not set', [
                'method' => 'UserProject FillFromUserId()',
                'projectid' => $this->ProjectId,
            ]);
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
            abort(500, 'UserProject GetProjects(): UserId not set');
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
