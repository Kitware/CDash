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
include_once 'models/buildupdatefile.php';
use CDash\Database;

class BuildUpdate
{
    private $Files;
    public $StartTime;
    public $EndTime;
    public $Command;
    public $Type;
    public $Status;
    public $Revision;
    public $PriorRevision;
    public $Path;
    public $BuildId;
    public $Append;
    public $UpdateId;
    public $Errors;
    private $PDO;

    public function __construct()
    {
        $this->Files = array();
        $this->Command = '';
        $this->Append = false;
        $this->DuplicateSQL = '';
        $this->PDO = Database::getInstance()->getPdo();
        global $CDASH_DB_TYPE;
        if ($CDASH_DB_TYPE !== 'pgsql') {
            $this->DuplicateSQL = 'ON DUPLICATE KEY UPDATE buildid=buildid';
        }
    }

    public function AddFile($file)
    {
        $this->Files[] = $file;
    }

    public function GetFiles()
    {
        return $this->Files;
    }

    // Insert the update
    public function Insert()
    {
        if (strlen($this->BuildId) == 0 || !is_numeric($this->BuildId)) {
            echo 'BuildUpdate:Insert BuildId not set';
            return false;
        }

        // Avoid a race condition when parallel processing.
        pdo_begin_transaction();

        $buildid = qnum($this->BuildId);

        // Check if this update already exists.
        $query = pdo_query(
            "SELECT updateid FROM build2update
              WHERE buildid=$buildid FOR UPDATE");
        $exists = pdo_num_rows($query) == 1;
        if ($exists) {
            $query_array = pdo_fetch_array($query);
            $this->UpdateId = $query_array['updateid'];
            $updateid = qnum($this->UpdateId);
        }

        // Remove previous updates
        if ($exists && !$this->Append) {
            // Parent builds share updates with their children.
            // So if this is a parent build remove any build2update rows
            // from the children here.
            pdo_query(
                "DELETE FROM build2update WHERE buildid IN
                (SELECT id FROM build WHERE parentid=$buildid)");

            // If the buildupdate and updatefile are not shared
            // we delete them as well.
            $query = pdo_query(
                "SELECT buildid FROM build2update WHERE updateid=$updateid");
            if (pdo_num_rows($query) == 1) {
                $query = "DELETE FROM buildupdate WHERE id=$updateid";
                if (!pdo_query($query)) {
                    add_last_sql_error('BuildUpdate Delete', 0, $this->BuildId);
                    pdo_rollback();
                    return false;
                }

                $query = "DELETE FROM updatefile WHERE updateid=$updateid";
                if (!pdo_query($query)) {
                    add_last_sql_error('BuildUpdate Delete updatefil', 0, $this->BuildId);
                    pdo_rollback();
                    return false;
                }
            }
            $query = "DELETE FROM build2update WHERE buildid=$buildid";
            if (!pdo_query($query)) {
                add_last_sql_error('Build2Update Delete', 0, $this->BuildId);
                pdo_rollback();
                return false;
            }
            $exists = false;
            $this->UpdateId = '';
            $updateid = '';
        }

        if (!$exists) {
            $this->StartTime = pdo_real_escape_string($this->StartTime);
        }
        $this->EndTime = pdo_real_escape_string($this->EndTime);
        $this->Command = pdo_real_escape_string($this->Command);

        $this->Type = pdo_real_escape_string($this->Type);
        if (strlen($this->Type) > 4) {
            $this->Type = 'NA';
        }

        $this->Status = pdo_real_escape_string($this->Status);
        $this->Revision = pdo_real_escape_string($this->Revision);
        $this->PriorRevision = pdo_real_escape_string($this->PriorRevision);
        $this->Path = pdo_real_escape_string($this->Path);

        $nfiles = count($this->Files);
        $nwarnings = 0;

        foreach ($this->Files as $file) {
            if ($file->Author == 'Local User' && $file->Revision == -1) {
                $nwarnings++;
            }
        }

        if (!$exists) {
            $query =
                "INSERT INTO buildupdate
              (starttime,endtime,command,type,status,nfiles,warnings,
               revision,priorrevision,path)
              VALUES ('$this->StartTime','$this->EndTime','$this->Command',
                      '$this->Type','$this->Status',$nfiles,$nwarnings,
                      '$this->Revision','$this->PriorRevision','$this->Path')";
            if (!pdo_query($query)) {
                add_last_sql_error('BuildUpdate Insert', 0, $this->BuildId);
                pdo_rollback();
                return false;
            }

            $this->UpdateId = pdo_insert_id('buildupdate');
            $updateid = qnum($this->UpdateId);
            $query = "INSERT INTO build2update (buildid,updateid)
              VALUES ($buildid,$updateid)
              $this->DuplicateSQL";

            if (!pdo_query($query)) {
                add_last_sql_error('Build2Update Insert', 0, $this->BuildId);
                pdo_rollback();
                return false;
            }

            // If this is a parent build, make sure that all of its children
            // are also associated with a buildupdate.
            $query = "
        INSERT INTO build2update (buildid,updateid)
        SELECT id, '$this->UpdateId' FROM build
        LEFT JOIN build2update ON build.id = build2update.buildid
        WHERE build2update.buildid IS NULL
        and build.parentid=$buildid";
            if (!pdo_query($query)) {
                add_last_sql_error('BuildUpdate Child Insert', 0, $this->BuildId);
                pdo_rollback();
                return false;
            }
        } else {
            $nwarnings += $this->GetNumberOfWarnings();
            $nfiles += $this->GetNumberOfFiles();

            include 'config/config.php';
            if ($CDASH_DB_TYPE == 'pgsql') {
                // pgsql doesn't have concat...

                $query = "UPDATE buildupdate SET
                  endtime='$this->EndTime'," .
                    "command=command || '$this->Command',
                  status='$this->Status'," .
                    "nfiles='$nfiles',warnings='$nwarnings'" .
                    "WHERE id=$updateid";
            } else {
                $query = "UPDATE buildupdate SET
                  endtime='$this->EndTime',
                  command=CONCAT(command, '$this->Command'),
                  status='$this->Status',
                  nfiles='$nfiles',
                  warnings='$nwarnings'
                      WHERE id=$updateid";
            }

            if (!pdo_query($query)) {
                add_last_sql_error('BuildUpdate Update', 0, $this->BuildId);
                pdo_rollback();
                return false;
            }
        }

        foreach ($this->Files as $file) {
            $file->UpdateId = $this->UpdateId;
            $file->Insert();
        }

        pdo_commit();
        return true;
    }

    /** Get the number of files for an update */
    public function GetNumberOfFiles()
    {
        if (!$this->UpdateId) {
            echo 'BuildUpdate::GetNumberOfFiles(): Id not set';
            return false;
        }

        $files = pdo_query('SELECT nfiles FROM buildupdate WHERE id=' . qnum($this->UpdateId));
        if (!$files) {
            add_last_sql_error('Build:GetNumberOfFiles', 0, $this->BuildId);
            return 0;
        }

        $files_array = pdo_fetch_array($files);
        if ($files_array[0] == -1) {
            return 0;
        }
        return $files_array[0];
    }

    /**
     * Returns the update for the buildid
     *
     * @param int $fetchType
     * @return bool|mixed
     */
    public function GetUpdateForBuild($fetchType = PDO::FETCH_ASSOC)
    {
        if (!$this->BuildId) {
            echo 'BuildUpdate::GetUpdateStatusForBuild(): BuildId not set';
            return false;
        }

        $sql = "
            SELECT
                A.*,
                B.buildid
            FROM
                buildupdate A,
                build2update B
            WHERE B.updateid=A.id
              AND B.buildid=:buildid
        ";

        $query = $this->PDO->prepare($sql);
        $query->bindParam(':buildid', $this->BuildId);

        return $query->fetch($fetchType);
    }

    /** Get the number of warnings for an update */
    public function GetNumberOfWarnings()
    {
        if (!$this->UpdateId) {
            echo 'BuildUpdate::GetNumberOfWarnings(): Id not set';
            return false;
        }

        $warnings = pdo_query('SELECT warnings FROM buildupdate WHERE id=' . qnum($this->UpdateId));
        if (!$warnings) {
            add_last_sql_error('Build:GetNumberOfWarnings', 0, $this->BuildId);
            return 0;
        }

        $warnings_array = pdo_fetch_array($warnings);
        if ($warnings_array[0] == -1) {
            return 0;
        }
        return $warnings_array[0];
    }

    /** Get the number of errors for a build */
    public function GetNumberOfErrors()
    {
        if (!$this->BuildId) {
            echo 'BuildUpdate::GetNumberOfErrors(): BuildId not set';
            return false;
        }

        $builderror = pdo_query('SELECT status FROM buildupdate AS u, build2update AS b2u WHERE u.id=b2u.updateid AND b2u.buildid=' . qnum($this->BuildId));
        $updatestatus_array = pdo_fetch_array($builderror);

        if (strlen($updatestatus_array['status']) > 0 &&
            $updatestatus_array['status'] != '0'
        ) {
            return 1;
        }
        return 0;
    }

    /** Associate a buildupdate to a build. */
    public function AssociateBuild($siteid, $name, $stamp)
    {
        if (!$this->BuildId) {
            echo 'BuildUpdate::AssociateBuild(): BuildId not set';
            return false;
        }

        // If we already have something in the databse we return
        $query = pdo_query('SELECT updateid FROM build2update WHERE buildid=' . qnum($this->BuildId));
        if (pdo_num_rows($query) > 0) {
            return true;
        }

        // Find the update id from a similar build
        $query = pdo_query("SELECT updateid FROM build2update AS b2u, build AS b
                        WHERE b.id=b2u.buildid AND b.stamp='" . $stamp . "'
                          AND b.siteid=" . qnum($siteid) . " AND b.name='" . $name . "'
                          AND b.id!=" . qnum($this->BuildId));
        if (!$query) {
            add_last_sql_error('BuildUpdate AssociateBuild', 0, $this->BuildId);
            return false;
        }
        if (pdo_num_rows($query) > 0) {
            $query_array = pdo_fetch_array($query);
            $this->updateId = $query_array['updateid'];

            pdo_query('INSERT INTO build2update (buildid,updateid) VALUES
                  (' . qnum($this->BuildId) . ',' . qnum($this->updateId) . ")
                  $this->DuplicateSQL");
            add_last_sql_error('BuildUpdate AssociateBuild', 0, $this->BuildId);

            // check if this build's parent also needs to be associated with
            // this update.
            $parent = pdo_single_row_query(
                'SELECT parentid FROM build WHERE id=' . qnum($this->BuildId));
            if ($parent && array_key_exists('parentid', $parent)) {
                $parentid = $parent['parentid'];
                if ($parentid < 1) {
                    return true;
                }

                $query = pdo_query(
                    'SELECT updateid FROM build2update WHERE buildid=' . qnum($parentid));
                if (pdo_num_rows($query) > 0) {
                    return true;
                }

                pdo_query('INSERT INTO build2update (buildid,updateid) VALUES
                      (' . qnum($parentid) . ',' . qnum($this->updateId) . ")
                      $this->DuplicateSQL");
                add_last_sql_error('BuildUpdate AssociateBuild', 0, $parentid);
            }
        }
        return true;
    }

    /** Update a child build so that it shares the parent's updates.
     *  This function does not change the data model unless the parent
     * has an update and the child does not. **/
    public static function AssignUpdateToChild($childid, $parentid)
    {
        $childid = qnum($childid);
        $parentid = qnum($parentid);

        // Make sure the child does not already have an update.
        $result = pdo_query(
            "SELECT updateid FROM build2update WHERE buildid=$childid");
        if (pdo_num_rows($result) > 0) {
            return;
        }

        // Get the parent's update.
        $result = pdo_query(
            "SELECT updateid FROM build2update WHERE buildid=$parentid");
        if (pdo_num_rows($result) < 1) {
            return;
        }
        $row = pdo_fetch_array($result);
        $updateid = qnum($row['updateid']);

        // Assign the parent's update to the child.
        $query = "INSERT INTO build2update (buildid, updateid)
          VALUES ($childid, $updateid)";
        if (!pdo_query($query)) {
            add_last_sql_error('AssignUpdateToChild', 0, $childid);
        }
    }

    public function FillFromBuildId()
    {
        if (!$this->BuildId) {
            return false;
        }

        $stmt = $this->PDO->prepare(
            'SELECT bu.* FROM buildupdate bu
            JOIN build2update b2u ON bu.id = b2u.updateid
            WHERE b2u.buildid = ?');
        if (!pdo_execute($stmt, [$this->BuildId])) {
            return false;
        }

        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        $this->UpdateId = $row['id'];
        $this->StartTime = $row['starttime'];
        $this->EndTime = $row['endtime'];
        $this->Command = $row['command'];
        $this->Type = $row['type'];
        $this->Status = $row['status'];
        $this->Revision = $row['revision'];
        $this->PriorRevision = $row['priorrevision'];
        $this->Path = $row['path'];

        // Get updated files too.
        $stmt = $this->PDO->prepare(
            "SELECT uf.* FROM updatefile uf
            JOIN build2update b2u ON uf.updateid = b2u.updateid
            WHERE b2u.buildid = ?
            ORDER BY REVERSE(RIGHT(REVERSE(filename),LOCATE('/',REVERSE(filename))))");
        pdo_execute($stmt, [$this->BuildId]);
        while ($row = $stmt->fetch()) {
            $file = new BuildUpdateFile();
            $file->Filename = $row['filename'];
            $file->CheckinDate = $row['checkindate'];
            $file->Author = $row['author'];
            $file->Email = $row['email'];
            $file->Committer = $row['committer'];
            $file->CommitterEmail = $row['committeremail'];
            $file->Log = $row['log'];
            $file->Revision = $row['revision'];
            $file->PriorRevision = $row['priorrevision'];
            $file->Status = $row['status'];
            $file->UpdateId = $row['updateid'];
            $this->AddFile($file);
        }

        return true;
    }
}
