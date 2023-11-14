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
use Illuminate\Support\Facades\DB;

/** Coverage file to users */
class CoverageFile2User
{
    public $UserId;
    public $FileId;
    public $Priority;
    public $FullPath;
    public $ProjectId;

    /** Constructor */
    public function __construct()
    {
        $this->UserId = 0;
        $this->FileId = 0;
        $this->Priority = 0;
        $this->FullPath = '';
        $this->ProjectId = 0;
    }

    /** Return if exists */
    public function Exists(): bool
    {
        $fileid = $this->GetId();

        if ($fileid == 0) {
            return false;
        }

        $db = Database::getInstance();
        $query_array = $db->executePreparedSingleRow('
                           SELECT count(*) AS c
                           FROM coveragefile2user
                           WHERE userid=? AND fileid=?
                       ', [intval($this->UserId), intval($fileid)]);
        if (intval($query_array['c']) > 0) {
            return true;
        }
        return false;
    }

    /** Insert the new user */
    public function Insert(): bool
    {
        if (!isset($this->UserId) || $this->UserId < 1) {
            abort(500, 'CoverageFile2User:Insert: UserId not set');
        }

        if ($this->FullPath == '' || $this->ProjectId < 1) {
            abort(500, 'CoverageFile2User:Insert: FullPath or ProjectId not set');
        }

        // Check if is already in the database
        if (!$this->Exists()) {
            $this->FileId = $this->GetId();
            $db = Database::getInstance();

            if ($this->FileId == 0) {
                $insert_result = $db->executePrepared('
                                     INSERT INTO coveragefilepriority (projectid, fullpath, priority)
                                     VALUES (?, ?, 0)
                                 ', [intval($this->ProjectId), $this->FullPath]);
                if ($insert_result === false) {
                    add_last_sql_error('CoverageFile2User:Insert');
                    return false;
                }
                $this->FileId = intval(pdo_insert_id('coveragefilepriority'));
            }

            // Find the new position
            $query_array = $db->executePreparedSingleRow('
                               SELECT count(*) AS c
                               FROM coveragefile2user
                               WHERE fileid=?
                           ', [intval($this->FileId)]);
            $position = intval($query_array['c']) + 1;

            $insert_result = $db->executePrepared('
                                 INSERT INTO coveragefile2user (userid, fileid, position)
                                 VALUES (?, ?, ?)
                             ', [intval($this->UserId), intval($this->FileId), $position]);
            if ($insert_result === false) {
                add_last_sql_error('CoverageFile2User:Insert');
                return false;
            }
            return true;
        }
        return false;
    } // function Insert

    /** Remove authors */
    public function RemoveAuthors(): void
    {
        if ($this->FullPath == '' || $this->ProjectId < 1) {
            abort(500, 'CoverageFile2User:RemoveAuthors: FullPath or ProjectId not set');
        }
        DB::delete('DELETE FROM coveragefile2user WHERE fileid = ?', [$this->GetId()]);
    }

    /** Remove the new user */
    public function Remove(): void
    {
        if (!isset($this->UserId) || $this->UserId < 1) {
            abort(500, 'Invalid UserId');
        }
        if (!isset($this->FileId) || $this->FileId < 1) {
            abort(500, 'Invalid FileId');
        }

        DB::delete('
            DELETE FROM coveragefile2user
            WHERE
                userid=?
                AND fileid=?
        ', [$this->UserId, $this->FileId]);

        $this->FixPosition();
    }

    /** Fix the position given a file */
    private function FixPosition(): bool
    {
        if (!isset($this->FileId) || $this->FileId < 1) {
            return false;
        }

        $db = Database::getInstance();
        $query_result = $db->executePrepared('
                            SELECT userid FROM coveragefile2user
                            WHERE fileid=?
                            ORDER BY position ASC
                        ', [intval($this->FileId)]);
        if ($query_result === false) {
            add_last_sql_error('CoverageFile2User:FixPosition');
            return false;
        }

        $position = 1;
        foreach ($query_result as $query_array) {
            // TODO: (williamjallen) Optimize this loop to execute a constant number of queries
            $db->executePrepared('
                UPDATE coveragefile2user
                SET position=?
                WHERE fileid=? AND userid=?
            ', [$position, intval($this->FileId), intval($query_array['userid'])]);
            $position++;
        }
        return true;
    }

    /**
     * Get authors of a file
     *
     * @return array<int>|false
     */
    public function GetAuthors(): array|false
    {
        if ($this->FullPath == '' || $this->ProjectId < 1) {
            abort(500, 'CoverageFile2User:GetAuthors: FullPath or ProjectId not set');
        }

        $db = Database::getInstance();
        $query_result = $db->executePrepared('
                            SELECT userid
                            FROM coveragefile2user, coveragefilepriority
                            WHERE
                                coveragefile2user.fileid=coveragefilepriority.id
                                AND coveragefilepriority.fullpath=?
                                AND coveragefilepriority.projectid=?
                            ORDER BY position ASC
                        ', [$this->FullPath, $this->ProjectId]);
        if ($query_result === false) {
            add_last_sql_error('CoverageFile2User:GetAuthors');
            return false;
        }
        $authorids = [];
        foreach ($query_result as $query_array) {
            $authorids[] = intval($query_array['userid']);
        }
        return $authorids;
    }

    /** Get id of a file */
    public function GetId(): int|false
    {
        if ($this->FullPath == '' || $this->ProjectId < 1) {
            abort(500, 'CoverageFile2User:GetId: FullPath or ProjectId not set');
        }

        $db = Database::getInstance();
        $query_result = $db->executePreparedSingleRow('
                            SELECT id
                            FROM coveragefilepriority
                            WHERE
                                coveragefilepriority.fullpath=?
                                AND coveragefilepriority.projectid=?
                        ', [$this->FullPath, intval($this->ProjectId)]);
        if ($query_result === false) {
            add_last_sql_error('CoverageFile2User:GetId');
            return false;
        }
        if (empty($query_result)) {
            return 0;
        }
        return intval($query_result['id']);
    }

    /**
     * Get files given an author
     *
     *@return array<int>|false
     */
    public function GetFiles(): array|false
    {
        if (!isset($this->UserId) || $this->UserId < 1) {
            abort(500, 'CoverageFile2User:GetFiles: UserId not set');
        }

        $db = Database::getInstance();
        $query_result = $db->executePrepared('SELECT fileid FROM coveragefile2user WHERE userid=?', [$this->UserId]);
        if (empty($query_result)) {
            add_last_sql_error('CoverageFile2User:GetFiles');
            return false;
        }

        $fileids = [];
        foreach ($query_result as $query_array) {
            $fileids[] = intval($query_array['fileid']);
        }
        return $fileids;
    }

    /** Return the actual coverage file id */
    public function GetCoverageFileId($buildid): int|false
    {
        if ($this->FileId == 0) {
            abort(500, 'CoverageFile2User:GetCoverageFileId: FileId not set');
        }

        $db = Database::getInstance();
        $query_result = $db->executePreparedSingleRow('
                            SELECT coveragefile.id AS id
                            FROM coveragefile, coveragefilepriority, coverage
                            WHERE
                                coveragefilepriority.id=?
                                AND coverage.buildid=?
                                AND coverage.fileid=coveragefile.id
                                AND coveragefilepriority.fullpath=coveragefile.fullpath
                            ', [intval($this->FileId), intval($buildid)]);
        if (empty($query_result)) {
            add_last_sql_error('CoverageFile2User:GetCoverageFileId');
            return false;
        }

        return intval($query_result['id']);
    }

    public function AssignAuthors(int $buildid, bool $onlylast = false): bool
    {
        if (!isset($this->ProjectId) || $this->ProjectId < 1) {
            abort(500, 'CoverageFile2User:AssignLastAuthor: ProjectId not set');
        }

        if ($buildid === 0) {
            abort(500, 'CoverageFile2User:AssignLastAuthor: buildid not valid');
        }

        // Find the files associated with the build
        $Coverage = new Coverage();
        $Coverage->BuildId = $buildid;
        $fileIds = $Coverage->GetFiles();
        foreach ($fileIds as $fileid) {
            $CoverageFile = new CoverageFile();
            $CoverageFile->Id = $fileid;
            $fullpath = $CoverageFile->GetPath();

            $DailyUpdate = new DailyUpdate();
            $DailyUpdate->ProjectId = $this->ProjectId;
            $userids = $DailyUpdate->GetAuthors($fullpath, $onlylast);

            foreach ($userids as $userid) {
                $this->FullPath = $fullpath;
                $this->UserId = $userid;
                $this->Insert();
            }
        }
        return true;
    }

    /** Function get the priority to a file */
    public function GetPriority(): int|false
    {
        if ($this->FullPath == '' || $this->ProjectId < 1) {
            abort(500, 'CoverageFile2User:GetPriority: FullPath or ProjectId not set');
        }

        $db = Database::getInstance();
        $query_result = $db->executePreparedSingleRow('
                            SELECT priority
                            FROM coveragefilepriority
                            WHERE fullpath=? AND projectid=?
                        ', [$this->FullPath, intval($this->ProjectId)]);
        if ($query_result === false) {
            add_last_sql_error('CoverageFile2User:GetPriority');
            return false;
        }
        return empty($query_result) ? 0 : intval($query_result['priority']);
    }

    /** Function set the priority to a file */
    public function SetPriority(int $priority): bool
    {
        if ($this->ProjectId == 0) {
            abort(500, 'CoverageFile2User:SetPriority:ProjectId not set');
        }
        if ($this->FullPath == '') {
            abort(500, 'CoverageFile2User:SetPriority:FullPath not set');
        }
        $db = Database::getInstance();
        $query_result = $db->executePreparedSingleRow('
                            SELECT count(*) AS c
                            FROM coveragefilepriority
                            WHERE FullPath=?
                        ', [$this->FullPath]);
        if ($query_result === false) {
            add_last_sql_error('CoverageFile2User:SetPriority');
            return false;
        }

        if (intval($query_result['c']) === 0) {
            $query_result = $db->executePrepared('
                                INSERT INTO coveragefilepriority (projectid, priority, fullpath)
                                VALUES (?, ?, ?)
                            ', [$this->ProjectId, $priority, $this->FullPath]);
        } else {
            $query_result = $db->executePrepared('
                                UPDATE coveragefilepriority
                                SET priority=?
                                WHERE fullpath=? AND projectid=?
                            ', [$priority, $this->FullPath, $this->ProjectId]);
        }

        if ($query_result === false) {
            add_last_sql_error('CoverageFile2User:SetPriority');
            return false;
        }
        return true;
    }
}
