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

class SubProjectGroup
{
    private $Id;
    private $ProjectId;
    private $Name;
    private $IsDefault;
    private $CoverageThreshold;
    private $StartTime;
    private $EndTime;
    private $Position;

    public function __construct()
    {
        $this->Id = 0;
        $this->ProjectId = 0;
        $this->Name = '';
        $this->IsDefault = 0;
        $this->Position = 1;
    }

    /** Get the Id of this subproject group. */
    public function GetId()
    {
        return $this->Id;
    }

    /** Set the id of this subproject group.  This function loads the
     * rest of the details about this group from the database.
     **/
    public function SetId($id)
    {
        if (!is_numeric($id)) {
            return false;
        }
        $this->Id = $id;

        $row = pdo_single_row_query(
            'SELECT * FROM subprojectgroup
            WHERE id=' . qnum($this->Id) . " AND endtime='1980-01-01 00:00:00'");
        if (empty($row)) {
            add_log(
                "No subprojectgroup found with Id='$this->Id'",
                'SubProjectGroup::SetId',
                LOG_WARNING);
            return false;
        }

        $this->Name = $row['name'];
        $this->ProjectId = $row['projectid'];
        $this->CoverageThreshold = $row['coveragethreshold'];
        $this->IsDefault = $row['is_default'];
        $this->StartTime = $row['starttime'];
        $this->EndTime = $row['endtime'];
        $this->Position = $row['position'];
        return true;
    }

    /** Function to get the project id. */
    public function GetProjectId()
    {
        return $this->ProjectId;
    }

    /** Function to set the project id. */
    public function SetProjectId($projectid)
    {
        if (is_numeric($projectid)) {
            $this->ProjectId = $projectid;
            if ($this->Name != '') {
                $this->Fill();
            }
            return true;
        }
        return false;
    }

    /** Get the Name of this subproject group. */
    public function GetName()
    {
        if (strlen($this->Name) > 0) {
            return $this->Name;
        }

        if ($this->Id < 1) {
            echo 'SubProjectGroup GetName(): Id not set';
            return false;
        }

        $row = pdo_single_row_query(
            'SELECT name FROM subprojectgroup WHERE id=' . qnum($this->Id));

        if (empty($row)) {
            return false;
        }

        $this->Name = $row['name'];
        return $this->Name;
    }

    /** Set the Name of the subproject. */
    public function SetName($name)
    {
        $this->Name = pdo_real_escape_string($name);
        if ($this->ProjectId > 0) {
            $this->Fill();
        }
    }

    /** Get whether or not this subproject group is the default group. */
    public function GetIsDefault()
    {
        return $this->IsDefault;
    }

    /** Set whether or not this subproject group is the default group. */
    public function SetIsDefault($is_default)
    {
        if ($is_default) {
            $this->IsDefault = 1;
        } else {
            $this->IsDefault = 0;
        }
    }

    /** Get the coverage threshold for this subproject group. */
    public function GetCoverageThreshold()
    {
        return $this->CoverageThreshold;
    }

    /** Set the coverage threshold for this subproject group. */
    public function SetCoverageThreshold($threshold)
    {
        if (is_numeric($threshold)) {
            $this->CoverageThreshold = $threshold;
        }
    }

    /** Populate the ivars of an existing subproject group.
     * Called automatically once name & projectid are set.
     **/
    public function Fill()
    {
        if ($this->Name == '' || $this->ProjectId == 0) {
            add_log(
                "Name='" . $this->Name . "' or ProjectId='" . $this->ProjectId . "' not set",
                'SubProjectGroup::Fill',
                LOG_WARNING);
            return false;
        }

        $row = pdo_single_row_query(
            'SELECT id, coveragethreshold, is_default, starttime
       FROM subprojectgroup
       WHERE projectid=' . qnum($this->ProjectId) . "
       AND name='$this->Name' AND endtime='1980-01-01 00:00:00'");

        if (empty($row)) {
            return false;
        }

        $this->Id = $row['id'];
        $this->CoverageThreshold = $row['coveragethreshold'];
        $this->IsDefault = $row['is_default'];
        $this->StartTime = $row['starttime'];
        return true;
    }

    /** Delete a subproject group */
    public function Delete($keephistory = true)
    {
        if ($this->Id < 1) {
            return false;
        }

        // If there are no subprojects in this group we can safely remove it.
        $query = pdo_query(
            'SELECT count(*) FROM subproject WHERE groupid=' . qnum($this->Id));
        if (!$query) {
            add_last_sql_error('SubProjectGroup Delete');
            return false;
        }
        $query_array = pdo_fetch_array($query);
        if ($query_array[0] == 0) {
            $keephistory = false;
        }

        if (!$keephistory) {
            pdo_query('DELETE FROM subprojectgroup WHERE id=' . qnum($this->Id));
        } else {
            $endtime = gmdate(FMT_DATETIME);
            $query = 'UPDATE subprojectgroup SET ';
            $query .= "endtime='" . $endtime . "'";
            $query .= ' WHERE id=' . qnum($this->Id) . '';
            if (!pdo_query($query)) {
                add_last_sql_error('SubProjectGroup Delete');
                return false;
            }
        }
    }

    /** Return if a subproject group exists */
    public function Exists()
    {
        // If no id specify return false
        if ($this->Id < 1) {
            return false;
        }

        $query = pdo_query(
            "SELECT count(*) FROM subprojectgroup WHERE id='" . $this->Id . "'
       AND endtime='1980-01-01 00:00:00'");
        $query_array = pdo_fetch_array($query);
        if ($query_array[0] > 0) {
            return true;
        }
        return false;
    }

    // Save this subproject group in the database.
    public function Save()
    {
        if ($this->Name == '' || $this->ProjectId == 0) {
            add_log(
                "Name='" . $this->Name . "' or ProjectId='" . $this->ProjectId . "' not set",
                'SubProjectGroup::Save',
                LOG_WARNING);
            return false;
        }

        // Load the default coverage threshold for this project if one
        // hasn't been set for this group.
        if (!isset($this->CoverageThreshold)) {
            $row = pdo_single_row_query(
                'SELECT coveragethreshold FROM project
         WHERE id=' . qnum($this->ProjectId));
            if (empty($row)) {
                return false;
            }
            $this->CoverageThreshold = $row['coveragethreshold'];
        }

        // Force is_default=1 if this will be the first subproject group
        // for this project.
        $query = pdo_query(
            'SELECT COUNT(*) FROM subprojectgroup
       WHERE projectid=' . qnum($this->ProjectId));
        if (!$query) {
            add_last_sql_error('SubProjectGroup::Save Count');
            return false;
        }
        $query_array = pdo_fetch_array($query);
        if ($query_array[0] == 0) {
            $this->IsDefault = 1;
        }

        // Check if the group already exists.
        if ($this->Exists()) {
            // Trim the name
            $this->Name = trim($this->Name);

            // Update the group
            $query = "UPDATE subprojectgroup SET
        name='$this->Name',
        projectid=" . qnum($this->ProjectId) . ',
        is_default=' . qnum($this->IsDefault) . ',
        coveragethreshold=' . qnum($this->CoverageThreshold) . '
        WHERE id=' . qnum($this->Id);
            if (!pdo_query($query)) {
                add_last_sql_error('SubProjectGroup::Save Update');
                return false;
            }
        } else {
            // insert the subproject

            $id = '';
            $idvalue = '';
            if ($this->Id) {
                $id = 'id,';
                $idvalue = "'" . $this->Id . "',";
            }

            // Trim the name
            $this->Name = trim($this->Name);

            // Double check that it's not already in the database.
            $query = pdo_query(
                "SELECT id FROM subprojectgroup WHERE name='$this->Name'
         AND projectid=" . qnum($this->ProjectId) . "
         AND endtime='1980-01-01 00:00:00'");
            if (!$query) {
                add_last_sql_error('SubProjectGroup::Save Select');
                return false;
            }
            if (pdo_num_rows($query) > 0) {
                $query_array = pdo_fetch_array($query);
                $this->Id = $query_array['id'];
                return true;
            }

            $starttime = gmdate(FMT_DATETIME);
            $endtime = '1980-01-01 00:00:00';
            $position = $this->GetNextPosition();
            $query =
                'INSERT INTO subprojectgroup
                (' . $id . 'name, projectid, is_default, coveragethreshold,
                 starttime, endtime, position)
                VALUES
                (' . $idvalue . "'$this->Name'," . qnum($this->ProjectId) . ',' .
                 qnum($this->IsDefault) . ',' . qnum($this->CoverageThreshold) . ",
                 '$starttime', '$endtime', " . qnum($position).')';

            if (!pdo_query($query)) {
                add_last_sql_error('SubProjectGroup::Save Insert');
                return false;
            }

            if ($this->Id < 1) {
                $this->Id = pdo_insert_id('subprojectgroup');
            }
        }

        // Make sure there's only one default group per project.
        if ($this->IsDefault) {
            $query =
                'UPDATE subprojectgroup SET is_default=0
         WHERE projectid=' . qnum($this->ProjectId) . ' AND id!=' . qnum($this->Id);
            if (!pdo_query($query)) {
                add_last_sql_error('SubProjectGroup Update Default');
                return false;
            }
        }
        return true;
    }

    public function GetPosition()
    {
        return $this->Position;
    }

    /** Get the next position available for this group. */
    public function GetNextPosition()
    {
        $query = pdo_query(
            "SELECT position FROM subprojectgroup
                WHERE projectid='$this->ProjectId'
                AND endtime='1980-01-01 00:00:00'
                ORDER BY position DESC LIMIT 1");
        if (pdo_num_rows($query) > 0) {
            $query_array = pdo_fetch_array($query);
            return $query_array['position'] + 1;
        }
        return 1;
    }
}
