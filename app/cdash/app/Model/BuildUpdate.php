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

use CDash\Config;
use CDash\Database;
use PDO;

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
        $this->PDO = Database::getInstance()->getPdo();
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
        return \DB::transaction(function () {
            // Check if this update already exists.
            $build2update_row =
            \DB::table('build2update')
                ->where('buildid', $this->BuildId)
                ->lockForUpdate()
                ->first();
            $exists = false;
            if ($build2update_row) {
                $exists = true;
                $this->UpdateId = $build2update_row->updateid;
            }

            // Remove previous updates
            if ($exists && !$this->Append) {
                // Parent builds share updates with their children.
                // So if this is a parent build remove any build2update rows
                // from the children here.
                $child_buildids = \DB::table('build')
                    ->where('parentid', $this->BuildId)
                    ->pluck('id');
                if (!empty($child_buildids)) {
                    \DB::table('build2update')
                        ->whereIn('buildid', $child_buildids)
                        ->delete();
                }

                // If the buildupdate and updatefile are not shared
                // we delete them as well.
                $num_shared_updates = \DB::table('build2update')
                    ->where('updateid', $this->UpdateId)
                    ->count();

                if ($num_shared_updates === 1) {
                    \DB::table('buildupdate')
                        ->where('id', $this->UpdateId)
                        ->delete();
                    \DB::table('updatefile')
                        ->where('updateid', $this->UpdateId)
                        ->delete();
                }
                \DB::table('build2update')
                    ->where('buildid', $this->BuildId)
                    ->delete();
                $exists = false;
                $this->UpdateId = '';
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
                $this->UpdateId = \DB::table('buildupdate')->insertGetId([
                    'starttime' => $this->StartTime,
                    'endtime' => $this->EndTime,
                    'command' => $this->Command,
                    'type' => $this->Type,
                    'status' => $this->Status,
                    'nfiles' => $nfiles,
                    'warnings' => $nwarnings,
                    'revision' => $this->Revision,
                    'priorrevision' => $this->PriorRevision,
                    'path' => $this->Path,
                ]);

                \DB::table('build2update')->insertOrIgnore([
                    'buildid' => $this->BuildId,
                    'updateid' => $this->UpdateId,
                ]);

                // If this is a parent build, make sure that all of its children
                // are also associated with a buildupdate.
                $rows_to_insert = [];
                $children_needing_buildupdates = \DB::table('build')
                    ->leftJoin('build2update', 'build.id', '=', 'build2update.buildid')
                    ->where('build.parentid', $this->BuildId)
                    ->whereNull('build2update.buildid')
                    ->pluck('id');
                foreach ($children_needing_buildupdates as $child_buildid) {
                    $rows_to_insert[] = ['buildid' => $child_buildid, 'updateid' => $this->UpdateId];
                }
                if (!empty($rows_to_insert)) {
                    \DB::table('build2update')->insert($rows_to_insert);
                }
            } else {
                $nwarnings += $this->GetNumberOfWarnings();
                $nfiles += $this->GetNumberOfFiles();

                if (config('database.default') == 'pgsql') {
                    // pgsql doesn't have concat...
                    \DB::table('buildupdate')
                        ->where('id', $this->UpdateId)
                        ->update([
                            'endtime' => $this->EndTime,
                            'status' => $this->Status,
                            'command' => \DB::raw("command || '$this->Command'"),
                            'nfiles' => $nfiles,
                            'warnings' => $nwarnings,
                        ]);
                } else {
                    \DB::table('buildupdate')
                        ->where('id', $this->UpdateId)
                        ->update([
                            'endtime' => $this->EndTime,
                            'status' => $this->Status,
                            'command' => \DB::raw("CONCAT(command, '$this->Command')"),
                            'nfiles' => $nfiles,
                            'warnings' => $nwarnings,
                        ]);
                }
            }

            foreach ($this->Files as $file) {
                $file->UpdateId = $this->UpdateId;
                $file->Insert();
            }

            return true;
        }, 5);
    }

    /** Get the number of files for an update */
    public function GetNumberOfFiles()
    {
        if (!$this->UpdateId) {
            echo 'BuildUpdate::GetNumberOfFiles(): Id not set';
            return false;
        }

        $row = \DB::table('buildupdate')
            ->where('id', $this->UpdateId)
            ->first();
        if ($row) {
            return $row->nfiles;
        }
        return 0;
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
            return 0;
        }

        $row = \DB::table('buildupdate')
            ->where('id', $this->UpdateId)
            ->first();
        if ($row) {
            return $row->warnings;
        }
        return 0;
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

        if (is_array($updatestatus_array) &&
                strlen($updatestatus_array['status']) > 0 &&
                $updatestatus_array['status'] != '0') {
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
        if (\DB::table('build2update')->where('buildid', $this->BuildId)->exists()) {
            return true;
        }

        // Find the update id from a similar build
        $similar_b2u_row = \DB::table('build2update')
            ->join('build', 'build2update.buildid', '=', 'build.id')
            ->where('build.stamp', '=', $stamp)
            ->where('build.siteid', '=', $siteid)
            ->where('build.name', '=', $name)
            ->where('build.id', '!=', $this->BuildId)
            ->first();
        if (!$similar_b2u_row) {
            return true;
        }

        $this->updateId = $similar_b2u_row->updateid;
        \DB::table('build2update')->insertOrIgnore([
            'buildid' => $this->BuildId,
            'updateid' => $this->updateId,
        ]);

        // check if this build's parent also needs to be associated with
        // this update.
        $build_row = \DB::table('build')->where('id', '=', $this->BuildId)->first();
        if (!$build_row) {
            return false;
        }
        if ($build_row->parentid < 1) {
            return true;
        }
        $parentid = $build_row->parentid;
        if (!\DB::table('build2update')->where('buildid', '=', $parentid)->exists()) {
            \DB::table('build2update')->insertOrIgnore([
                'buildid' => $parentid,
                'updateid' => $this->updateId,
            ]);
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

    /**
     * Returns a self referencing URI for the current BuildUpdate.
     *
     * @return string
     */
    public function GetUrlForSelf()
    {
        $config = Config::getInstance();
        return "{$config->getBaseUrl()}/viewUpdate.php?buildid={$this->BuildId}";
    }
}
