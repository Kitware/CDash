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
        $this->Fullpath = '';
        $this->ProjectId = 0;
    }

    /** Return if exists */
    public function Exists()
    {
        $fileid = $this->GetId();

        if ($fileid == 0) {
            return false;
        }

        $query = pdo_query('SELECT count(*) AS c FROM coveragefile2user WHERE userid=' . qnum($this->UserId) . '
                        AND fileid=' . qnum($fileid));
        $query_array = pdo_fetch_array($query);
        if ($query_array['c'] > 0) {
            return true;
        }
        return false;
    }

    /** Insert the new user */
    public function Insert()
    {
        if (!isset($this->UserId) || $this->UserId < 1) {
            echo 'CoverageFile2User:Insert: UserId not set';
            return false;
        }

        if ($this->FullPath == '' || $this->ProjectId < 1) {
            echo 'CoverageFile2User:Insert: FullPath or ProjectId not set';
            return false;
        }

        // Check if is already in the database
        if (!$this->Exists()) {
            $this->FileId = $this->GetId();

            if ($this->FileId == 0) {
                $query = 'INSERT INTO coveragefilepriority (projectid,fullpath,priority)
                  VALUES (' . qnum($this->ProjectId) . ",'" . $this->FullPath . "',0)";
                if (!pdo_query($query)) {
                    add_last_sql_error('CoverageFile2User:Insert');
                    return false;
                }
                $this->FileId = pdo_insert_id('coveragefilepriority');
            }

            // Find the new position
            $query = pdo_query('SELECT count(*) AS c FROM coveragefile2user WHERE fileid=' . qnum($this->FileId));
            $query_array = pdo_fetch_array($query);
            $position = $query_array['c'] + 1;

            $query = 'INSERT INTO coveragefile2user (userid,fileid,position)
                VALUES (' . qnum($this->UserId) . ',' . qnum($this->FileId) . ',' . qnum($position) . ')';
            if (!pdo_query($query)) {
                add_last_sql_error('CoverageFile2User:Insert');
                return false;
            }
            return true;
        }
        return false;
    } // function Insert

    /** Remove authors */
    public function RemoveAuthors()
    {
        if ($this->FullPath == '' || $this->ProjectId < 1) {
            echo 'CoverageFile2User:RemoveAuthors: FullPath or ProjectId not set';
            return false;
        }

        $query = 'DELETE FROM coveragefile2user WHERE fileid=' . qnum($this->GetId());
        if (!pdo_query($query)) {
            add_last_sql_error('CoverageFile2User:RemoveAuthors');
            echo $query;
            return false;
        }
    }

    /** Remove the new user */
    public function Remove()
    {
        if (!isset($this->UserId) || $this->UserId < 1) {
            return false;
        }
        if (!isset($this->FileId) || $this->FileId < 1) {
            return false;
        }

        $query = 'DELETE FROM coveragefile2user WHERE userid=' . qnum($this->UserId) . '
                AND fileid=' . qnum($this->FileId);
        if (!pdo_query($query)) {
            add_last_sql_error('CoverageFile2User:Remove');
            return false;
        }

        $this->FixPosition();
        return true;
    }

    /** Fix the position given a file */
    private function FixPosition()
    {
        if (!isset($this->FileId) || $this->FileId < 1) {
            return false;
        }

        $query = pdo_query('SELECT userid FROM coveragefile2user WHERE fileid=' . qnum($this->FileId) . ' ORDER BY position ASC');
        if (!$query) {
            add_last_sql_error('CoverageFile2User:FixPosition');
            return false;
        }

        $position = 1;
        while ($query_array = pdo_fetch_array($query)) {
            pdo_query('UPDATE coveragefile2user SET position=' . qnum($position) . ' WHERE fileid=' . qnum($this->FileId) . '
                 AND userid=' . qnum($query_array['userid']));
            $position++;
        }
        return true;
    }

    /** Get authors of a file */
    public function GetAuthors()
    {
        if ($this->FullPath == '' || $this->ProjectId < 1) {
            echo 'CoverageFile2User:GetAuthors: FullPath or ProjectId not set';
            return false;
        }
        $query = pdo_query("SELECT userid FROM coveragefile2user,coveragefilepriority WHERE
                       coveragefile2user.fileid=coveragefilepriority.id AND
                       coveragefilepriority.fullpath='" . $this->FullPath . "' AND coveragefilepriority.projectid=" . qnum($this->ProjectId) . ' ORDER BY position ASC');
        if (!$query) {
            add_last_sql_error('CoverageFile2User:GetAuthors');
            return false;
        }
        $authorids = array();
        while ($query_array = pdo_fetch_array($query)) {
            $authorids[] = $query_array['userid'];
        }
        return $authorids;
    }

    /** Get id of a file */
    public function GetId()
    {
        if ($this->FullPath == '' || $this->ProjectId < 1) {
            echo 'CoverageFile2User:GetId: FullPath or ProjectId not set';
            return false;
        }
        $query = pdo_query("SELECT id FROM coveragefilepriority WHERE
                       coveragefilepriority.fullpath='" . $this->FullPath . "' AND coveragefilepriority.projectid=" . qnum($this->ProjectId));
        if (!$query) {
            add_last_sql_error('CoverageFile2User:GetId');
            return false;
        }
        if (pdo_num_rows($query) == 0) {
            return 0;
        }
        $query_array = pdo_fetch_array($query);
        return $query_array['id'];
    }

    /** Get files given an author */
    public function GetFiles()
    {
        if (!isset($this->UserId) || $this->UserId < 1) {
            echo 'CoverageFile2User:GetFiles: UserId not set';
            return false;
        }
        $query = pdo_query('SELECT fileid FROM coveragefile2user WHERE userid=' . qnum($this->UserId));
        if (!$query) {
            add_last_sql_error('CoverageFile2User:GetFiles');
            return false;
        }

        $fileids = array();
        while ($query_array = pdo_fetch_array($query)) {
            $fileids[] = $query_array['fileid'];
        }
        return $fileids;
    }

    /** Return the actualy coverage file id */
    public function GetCoverageFileId($buildid)
    {
        if ($this->FileId == 0) {
            echo 'CoverageFile2User:GetCoverageFileId: FileId not set';
            return false;
        }

        $query = pdo_query('SELECT coveragefile.id AS id FROM coveragefile,coveragefilepriority,coverage WHERE
                        coveragefilepriority.id=' . qnum($this->FileId) . '
                        AND coverage.buildid=' . qnum($buildid) . '
                        AND coverage.fileid=coveragefile.id
                        AND coveragefilepriority.fullpath=coveragefile.fullpath');
        if (!$query) {
            add_last_sql_error('CoverageFile2User:GetCoverageFileId');
            return false;
        }

        $query_array = pdo_fetch_array($query);
        return $query_array['id'];
    }

    /** Get the list of authors for the project */
    public function GetUsersFromProject()
    {
        if (!isset($this->ProjectId) || $this->ProjectId < 1) {
            echo 'CoverageFile2User:GetUsersFromProject: projectid not valid';
            return false;
        }

        $query = pdo_query('SELECT DISTINCT userid FROM coveragefile2user,coveragefilepriority WHERE
                        coveragefilepriority.id=coveragefile2user.fileid
                        AND coveragefilepriority.projectid=' . qnum($this->ProjectId));
        if (!$query) {
            add_last_sql_error('CoverageFile2User:GetUsersFromProject');
            return false;
        }
        $userids = array();
        while ($query_array = pdo_fetch_array($query)) {
            $userids[] = $query_array['userid'];
        }
        return $userids;
    }

    /** Assign the last author */
    public function AssignLastAuthor($buildid, $beginUTCTime, $currentUTCTime)
    {
        if (!isset($this->ProjectId) || $this->ProjectId < 1) {
            echo 'CoverageFile2User:AssignLastAuthor: ProjectId not set';
            return false;
        }

        if ($buildid == 0) {
            echo 'CoverageFile2User:AssignLastAuthor: buildid not valid';
            return false;
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
            $userids = $DailyUpdate->GetAuthors($fullpath, true); // only last

            foreach ($userids as $userid) {
                $this->FullPath = $fullpath;
                $this->UserId = $userid;
                $this->Insert();
            }
        }
        return true;
    }

    /** Assign all author author */
    public function AssignAllAuthors($buildid, $beginUTCTime, $currentUTCTime)
    {
        if (!isset($this->ProjectId) || $this->ProjectId < 1) {
            echo 'CoverageFile2User:AssignLastAuthor: ProjectId not set';
            return false;
        }

        if ($buildid == 0) {
            echo 'CoverageFile2User:AssignLastAuthor: buildid not valid';
            return false;
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
            $userids = $DailyUpdate->GetAuthors($fullpath);

            foreach ($userids as $userid) {
                $this->FullPath = $fullpath;
                $this->UserId = $userid;
                $this->Insert();
            }
        }
        return true;
    }

    // Function get the priority to a file
    public function GetPriority()
    {
        if ($this->FullPath == '' || $this->ProjectId < 1) {
            echo 'CoverageFile2User:GetPriority: FullPath or ProjectId not set';
            return false;
        }

        $query = pdo_query("SELECT priority FROM coveragefilepriority WHERE fullpath='" . $this->FullPath . "' AND projectid=" . qnum($this->ProjectId));
        if (!$query) {
            add_last_sql_error('CoverageFile2User:GetPriority');
            return false;
        }

        if (pdo_num_rows($query) == 0) {
            return 0;
        }
        $query_array = pdo_fetch_array($query);
        return $query_array[0];
    }

    // Function set the priority to a file
    public function SetPriority($priority)
    {
        if ($this->ProjectId == 0) {
            echo 'CoverageFile2User:SetPriority:ProjectId not set';
            return false;
        }
        if ($this->FullPath == '') {
            echo 'CoverageFile2User:SetPriority:FullPath not set';
            return false;
        }
        $query = pdo_query("SELECT count(*) FROM coveragefilepriority WHERE FullPath='" . $this->FullPath . "'");
        if (!$query) {
            add_last_sql_error('CoverageFile2User:SetPriority');
            return false;
        }

        $sql = '';
        $query_array = pdo_fetch_array($query);
        if ($query_array[0] == 0) {
            $sql = 'INSERT INTO coveragefilepriority (projectid,priority,fullpath) VALUES (' . qnum($this->ProjectId) . ',' . qnum($priority) . ",'" . $this->FullPath . "')";
        } else {
            $sql = 'UPDATE coveragefilepriority set priority=' . qnum($priority) . " WHERE fullpath='" . $this->FullPath . "' AND projectid=" . qnum($this->ProjectId);
        }

        $query = pdo_query($sql);
        if (!$query) {
            add_last_sql_error('CoverageFile2User:SetPriority');
            return false;
        }
        return true;
    }
}
