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

include_once 'models/subprojectgroup.php';

/** Main subproject class */
class SubProject
{
    private $Name;
    private $Id;
    private $ProjectId;
    private $GroupId;
    private $Path;

    public function __construct()
    {
        $this->Id = 0;
        $this->GroupId = 0;
        $this->ProjectId = 0;
        $this->Name = '';
        $this->Path = '';
    }

    /** Function to get the id */
    public function GetId()
    {
        return $this->Id;
    }

    /** Function to set the id.  Also loads remaining data for this
     * subproject from the database.
     **/
    public function SetId($id)
    {
        if (!is_numeric($id)) {
            return false;
        }

        $this->Id = $id;

        $row = pdo_single_row_query(
            'SELECT name, projectid, groupid, path FROM subproject
       WHERE id=' . qnum($this->Id) . " AND endtime='1980-01-01 00:00:00'");
        if (empty($row)) {
            return false;
        }

        $this->Name = $row['name'];
        $this->ProjectId = $row['projectid'];
        $this->GroupId = $row['groupid'];
        $this->Path = $row['path'];
        return true;
    }

    /** Function to get the project id */
    public function GetProjectId()
    {
        return $this->ProjectId;
    }

    /** Function to set the project id */
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

    /** Delete a subproject */
    public function Delete($keephistory = true)
    {
        if ($this->Id < 1) {
            return false;
        }

        // If there is no build in the subproject we remove
        $query = pdo_query('SELECT count(*) FROM subproject2build WHERE subprojectid=' . qnum($this->Id));
        if (!$query) {
            add_last_sql_error('SubProject Delete');
            return false;
        }
        $query_array = pdo_fetch_array($query);
        if ($query_array[0] == 0) {
            $keephistory = false;
        }

        // Regardless of whether or not we're performing a "soft delete",
        // we should remove any dependencies on this subproject.
        pdo_query(
            'DELETE FROM subproject2subproject WHERE dependsonid=' . qnum($this->Id));

        if (!$keephistory) {
            pdo_query('DELETE FROM subproject2build WHERE subprojectid=' . qnum($this->Id));
            pdo_query('DELETE FROM subproject2subproject WHERE subprojectid=' . qnum($this->Id));
            pdo_query('DELETE FROM subproject WHERE id=' . qnum($this->Id));
        } else {
            $endtime = gmdate(FMT_DATETIME);
            $query = 'UPDATE subproject SET ';
            $query .= "endtime='" . $endtime . "'";
            $query .= ' WHERE id=' . qnum($this->Id) . '';
            if (!pdo_query($query)) {
                add_last_sql_error('SubProject Delete');
                return false;
            }
        }
    }

    /** Return if a subproject exists */
    public function Exists()
    {
        // If no id specify return false
        if ($this->Id < 1) {
            return false;
        }

        $query = pdo_query("SELECT count(*) FROM subproject WHERE id='" . $this->Id . "' AND endtime='1980-01-01 00:00:00'");
        $query_array = pdo_fetch_array($query);
        if ($query_array[0] > 0) {
            return true;
        }
        return false;
    }

    // Save the subproject in the database
    public function Save()
    {
        // Assign it to the default group if necessary.
        if ($this->GroupId < 1) {
            $row = pdo_single_row_query(
                'SELECT id from subprojectgroup
         WHERE projectid=' . qnum($this->ProjectId) . ' AND is_default=1');
            if (!empty($row)) {
                $this->GroupId = $row['id'];
            }
        }

        // Check if the subproject already exists.
        if ($this->Exists()) {
            // Trim the name
            $this->Name = trim($this->Name);

            // Update the subproject
            $query = 'UPDATE subproject SET ';
            $query .= "name='" . $this->Name . "'";
            $query .= ',projectid=' . qnum($this->ProjectId);
            $query .= ',groupid=' . qnum($this->GroupId);
            $query .= ",path='" . $this->Path . "'";
            $query .= ' WHERE id=' . qnum($this->Id) . '';

            if (!pdo_query($query)) {
                add_last_sql_error('SubProject Update');
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
            $select_query =
                "SELECT id FROM subproject WHERE name='$this->Name' AND
            projectid=" . qnum($this->ProjectId) . " AND
            endtime='1980-01-01 00:00:00'";
            $result = pdo_query($select_query);

            if (!$result) {
                add_last_sql_error('SubProject Update');
                return false;
            }

            if (pdo_num_rows($result) > 0) {
                $row = pdo_fetch_array($result);
                $this->Id = $row['id'];
                return true;
            }

            $starttime = gmdate(FMT_DATETIME);
            $endtime = '1980-01-01 00:00:00';
            $insert_query =
                'INSERT INTO subproject(' . $id . 'name,projectid,groupid,path,starttime,endtime)
                VALUES (' . $idvalue . "'$this->Name'," . qnum($this->ProjectId) . ',' .
                qnum($this->GroupId) . ",'$this->Path','$starttime','$endtime')";

            if (!pdo_query($insert_query)) {
                $error = pdo_error();
                // Check if the query failed due to a race condition during
                // parallel submission processing.
                $result = pdo_query($select_query);
                if (!$result || pdo_num_rows($result) == 0) {
                    add_log("SQL error: $error", 'SubProject Create', LOG_ERR, $this->ProjectId);
                    return false;
                }
                $row = pdo_fetch_array($result);
                $this->Id = $row['id'];
            }

            if ($this->Id < 1) {
                $this->Id = pdo_insert_id('subproject');
            }
        }
        return true;
    }

    /** Get the Name of the subproject */
    public function GetName()
    {
        if (strlen($this->Name) > 0) {
            return $this->Name;
        }

        if ($this->Id < 1) {
            return false;
        }

        $project = pdo_query('SELECT name FROM subproject WHERE id=' . qnum($this->Id));
        if (!$project) {
            add_last_sql_error('SubProject GetName');
            return false;
        }
        $project_array = pdo_fetch_array($project);
        $this->Name = $project_array['name'];
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

    /** Populate the ivars of an existing subproject.
     * Called automatically once name & projectid are set.
     **/
    public function Fill()
    {
        if ($this->Name == '' || $this->ProjectId == 0) {
            add_log(
                "Name='" . $this->Name . "' or ProjectId='" . $this->ProjectId . "' not set",
                'SubProject::Fill',
                LOG_WARNING);
            return false;
        }

        $row = pdo_single_row_query(
            'SELECT id, groupid FROM subproject
       WHERE projectid=' . qnum($this->ProjectId) . "
       AND name='$this->Name' AND endtime='1980-01-01 00:00:00'");

        if (empty($row)) {
            return false;
        }

        $this->Id = $row['id'];
        $this->GroupId = $row['groupid'];
        return true;
    }

    /** Get the group that this subproject belongs to. */
    public function GetGroupId()
    {
        if ($this->Id < 1) {
            return false;
        }

        $row = pdo_single_row_query(
            'SELECT groupid FROM subproject WHERE id=' . qnum($this->Id));
        if (empty($row)) {
            return false;
        }
        $this->GroupId = $row['groupid'];
        return $this->GroupId;
    }

    /** Function to set this subproject's group. */
    public function SetGroup($groupName)
    {
        $groupName = pdo_real_escape_string($groupName);
        $row = pdo_single_row_query(
            "SELECT id from subprojectgroup
            WHERE name = '$groupName' AND endtime='1980-01-01 00:00:00'");
        if (empty($row)) {
            // Create the group if it doesn't exist yet.
            $subprojectGroup = new SubProjectGroup();
            $subprojectGroup->SetName($groupName);
            $subprojectGroup->SetProjectId($this->ProjectId);
            if ($subprojectGroup->Save() === false) {
                return false;
            }
            $this->GroupId = $subprojectGroup->GetId();
            return true;
        }
        $this->GroupId = $row['id'];
        return true;
    }

    /** Get/Set this SubProject's path. */
    public function GetPath()
    {
        return $this->Path;
    }

    public function SetPath($path)
    {
        $this->Path = $path;
    }

    /** Get the last submission of the subproject*/
    public function GetLastSubmission()
    {
        global $CDASH_SHOW_LAST_SUBMISSION;
        if (!$CDASH_SHOW_LAST_SUBMISSION) {
            return false;
        }

        if ($this->Id < 1) {
            return false;
        }

        $project = pdo_query('SELECT submittime FROM build,subproject2build,build2group,buildgroup WHERE subprojectid=' . qnum($this->Id) .
            ' AND build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                           AND buildgroup.includesubprojectotal=1
                           AND subproject2build.buildid=build.id ORDER BY submittime DESC LIMIT 1');
        if (!$project) {
            add_last_sql_error('SubProject GetLastSubmission');
            return false;
        }
        $project_array = pdo_fetch_array($project);

        if (!is_array($project_array) ||
                !array_key_exists('submittime', $project_array)) {
            return false;
        }

        return date(FMT_DATETIMESTD, strtotime($project_array['submittime'] . 'UTC'));
    }

    /** Encapsulate common logic for build queries in this class. */
    private function CommonBuildQuery($startUTCdate, $endUTCdate, $allSubProjects, $extraCriteria)
    {
        if (!$allSubProjects && $this->Id < 1) {
            return false;
        }

        $extraSelect = '';
        $extraWhere = '';
        if ($allSubProjects) {
            $extraSelect = 'subprojectid, ';
        } else {
            $extraWhere = 'subprojectid = ' . qnum($this->Id) . 'AND ';
        }

        $query =
            "SELECT $extraSelect COUNT(*) FROM build b
            JOIN build2group b2g ON (b2g.buildid = b.id)
            JOIN buildgroup bg ON (bg.id = b2g.groupid)
            JOIN subproject2build sp2b ON (sp2b.buildid = b.id)
            WHERE $extraWhere
            b.projectid = $this->ProjectId AND
            b.starttime > '$startUTCdate' AND
            b.starttime <= '$endUTCdate' AND
            $extraCriteria AND
            bg.includesubprojectotal = 1";
        if ($allSubProjects) {
            $query .= ' GROUP BY subprojectid';
        }
        $project = pdo_query($query);

        if (!$project) {
            add_last_sql_error("SubProject CommonBuildQuery($extraCriteria)");
            return false;
        }
        if ($allSubProjects) {
            $project_array = array();
            while ($row = pdo_fetch_array($project)) {
                $project_array[$row['subprojectid']] = $row;
            }
            pdo_free_result($project);
            return $project_array;
        } else {
            $project_array = pdo_fetch_array($project);
            return intval($project_array[0]);
        }
    }

    /** Get the number of warning builds given a date range */
    public function GetNumberOfWarningBuilds($startUTCdate, $endUTCdate, $allSubProjects = false)
    {
        $criteria = 'b.buildwarnings > 0';
        return $this->CommonBuildQuery($startUTCdate, $endUTCdate, $allSubProjects, $criteria);
    }

    /** Get the number of error builds given a date range */
    public function GetNumberOfErrorBuilds($startUTCdate, $endUTCdate, $allSubProjects = false)
    {
        $criteria = 'b.builderrors > 0';
        return $this->CommonBuildQuery($startUTCdate, $endUTCdate, $allSubProjects, $criteria);
    }

    /** Get the number of failing builds given a date range */
    public function GetNumberOfPassingBuilds($startUTCdate, $endUTCdate, $allSubProjects = false)
    {
        $criteria = 'b.builderrors = 0 AND b.buildwarnings = 0';
        return $this->CommonBuildQuery($startUTCdate, $endUTCdate, $allSubProjects, $criteria);
    }

    /** Get the number of failing configure given a date range */
    public function GetNumberOfWarningConfigures($startUTCdate, $endUTCdate, $allSubProjects = false)
    {
        $criteria = 'b.configurewarnings > 0';
        return $this->CommonBuildQuery($startUTCdate, $endUTCdate, $allSubProjects, $criteria);
    }

    /** Get the number of failing configure given a date range */
    public function GetNumberOfErrorConfigures($startUTCdate, $endUTCdate, $allSubProjects = false)
    {
        $criteria = 'b.configureerrors > 0';
        return $this->CommonBuildQuery($startUTCdate, $endUTCdate, $allSubProjects, $criteria);
    }

    /** Get the number of failing configure given a date range */
    public function GetNumberOfPassingConfigures($startUTCdate, $endUTCdate, $allSubProjects = false)
    {
        $criteria = 'b.configureerrors = 0 AND b.configurewarnings = 0';
        return $this->CommonBuildQuery($startUTCdate, $endUTCdate, $allSubProjects, $criteria);
    }

    /** Get the number of tests given a date range */
    public function GetNumberOfPassingTests($startUTCdate, $endUTCdate, $allSubProjects = false)
    {
        if (!$allSubProjects && $this->Id < 1) {
            return false;
        }

        $queryStr = 'SELECT ';
        if ($allSubProjects) {
            $queryStr .= 'subprojectid, ';
        }
        $queryStr .= 'SUM(build.testpassed) FROM build,subproject2build,build2group,buildgroup WHERE ';
        if (!$allSubProjects) {
            $queryStr .= 'subprojectid=' . qnum($this->Id) . 'AND ';
        }

        $queryStr .= "build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                  AND buildgroup.includesubprojectotal=1
                  AND subproject2build.buildid=build.id AND build.starttime>'$startUTCdate'
                  AND build.starttime<='$endUTCdate' AND build.testpassed>=0 ";

        if ($allSubProjects) {
            $queryStr .= 'GROUP BY subprojectid';
        }
        $project = pdo_query($queryStr);

        if (!$project) {
            add_last_sql_error('SubProject GetNumberOfPassingTests');
            return false;
        }
        if ($allSubProjects) {
            $project_array = array();
            while ($row = pdo_fetch_array($project)) {
                $project_array[$row['subprojectid']] = $row;
            }
            pdo_free_result($project);
            return $project_array;
        } else {
            $project_array = pdo_fetch_array($project);
            return intval($project_array[0]);
        }
    }

    /** Get the number of tests given a date range */
    public function GetNumberOfFailingTests($startUTCdate, $endUTCdate, $allSubProjects = false)
    {
        if (!$allSubProjects && $this->Id < 1) {
            return false;
        }

        $queryStr = 'SELECT ';
        if ($allSubProjects) {
            $queryStr .= 'subprojectid, ';
        }
        $queryStr .= 'SUM(build.testfailed) FROM build,subproject2build,build2group,buildgroup WHERE ';
        if (!$allSubProjects) {
            $queryStr .= 'subprojectid=' . qnum($this->Id) . 'AND ';
        }

        $queryStr .= "build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                  AND buildgroup.includesubprojectotal=1
                  AND subproject2build.buildid=build.id AND build.starttime>'$startUTCdate'
                  AND build.starttime<='$endUTCdate' AND build.testfailed>=0 ";

        if ($allSubProjects) {
            $queryStr .= 'GROUP BY subprojectid';
        }
        $project = pdo_query($queryStr);

        if (!$project) {
            add_last_sql_error('SubProject GetNumberOfFailingTests');
            return false;
        }
        if ($allSubProjects) {
            $project_array = array();
            while ($row = pdo_fetch_array($project)) {
                $project_array[$row['subprojectid']] = $row;
            }
            pdo_free_result($project);
            return $project_array;
        } else {
            $project_array = pdo_fetch_array($project);
            return intval($project_array[0]);
        }
    }

    /** Get the number of tests given a date range */
    public function GetNumberOfNotRunTests($startUTCdate, $endUTCdate, $allSubProjects = false)
    {
        if (!$allSubProjects && $this->Id < 1) {
            return false;
        }

        $queryStr = 'SELECT ';
        if ($allSubProjects) {
            $queryStr .= 'subprojectid, ';
        }
        $queryStr .= 'SUM(build.testnotrun) FROM build,subproject2build,build2group,buildgroup WHERE ';
        if (!$allSubProjects) {
            $queryStr .= 'subprojectid=' . qnum($this->Id) . 'AND ';
        }

        $queryStr .= "build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                  AND buildgroup.includesubprojectotal=1
                  AND subproject2build.buildid=build.id AND build.starttime>'$startUTCdate'
                  AND build.starttime<='$endUTCdate' AND build.testnotrun>=0 ";

        if ($allSubProjects) {
            $queryStr .= 'GROUP BY subprojectid';
        }
        $project = pdo_query($queryStr);

        if (!$project) {
            add_last_sql_error('SubProject GetNumberOfNotRunTests');
            return false;
        }
        if ($allSubProjects) {
            $project_array = array();
            while ($row = pdo_fetch_array($project)) {
                $project_array[$row['subprojectid']] = $row;
            }
            pdo_free_result($project);
            return $project_array;
        } else {
            $project_array = pdo_fetch_array($project);
            return intval($project_array[0]);
        }
    }

    /** Get the subprojectids of the subprojects depending on this one */
    public function GetDependencies($date = null)
    {
        if ($this->Id < 1) {
            add_log(
                "Id='" . $this->Id . "' not set",
                'SubProject::GetDependencies',
                LOG_WARNING);
            return false;
        }

        // If not set, the date is now
        if ($date == null) {
            $date = gmdate(FMT_DATETIME);
        }

        $project = pdo_query('SELECT dependsonid FROM subproject2subproject
                          WHERE subprojectid=' . qnum($this->Id) . " AND
                          starttime<='" . $date . "' AND (endtime>'" . $date . "' OR endtime='1980-01-01 00:00:00')"
        );
        if (!$project) {
            add_last_sql_error('SubProject GetDependencies');
            return false;
        }
        $ids = array();
        while ($project_array = pdo_fetch_array($project)) {
            $ids[] = $project_array['dependsonid'];
        }
        return $ids;
    }

    /** Add a dependency */
    public function AddDependency($subprojectid)
    {
        if ($this->Id < 1 || !isset($subprojectid) || !is_numeric($subprojectid)) {
            return false;
        }

        // Check that the dependency doesn't exist
        $project = pdo_query('SELECT count(*) FROM subproject2subproject WHERE subprojectid=' . qnum($this->Id) .
            ' AND dependsonid=' . qnum($subprojectid) . " AND endtime='1980-01-01 00:00:00'"
        );
        if (!$project) {
            add_last_sql_error('SubProject AddDependency');
            return false;
        }

        $project_array = pdo_fetch_array($project);
        if ($project_array[0] > 0) {
            return false;
        }

        // Add the dependency
        $starttime = gmdate(FMT_DATETIME);
        $endtime = '1980-01-01 00:00:00';
        $project = pdo_query('INSERT INTO subproject2subproject (subprojectid,dependsonid,starttime,endtime)
                         VALUES (' . qnum($this->Id) .
            ',' . qnum($subprojectid) . ",'" . $starttime . "','" . $endtime . "')");
        if (!$project) {
            add_last_sql_error('SubProject AddDependency');
            return false;
        }
        return true;
    }

    /** Remove a dependency */
    public function RemoveDependency($subprojectid)
    {
        if ($this->Id < 1) {
            return false;
        }

        if (!isset($subprojectid) || !is_numeric($subprojectid)) {
            return false;
        }

        // Set the date of the dependency to be now
        $now = gmdate(FMT_DATETIME);
        $project = pdo_query("UPDATE subproject2subproject SET endtime='" . $now . "'
                          WHERE subprojectid=" . qnum($this->Id) .
            ' AND dependsonid=' . qnum($subprojectid) . " AND endtime='1980-01-01 00:00:00'");
        if (!$project) {
            add_last_sql_error('SubProject RemoveDependency');
            return false;
        }
        return true;
    }
}
