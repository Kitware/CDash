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

class DailyUpdate
{
    public $Id;
    public $Date;
    public $Command;
    public $Type;
    public $Status;
    public $ProjectId;

    /**
     * Get all the authors of a file
     *
     * @return array<int>|false
     */
    public function GetAuthors(string $filename, bool $onlylast = false): array|false
    {
        if (!$this->ProjectId) {
            echo 'DailyUpdate::GetAuthors(): ProjectId is not set<br>';
            return false;
        }

        // Check if the note already exists
        // Remove
        if (str_starts_with($filename, './')) {
            $filename = substr($filename, 2);
        }

        $sql = '';
        if ($onlylast) {
            $sql = ' ORDER BY dailyupdate.id DESC LIMIT 1';
        }

        $db = Database::getInstance();
        $query = $db->executePrepared("
                     SELECT DISTINCT up.userid, dailyupdate.id
                     FROM
                         user2project AS up,
                         user2repository AS ur,
                         dailyupdatefile,
                         dailyupdate
                     WHERE
                         dailyupdatefile.dailyupdateid=dailyupdate.id
                         AND dailyupdate.projectid=up.projectid
                         AND ur.credential=dailyupdatefile.author
                         AND up.projectid=?
                         AND up.userid=ur.userid
                         AND (ur.projectid=0 OR ur.projectid=?)
                         AND dailyupdatefile.filename LIKE '%' || ?
                     $sql
                 ", [intval($this->ProjectId), intval($this->ProjectId), $filename]);

        if ($query === false) {
            add_last_sql_error('DailyUpdate GetAuthors', $this->ProjectId);
            return false;
        }

        $authorids = [];
        foreach ($query as $query_array) {
            $authorids[] = intval($query_array['userid']);
        }
        return $authorids;
    }
}
