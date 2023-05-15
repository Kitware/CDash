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

/** Main subproject class */
class SubProject
{
    private $Name;
    private $Id;
    private $ProjectId;
    private $GroupId;
    private $Path;
    private $Position;
    private $PDO;

    public function __construct()
    {
        $this->Id = 0;
        $this->GroupId = 0;
        $this->ProjectId = 0;
        $this->Position = 0;
        $this->Name = '';
        $this->Path = '';
        $this->PDO = Database::getInstance()->getPdo();
    }

    /** Function to get the id */
    public function GetId(): int
    {
        return $this->Id;
    }

    /** Function to set the id.  Also loads remaining data for this
     * subproject from the database.
     **/
    public function SetId($id): bool
    {
        if (!is_numeric($id)) {
            return false;
        }

        $this->Id = $id;

        $db = Database::getInstance();
        $row = $db->executePreparedSingleRow("
                   SELECT name, projectid, groupid, path, position
                   FROM subproject
                   WHERE
                       id=?
                       AND endtime='1980-01-01 00:00:00'
               ", [intval($this->Id)]);

        if (empty($row)) {
            return false;
        }

        $this->Name = $row['name'];
        $this->ProjectId = $row['projectid'];
        $this->GroupId = $row['groupid'];
        $this->Path = $row['path'];
        $this->Position = $row['position'];
        return true;
    }

    /** Function to get the project id */
    public function GetProjectId(): int
    {
        return $this->ProjectId;
    }

    /** Function to set the project id */
    public function SetProjectId($projectid): bool
    {
        if (is_numeric($projectid)) {
            $this->ProjectId = intval($projectid);
            if ($this->Name != '') {
                $this->Fill();
            }
            return true;
        }
        return false;
    }

    /** Delete a subproject */
    public function Delete($keephistory = true): bool
    {
        if ($this->Id < 1) {
            return false;
        }

        $db = Database::getInstance();

        // If there is no build in the subproject we remove
        $query = $db->executePreparedSingleRow('
                     SELECT count(*) AS c
                     FROM subproject2build
                     WHERE subprojectid=?
                 ', [intval($this->Id)]);
        if (intval($query['c']) === 0) {
            $keephistory = false;
        }

        // Regardless of whether or not we're performing a "soft delete",
        // we should remove any dependencies on this subproject.
        $db->executePrepared('DELETE FROM subproject2subproject WHERE dependsonid=?', [intval($this->Id)]);

        if (!$keephistory) {
            $db->executePrepared('DELETE FROM subproject2build WHERE subprojectid=?', [intval($this->Id)]);
            $db->executePrepared('DELETE FROM subproject2subproject WHERE subprojectid=?', [intval($this->Id)]);
            $db->executePrepared('DELETE FROM subproject WHERE id=?', [intval($this->Id)]);
        } else {
            $endtime = gmdate(FMT_DATETIME);
            $query = $db->executePrepared('UPDATE subproject SET endtime=? WHERE id=?', [$endtime, intval($this->Id)]);
            if ($query === false) {
                add_last_sql_error('SubProject Delete');
                return false;
            }
        }
        return true;
    }

    /** Return if a subproject exists */
    public function Exists(): bool
    {
        // If no id specify return false
        if ($this->Id < 1) {
            return false;
        }

        $db = Database::getInstance();

        $query = $db->executePreparedSingleRow("
                     SELECT count(*) AS c
                     FROM subproject
                     WHERE id=? AND endtime='1980-01-01 00:00:00'
                 ", [intval($this->Id)]);
        if (intval($query['c']) > 0) {
            return true;
        }
        return false;
    }

    // Save the subproject in the database
    public function Save(): bool
    {
        // Assign it to the default group if necessary.
        if ($this->GroupId < 1) {
            $stmt = $this->PDO->prepare(
                'SELECT id from subprojectgroup
                 WHERE projectid = ? AND is_default = 1');
            pdo_execute($stmt, [$this->ProjectId]);
            $groupid = $stmt->fetchColumn();
            if ($groupid) {
                $this->GroupId = $groupid;
            }
        }

        // Trim the name.
        $this->Name = trim($this->Name);

        // Check if the subproject already exists.
        if ($this->Exists()) {
            // Update the subproject
            $stmt = $this->PDO->prepare(
                'UPDATE subproject SET
                    name = ?, projectid = ?, groupid = ?, path = ?, position = ?
                WHERE id = ?');
            return pdo_execute($stmt,
                [$this->Name, $this->ProjectId, $this->GroupId, $this->Path,
                 $this->Position, $this->Id]);
        } else {
            // Double check that it's not already in the database.
            $exists_stmt = $this->PDO->prepare(
                "SELECT id FROM subproject
                WHERE name = ? AND projectid = ? AND
                      endtime = '1980-01-01 00:00:00'");
            if (!pdo_execute($exists_stmt, [$this->Name, $this->ProjectId])) {
                return false;
            }
            $existing_id = $exists_stmt->fetchColumn();
            if ($existing_id) {
                $this->Id = $existing_id;
                return true;
            }

            // Insert the subproject.
            $id = '';
            $idvalue = '';
            if ($this->Id) {
                $id = 'id,';
                $idvalue = "'" . $this->Id . "',";
            }

            $starttime = gmdate(FMT_DATETIME);
            $endtime = '1980-01-01 00:00:00';

            $stmt = $this->PDO->prepare(
                "INSERT INTO subproject
                    ($id name, projectid, groupid, path, position, starttime,
                     endtime)
                VALUES ($idvalue ?, ?, ?, ?, ?, ?, ?)");
            $params = [$this->Name, $this->ProjectId, $this->GroupId,
                       $this->Path, $this->Position, $starttime, $endtime];

            if (!$stmt->execute($params)) {
                $error = pdo_error();
                // Check if the query failed due to a race condition during
                // parallel submission processing.
                $failed = false;
                if (!pdo_execute($exists_stmt, [$this->Name, $this->ProjectId])) {
                    $failed = true;
                } else {
                    $existing_id = $exists_stmt->fetchColumn();
                    if (!$existing_id) {
                        $failed = true;
                    }
                }
                if ($failed) {
                    add_log("SQL error: $error", 'SubProject Create', LOG_ERR, $this->ProjectId);
                    return false;
                }
                $this->Id = $existing_id;
            }

            if ($this->Id < 1) {
                $this->Id = pdo_insert_id('subproject');
            }
        }
        return true;
    }

    /** Get the Name of the subproject */
    public function GetName(): string|false
    {
        if (strlen($this->Name) > 0) {
            return $this->Name;
        }

        if ($this->Id < 1) {
            return false;
        }

        $db = Database::getInstance();
        $project = $db->executePreparedSingleRow('SELECT name FROM subproject WHERE id=?', [intval($this->Id)]);
        if ($project === false) {
            add_last_sql_error('SubProject GetName');
            return false;
        }
        $this->Name = $project['name'];
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

    /** Populate the ivars of an existing subproject.
     * Called automatically once name & projectid are set.
     **/
    public function Fill(): bool
    {
        if ($this->Name === '' || $this->ProjectId === 0) {
            add_log(
                "Name='" . $this->Name . "' or ProjectId='" . $this->ProjectId . "' not set",
                'SubProject::Fill',
                LOG_WARNING);
            return false;
        }

        $stmt = $this->PDO->prepare(
            "SELECT * FROM subproject
           WHERE projectid = ?  AND name = ? AND
                 endtime = '1980-01-01 00:00:00'");

        if (!pdo_execute($stmt, [$this->ProjectId, $this->Name])) {
            return false;
        }
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        $this->Id = intval($row['id']);
        $this->GroupId = intval($row['groupid']);
        $this->Path = $row['path'];
        $this->Position = intval($row['position']);
        return true;
    }

    /** Get the group that this subproject belongs to. */
    public function GetGroupId(): int|false
    {
        if ($this->Id < 1) {
            return false;
        }

        $db = Database::getInstance();
        $row = $db->executePreparedSingleRow('SELECT groupid FROM subproject WHERE id=?', [$this->Id]);
        if (empty($row)) {
            return false;
        }
        $this->GroupId = intval($row['groupid']);
        return $this->GroupId;
    }

    /** Function to set this subproject's group. */
    public function SetGroup(string $groupName): bool
    {
        $db = Database::getInstance();
        $row = $db->executePreparedSingleRow("
                   SELECT id
                   FROM subprojectgroup
                   WHERE
                       name=?
                       AND endtime='1980-01-01 00:00:00'
               ", [$groupName]);

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
        $this->GroupId = intval($row['id']);
        return true;
    }

    /** Get/Set this SubProject's path. */
    public function GetPath(): string
    {
        return $this->Path;
    }

    public function SetPath(string $path): void
    {
        $this->Path = $path;
    }

    /** Get/Set this SubProject's position. */
    public function GetPosition(): int
    {
        return $this->Position;
    }

    public function SetPosition(int $position): void
    {
        $this->Position = $position;
    }

    /** Get the last submission of the subproject*/
    public function GetLastSubmission(): string|false
    {
        if (!config('cdash.show_last_submission')) {
            return false;
        }

        if ($this->Id < 1) {
            return false;
        }

        $db = Database::getInstance();
        $project = $db->executePreparedSingleRow('
                       SELECT submittime
                       FROM build, subproject2build, build2group, buildgroup
                       WHERE
                           subprojectid=?
                           AND build2group.buildid=build.id
                           AND build2group.groupid=buildgroup.id
                           AND buildgroup.includesubprojectotal=1
                           AND subproject2build.buildid=build.id
                       ORDER BY submittime DESC
                       LIMIT 1
                   ', [intval($this->Id)]);
        if ($project === false) {
            add_last_sql_error('SubProject GetLastSubmission');
            return false;
        }

        if (!is_array($project) || !array_key_exists('submittime', $project)) {
            return false;
        }

        return date(FMT_DATETIMESTD, strtotime($project['submittime'] . 'UTC'));
    }

    /**
     * Encapsulate common logic for build queries in this class.
     *
     * Use caution when calling this function.  The $extraCriteria argument will be inserted
     * directly into the SQL, which potentially leaves us open to SQL injection if user-controllable
     * variables are inserted into the query string.
     */
    private function CommonBuildQuery($startUTCdate, $endUTCdate, bool $allSubProjects, string $extraCriteria): int|array|false
    {
        if (!$allSubProjects && $this->Id < 1) {
            return false;
        }

        $extraSelect = '';
        $extraWhere = '';
        $params = [];
        if ($allSubProjects) {
            $extraSelect = 'subprojectid, ';
        } else {
            $extraWhere = 'subprojectid = ? AND ';
            $params[] = intval($this->Id);
        }

        $query =
            "SELECT $extraSelect COUNT(*) AS c
            FROM build b
            JOIN build2group b2g ON (b2g.buildid = b.id)
            JOIN buildgroup bg ON (bg.id = b2g.groupid)
            JOIN subproject2build sp2b ON (sp2b.buildid = b.id)
            WHERE
                $extraWhere
                b.projectid = ? AND
                b.starttime > ? AND
                b.starttime <= ? AND
                $extraCriteria AND
                bg.includesubprojectotal = 1";
        $params = array_merge($params, [intval($this->ProjectId), $startUTCdate, $endUTCdate]);
        if ($allSubProjects) {
            $query .= ' GROUP BY subprojectid';
        }

        $db = Database::getInstance();
        $project = $db->executePrepared($query, $params);

        if ($project === false) {
            add_last_sql_error("SubProject CommonBuildQuery($extraCriteria)");
            return false;
        }
        if ($allSubProjects) {
            $project_array = [];
            foreach ($project as $row) {
                $project_array[intval($row['subprojectid'])] = $row;
            }
            return $project_array;
        } else {
            return intval($project[0]['c']);
        }
    }

    /** Get the number of warning builds given a date range */
    public function GetNumberOfWarningBuilds($startUTCdate, $endUTCdate, bool $allSubProjects = false): int|array|false
    {
        $criteria = 'b.buildwarnings > 0';
        return $this->CommonBuildQuery($startUTCdate, $endUTCdate, $allSubProjects, $criteria);
    }

    /** Get the number of error builds given a date range */
    public function GetNumberOfErrorBuilds($startUTCdate, $endUTCdate, bool $allSubProjects = false): int|array|false
    {
        $criteria = 'b.builderrors > 0';
        return $this->CommonBuildQuery($startUTCdate, $endUTCdate, $allSubProjects, $criteria);
    }

    /** Get the number of failing builds given a date range */
    public function GetNumberOfPassingBuilds($startUTCdate, $endUTCdate, bool $allSubProjects = false): int|array|false
    {
        $criteria = 'b.builderrors = 0 AND b.buildwarnings = 0';
        return $this->CommonBuildQuery($startUTCdate, $endUTCdate, $allSubProjects, $criteria);
    }

    /** Get the number of failing configure given a date range */
    public function GetNumberOfWarningConfigures($startUTCdate, $endUTCdate, bool $allSubProjects = false): int|array|false
    {
        $criteria = 'b.configurewarnings > 0';
        return $this->CommonBuildQuery($startUTCdate, $endUTCdate, $allSubProjects, $criteria);
    }

    /** Get the number of failing configure given a date range */
    public function GetNumberOfErrorConfigures($startUTCdate, $endUTCdate, bool $allSubProjects = false): int|array|false
    {
        $criteria = 'b.configureerrors > 0';
        return $this->CommonBuildQuery($startUTCdate, $endUTCdate, $allSubProjects, $criteria);
    }

    /** Get the number of failing configure given a date range */
    public function GetNumberOfPassingConfigures($startUTCdate, $endUTCdate, bool $allSubProjects = false): int|array|false
    {
        $criteria = 'b.configureerrors = 0 AND b.configurewarnings = 0';
        return $this->CommonBuildQuery($startUTCdate, $endUTCdate, $allSubProjects, $criteria);
    }

    /** Get the number of tests given a date range */
    public function GetNumberOfPassingTests($startUTCdate, $endUTCdate, bool $allSubProjects = false): int|array|false
    {
        if (!$allSubProjects && $this->Id < 1) {
            return false;
        }

        $params = [];
        $queryStr = 'SELECT ';
        if ($allSubProjects) {
            $queryStr .= 'subprojectid, ';
        }
        $queryStr .= 'SUM(build.testpassed) AS s FROM build, subproject2build ,build2group, buildgroup WHERE ';
        if (!$allSubProjects) {
            $queryStr .= 'subprojectid=? AND ';
            $params[] = intval($this->Id);
        }

        $queryStr .= "build2group.buildid=build.id
                  AND build2group.groupid=buildgroup.id
                  AND buildgroup.includesubprojectotal=1
                  AND subproject2build.buildid=build.id
                  AND build.starttime>?
                  AND build.starttime<=?
                  AND build.testpassed>=0 ";
        $params[] = $startUTCdate;
        $params[] = $endUTCdate;

        if ($allSubProjects) {
            $queryStr .= 'GROUP BY subprojectid';
        }

        $db = Database::getInstance();
        $project = $db->executePrepared($queryStr, $params);

        if ($project === false) {
            add_last_sql_error('SubProject GetNumberOfPassingTests');
            return false;
        }
        if ($allSubProjects) {
            $project_array = [];
            foreach ($project as $row) {
                $project_array[intval($row['subprojectid'])] = $row;
            }
            return $project_array;
        } else {
            return intval($project[0]['s']);
        }
    }

    /** Get the number of tests given a date range */
    public function GetNumberOfFailingTests($startUTCdate, $endUTCdate, bool $allSubProjects = false): int|array|false
    {
        if (!$allSubProjects && $this->Id < 1) {
            return false;
        }

        $params = [];
        $queryStr = 'SELECT ';
        if ($allSubProjects) {
            $queryStr .= 'subprojectid, ';
        }
        $queryStr .= 'SUM(build.testfailed) AS s FROM build, subproject2build, build2group, buildgroup WHERE ';
        if (!$allSubProjects) {
            $queryStr .= 'subprojectid=? AND ';
            $params[] = intval($this->Id);
        }

        $queryStr .= "build2group.buildid=build.id
                  AND build2group.groupid=buildgroup.id
                  AND buildgroup.includesubprojectotal=1
                  AND subproject2build.buildid=build.id
                  AND build.starttime>?
                  AND build.starttime<=?
                  AND build.testfailed>=0 ";
        $params[] = $startUTCdate;
        $params[] = $endUTCdate;

        if ($allSubProjects) {
            $queryStr .= 'GROUP BY subprojectid';
        }

        $db = Database::getInstance();
        $project = $db->executePrepared($queryStr, $params);

        if ($project === false) {
            add_last_sql_error('SubProject GetNumberOfFailingTests');
            return false;
        }
        if ($allSubProjects) {
            $project_array = [];
            foreach ($project as $row) {
                $project_array[intval($row['subprojectid'])] = $row;
            }
            return $project_array;
        } else {
            return intval($project[0]['s']);
        }
    }

    /** Get the number of tests given a date range */
    public function GetNumberOfNotRunTests($startUTCdate, $endUTCdate, bool $allSubProjects = false): int|array|false
    {
        if (!$allSubProjects && $this->Id < 1) {
            return false;
        }

        $params = [];
        $queryStr = 'SELECT ';
        if ($allSubProjects) {
            $queryStr .= 'subprojectid, ';
        }
        $queryStr .= 'SUM(build.testnotrun) AS s FROM build, subproject2build, build2group, buildgroup WHERE ';
        if (!$allSubProjects) {
            $queryStr .= 'subprojectid=? AND ';
            $params[] = $this->Id;
        }

        $queryStr .= "build2group.buildid=build.id
                  AND build2group.groupid=buildgroup.id
                  AND buildgroup.includesubprojectotal=1
                  AND subproject2build.buildid=build.id
                  AND build.starttime>?
                  AND build.starttime<=?
                  AND build.testnotrun>=0 ";
        $params[] = $startUTCdate;
        $params[] = $endUTCdate;

        if ($allSubProjects) {
            $queryStr .= 'GROUP BY subprojectid';
        }

        $db = Database::getInstance();
        $project = $db->executePrepared($queryStr, $params);

        if ($project === false) {
            add_last_sql_error('SubProject GetNumberOfNotRunTests');
            return false;
        }
        if ($allSubProjects) {
            $project_array = [];
            foreach ($project as $row) {
                $project_array[intval($row['subprojectid'])] = $row;
            }
            return $project_array;
        } else {
            return intval($project[0]['s']);
        }
    }

    /** Get the subprojectids of the subprojects depending on this one */
    public function GetDependencies(?string $date = null): array|false
    {
        if ($this->Id < 1) {
            add_log(
                "Id='" . $this->Id . "' not set",
                'SubProject::GetDependencies',
                LOG_WARNING);
            return false;
        }

        // If not set, the date is now
        if ($date === null) {
            $date = gmdate(FMT_DATETIME);
        }

        $db = Database::getInstance();
        $project = $db->executePrepared("
                       SELECT dependsonid
                       FROM subproject2subproject
                       WHERE
                           subprojectid=?
                           AND starttime<=?
                           AND (
                               endtime>?
                               OR endtime='1980-01-01 00:00:00'
                           )
                       ", [intval($this->Id), $date, $date]);

        if ($project === false) {
            add_last_sql_error('SubProject GetDependencies');
            return false;
        }

        $ids = [];
        foreach ($project as $project_array) {
            $ids[] = intval($project_array['dependsonid']);
        }
        return $ids;
    }

    /** Add a dependency */
    public function AddDependency(int $subprojectid): bool
    {
        if ($this->Id < 1) {
            return false;
        }

        $db = Database::getInstance();

        // Check that the dependency doesn't exist
        $project = $db->executePreparedSingleRow("
                       SELECT count(*) AS c
                       FROM subproject2subproject
                       WHERE
                           subprojectid=?
                           AND dependsonid=?
                           AND endtime='1980-01-01 00:00:00'
                   ", [intval($this->Id), $subprojectid]);

        if ($project === false) {
            add_last_sql_error('SubProject AddDependency');
            return false;
        }

        if (intval($project['c']) > 0) {
            return false;
        }

        // Add the dependency
        $starttime = gmdate(FMT_DATETIME);
        $project = $db->executePrepared("
                       INSERT INTO subproject2subproject (subprojectid, dependsonid, starttime, endtime)
                       VALUES (?, ?, '$starttime', '1980-01-01 00:00:00')
                   ", [intval($this->Id), intval($subprojectid)]);
        if ($project === false) {
            add_last_sql_error('SubProject AddDependency');
            return false;
        }
        return true;
    }

    /** Remove a dependency */
    public function RemoveDependency(int $subprojectid): bool
    {
        if ($this->Id < 1) {
            return false;
        }

        $db = Database::getInstance();

        // Set the date of the dependency to be now
        $now = gmdate(FMT_DATETIME);
        $project = $db->executePrepared("
                       UPDATE subproject2subproject
                       SET endtime=?
                       WHERE
                           subprojectid=?
                           AND dependsonid=?
                           AND endtime='1980-01-01 00:00:00'
                   ", [$now, intval($this->Id), $subprojectid]);

        if ($project === false) {
            add_last_sql_error('SubProject RemoveDependency');
            return false;
        }
        return true;
    }

    /** Return a subproject object for a given file path and projectid. */
    public static function GetSubProjectFromPath(string $path, int $projectid): SubProject|null
    {
        $pdo = Database::getInstance()->getPdo();
        $stmt = $pdo->prepare(
            "SELECT id FROM subproject
            WHERE projectid = :projectid AND
            endtime = '1980-01-01 00:00:00' AND
            path != '' AND
            :path LIKE CONCAT('%',path,'%')");

        $stmt->bindValue(':projectid', $projectid);
        $stmt->bindValue(':path', $path);
        pdo_execute($stmt);
        $id = $stmt->fetchColumn();
        if (!$id) {
            add_log(
                "No SubProject found for '$path'", 'GetSubProjectFromPath',
                LOG_INFO, $projectid, 0);
            return null;
        }
        $subproject = new SubProject();
        $subproject->SetId($id);
        return $subproject;
    }

    /**
     * Return the name of the subproject whose path contains the specified
     * source file.
     */
    public static function GetSubProjectForPath(string $filepath, int $projectid): string
    {
        $pdo = get_link_identifier()->getPdo();
        // Get all the subprojects for this project that have a path defined.
        // Sort by longest paths first.
        $stmt = $pdo->prepare(
            "SELECT name, path FROM subproject
            WHERE projectid = ? AND path != ''
            ORDER BY CHAR_LENGTH(path) DESC");
        pdo_execute($stmt, [$projectid]);
        while ($row = $stmt->fetch()) {
            // Return the name of the subproject with the longest path
            // that matches our input path.
            if (str_contains($filepath, $row['path'])) {
                return $row['name'];
            }
        }

        // Return empty string if no match was found.
        return '';
    }
}
