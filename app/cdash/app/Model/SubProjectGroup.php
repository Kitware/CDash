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

class SubProjectGroup
{
    private int $Id;
    private int $ProjectId;
    private string $Name;
    private int $IsDefault;
    private ?int $CoverageThreshold;
    private int $Position;

    public function __construct()
    {
        $this->Id = 0;
        $this->ProjectId = 0;
        $this->Name = '';
        $this->IsDefault = 0;
        $this->Position = 1;
        $this->CoverageThreshold = null;
    }

    /** Get the Id of this subproject group. */
    public function GetId(): int
    {
        return $this->Id;
    }

    /** Set the id of this subproject group.  This function loads the
     * rest of the details about this group from the database.
     **/
    public function SetId(int $id): bool
    {
        if (!is_numeric($id)) {
            return false;
        }
        $this->Id = $id;

        $db = Database::getInstance();
        $row = $db->executePreparedSingleRow("
                   SELECT *
                   FROM subprojectgroup
                   WHERE id=? AND endtime='1980-01-01 00:00:00'
               ", [$this->Id]);
        if (empty($row)) {
            add_log(
                "No subprojectgroup found with Id='$this->Id'",
                'SubProjectGroup::SetId',
                LOG_WARNING);
            return false;
        }

        $this->Name = $row['name'];
        $this->ProjectId = intval($row['projectid']);
        $this->CoverageThreshold = intval($row['coveragethreshold']);
        $this->IsDefault = intval($row['is_default']);
        $this->Position = intval($row['position']);
        return true;
    }

    /** Function to set the project id. */
    public function SetProjectId(int $projectid): bool
    {
        $this->ProjectId = $projectid;
        if ($this->Name !== '') {
            return $this->Fill();
        }
        return true;
    }

    /** Get the Name of this subproject group. */
    public function GetName(): string|false
    {
        if (strlen($this->Name) > 0) {
            return $this->Name;
        }

        if ($this->Id < 1) {
            echo 'SubProjectGroup GetName(): Id not set';
            return false;
        }

        $db = Database::getInstance();
        $row = $db->executePreparedSingleRow('SELECT name FROM subprojectgroup WHERE id=?', [$this->Id]);

        if (empty($row)) {
            return false;
        }

        $this->Name = $row['name'];
        return $this->Name;
    }

    /** Set the Name of the subproject. */
    public function SetName(string $name): void
    {
        $this->Name = $name;
        if ($this->ProjectId > 0) {
            $this->Fill();
        }
    }

    /**
     * Get whether or not this subproject group is the default group.
     *
     * TODO: (williamjallen) why does this function return an int?  It should return a bool...
     */
    public function GetIsDefault(): int
    {
        return $this->IsDefault;
    }

    /** Set whether or not this subproject group is the default group. */
    public function SetIsDefault(int $is_default): void
    {
        if ($is_default) {
            $this->IsDefault = 1;
        } else {
            $this->IsDefault = 0;
        }
    }

    /** Get the coverage threshold for this subproject group. */
    public function GetCoverageThreshold(): ?int
    {
        return $this->CoverageThreshold;
    }

    /** Set the coverage threshold for this subproject group. */
    public function SetCoverageThreshold(int $threshold): void
    {
        $this->CoverageThreshold = $threshold;
    }

    /** Populate the ivars of an existing subproject group.
     * Called automatically once name & projectid are set.
     **/
    public function Fill(): bool
    {
        if ($this->Name === '' || $this->ProjectId === 0) {
            add_log(
                "Name='" . $this->Name . "' or ProjectId='" . $this->ProjectId . "' not set",
                'SubProjectGroup::Fill',
                LOG_WARNING);
            return false;
        }

        $db = Database::getInstance();
        $row = $db->executePreparedSingleRow("
                   SELECT id, coveragethreshold, is_default, starttime
                   FROM subprojectgroup
                   WHERE
                       projectid=?
                       AND name=?
                       AND endtime='1980-01-01 00:00:00'
               ", [$this->ProjectId, $this->Name]);

        if (empty($row)) {
            return false;
        }

        $this->Id = intval($row['id']);
        $this->CoverageThreshold = intval($row['coveragethreshold']);
        $this->IsDefault = intval($row['is_default']);
        return true;
    }

    /** Delete a subproject group */
    public function Delete(bool $keephistory = true): bool
    {
        if ($this->Id < 1) {
            return false;
        }

        $db = Database::getInstance();

        // If there are no subprojects in this group we can safely remove it.
        $query_array = $db->executePreparedSingleRow('SELECT count(*) AS c FROM subproject WHERE groupid=?', [$this->Id]);
        if ($query_array === false) {
            add_last_sql_error('SubProjectGroup Delete');
            return false;
        }
        if (intval($query_array['c']) === 0) {
            $keephistory = false;
        }

        if (!$keephistory) {
            $db->executePrepared('DELETE FROM subprojectgroup WHERE id=?', [$this->Id]);
        } else {
            $endtime = gmdate(FMT_DATETIME);
            $query = $db->executePrepared('
                         UPDATE subprojectgroup
                         SET endtime=?
                         WHERE id=?
                     ', [$endtime, $this->Id]);
            if ($query === false) {
                add_last_sql_error('SubProjectGroup Delete');
                return false;
            }
        }
        return true;
    }

    /** Return if a subproject group exists */
    public function Exists(): bool
    {
        // If no id specify return false
        if ($this->Id < 1) {
            return false;
        }

        $db = Database::getInstance();

        $query = $db->executePreparedSingleRow("
                     SELECT count(*) AS c
                     FROM subprojectgroup
                     WHERE
                         id=?
                         AND endtime='1980-01-01 00:00:00'
                 ", [$this->Id]);
        return intval($query['c']) > 0;
    }

    // Save this subproject group in the database.
    public function Save(): bool
    {
        if ($this->Name === '' || $this->ProjectId === 0) {
            add_log(
                "Name='" . $this->Name . "' or ProjectId='" . $this->ProjectId . "' not set",
                'SubProjectGroup::Save',
                LOG_WARNING);
            return false;
        }

        $db = Database::getInstance();

        // Load the default coverage threshold for this project if one
        // hasn't been set for this group.
        if (!isset($this->CoverageThreshold)) {
            $row = $db->executePreparedSingleRow('SELECT coveragethreshold FROM project WHERE id=?', [$this->ProjectId]);
            if (empty($row)) {
                return false;
            }
            $this->CoverageThreshold = intval($row['coveragethreshold']);
        }

        // Force is_default=1 if this will be the first subproject group
        // for this project.
        $query_array = $db->executePreparedSingleRow('
                           SELECT COUNT(*) AS c
                           FROM subprojectgroup
                           WHERE projectid=?
                       ', [$this->ProjectId]);
        if ($query_array === false) {
            add_last_sql_error('SubProjectGroup::Save Count');
            return false;
        }
        if (intval($query_array['c']) === 0) {
            $this->IsDefault = 1;
        }

        // Check if the group already exists.
        if ($this->Exists()) {
            // Trim the name
            $this->Name = trim($this->Name);

            // Update the group
            $query = $db->executePrepared('
                         UPDATE subprojectgroup
                         SET
                             name=?,
                             projectid=?,
                             is_default=?,
                             coveragethreshold=?
                         WHERE id=?
                     ', [$this->Name, $this->ProjectId, $this->IsDefault, $this->CoverageThreshold, $this->Id]);
            if ($query === false) {
                add_last_sql_error('SubProjectGroup::Save Update');
                return false;
            }
        } else {
            // insert the subproject

            // Trim the name
            $this->Name = trim($this->Name);

            // Double check that it's not already in the database.
            $query = $db->executePreparedSingleRow("
                         SELECT id
                         FROM subprojectgroup
                         WHERE
                             name=?
                             AND projectid=?
                             AND endtime='1980-01-01 00:00:00'
                     ", [$this->Name, $this->ProjectId]);
            if (!empty($query)) {
                $this->Id = intval($query['id']);
                return true;
            }

            $id = '';
            $idvalue = [];
            $prepared_array = $db->createPreparedArray(7);
            if ($this->Id) {
                $id = 'id,';
                $idvalue[] = $this->Id;
                $prepared_array = $db->createPreparedArray(8);
            }

            $starttime = gmdate(FMT_DATETIME);
            $endtime = '1980-01-01T00:00:00';
            $position = $this->GetNextPosition();
            $query = $db->executePrepared("
                         INSERT INTO subprojectgroup (
                             $id
                             name,
                             projectid,
                             is_default,
                             coveragethreshold,
                             starttime,
                             endtime,
                             position
                         )
                         VALUES $prepared_array
                     ", array_merge($idvalue, [
                         $this->Name,
                         $this->ProjectId,
                         $this->IsDefault,
                         $this->CoverageThreshold,
                         $starttime,
                         $endtime,
                         $position
                     ]));

            if ($query === false) {
                add_last_sql_error('SubProjectGroup::Save Insert');
                return false;
            }

            if ($this->Id < 1) {
                $this->Id = intval(pdo_insert_id('subprojectgroup'));
            }
        }

        // Make sure there's only one default group per project.
        if ($this->IsDefault) {
            $query = $db->executePrepared('
                         UPDATE subprojectgroup
                         SET is_default=0
                         WHERE projectid=? AND id<>?
                     ', [$this->ProjectId, $this->Id]);
            if ($query === false) {
                add_last_sql_error('SubProjectGroup Update Default');
                return false;
            }
        }
        return true;
    }

    public function GetPosition(): int
    {
        return $this->Position;
    }

    /** Get the next position available for this group. */
    public function GetNextPosition(): int
    {
        $db = Database::getInstance();
        $query = $db->executePreparedSingleRow("
                     SELECT position
                     FROM subprojectgroup
                     WHERE
                         projectid=?
                         AND endtime='1980-01-01 00:00:00'
                     ORDER BY position DESC
                     LIMIT 1
                 ", [$this->ProjectId]);
        return !empty($query) ? intval($query['position']) + 1 : 1;
    }
}
