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

class DailyUpdate
{
    public $Id;
    public $Date;
    public $Command;
    public $Type;
    public $Status;
    public $ProjectId;

    /** Get all the authors of a file */
    public function GetAuthors($filename, $onlylast = false)
    {
        if (!$this->ProjectId) {
            echo 'DailyUpdate::GetAuthors(): ProjectId is not set<br>';
            return false;
        }

        // Check if the note already exists
        $filename = pdo_real_escape_string($filename);

        // Remove
        if (substr($filename, 0, 2) == './') {
            $filename = substr($filename, 2);
        }

        $sql = '';
        if ($onlylast) {
            $sql = ' ORDER BY dailyupdate.id DESC LIMIT 1';
        }

        $query = pdo_query('SELECT DISTINCT up.userid,dailyupdate.id FROM user2project AS up,user2repository AS ur,dailyupdatefile,dailyupdate
                        WHERE dailyupdatefile.dailyupdateid=dailyupdate.id
                        AND dailyupdate.projectid=up.projectid
                        AND ur.credential=dailyupdatefile.author
                        AND up.projectid=' . qnum($this->ProjectId) . '
                        AND up.userid=ur.userid
                        AND (ur.projectid=0 OR ur.projectid=' . qnum($this->ProjectId) . ")
                        AND dailyupdatefile.filename LIKE '%" . $filename . "'" . $sql);

        if (!$query) {
            add_last_sql_error('DailyUpdate GetAuthors', $this->ProjectId);
            return false;
        }

        $authorids = array();
        while ($query_array = pdo_fetch_array($query)) {
            $authorids[] = $query_array['userid'];
        }
        return $authorids;
    }
}
