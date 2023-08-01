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

// It is assumed that appropriate headers should be included before including this file
class LabelEmail
{
    public $UserId;
    public $ProjectId;
    public $LabelId;

    public function __construct()
    {
        $this->ProjectId = 0;
        $this->UserId = 0;
        $this->LabelId = 0;
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
                     FROM labelemail
                     WHERE
                         userid=?
                         AND projectid=?
                         AND labelid=?
                 ', [intval($this->UserId), intval($this->ProjectId), intval($this->LabelId)]);

        return intval($query['c']) > 0;
    }

    public function Insert(): bool
    {
        if (!$this->ProjectId) {
            abort(500, 'LabelEmail Insert(): ProjectId not set');
        }

        if (!$this->UserId) {
            abort(500, 'LabelEmail Insert(): UserId not set');
        }

        if (!$this->LabelId) {
            abort(500, 'LabelEmail Insert(): LabelId not set');
        }

        if (!$this->Exists()) {
            $db = Database::getInstance();
            $query = $db->executePrepared('
                         INSERT INTO labelemail (userid, projectid, labelid)
                         VALUES (?, ?, ?)
                    ', [intval($this->UserId), intval($this->ProjectId), intval($this->LabelId)]);
            if ($query === false) {
                return false;
            }
        }
        return true;
    }

    /** Update the labels given a projectid and userid */
    public function UpdateLabels(array|null $labels): bool
    {
        if (!$this->ProjectId) {
            abort(500, 'LabelEmail UpdateLabels(): ProjectId not set');
        }

        if (!$this->UserId) {
            abort(500, 'LabelEmail UpdateLabels(): UserId not set');
        }

        if ($labels === null) {
            $labels = [];
        }

        $existinglabels = $this->GetLabels();
        $toremove = array_diff($existinglabels, $labels);
        $toadd = array_diff($labels, $existinglabels);

        foreach ($toremove as $id) {
            $this->LabelId = $id;
            $this->Remove();
        }

        foreach ($toadd as $id) {
            $this->LabelId = $id;
            $this->Insert();
        }
        return true;
    }

    /**
     * Get the labels given a projectid and userid
     *
     * @return array<int>|false
     */
    public function GetLabels(): array|false
    {
        if (empty($this->ProjectId)) {
            abort(500, 'LabelEmail GetLabels(): ProjectId not set');
        }

        if (empty($this->UserId)) {
            abort(500, 'LabelEmail GetLabels(): UserId not set');
        }

        $db = Database::getInstance();
        $labels = $db->executePrepared('
                      SELECT labelid
                      FROM labelemail
                      WHERE projectid=? AND userid=?
                  ', [intval($this->ProjectId), intval($this->UserId)]);
        if ($labels === false) {
            add_last_sql_error('LabelEmail GetLabels');
            return false;
        }

        $labelids = array();
        foreach ($labels as $labels_array) {
            $labelids[] = intval($labels_array['labelid']);
        }
        return $labelids;
    }
}
