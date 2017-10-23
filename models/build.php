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
include_once 'include/common.php';
include_once 'include/ctestparserutils.php';
include_once 'include/repository.php';
include_once 'models/builderror.php';
include_once 'models/buildfailure.php';
include_once 'models/builderrordiff.php';
include_once 'models/buildinformation.php';
include_once 'models/buildusernote.php';
include_once 'models/constants.php';
include_once 'models/label.php';
include_once 'models/subproject.php';
include_once 'models/test.php';
include_once 'models/uploadfile.php';

class Build
{
    const TYPE_ERROR = 0;
    const TYPE_WARN = 1;
    const STATUS_NEW = 1;

    const PARENT_BUILD = -1;
    const STANDALONE_BUILD = 0;

    public $Id;
    public $SiteId;
    public $ProjectId;
    private $ParentId;
    public $Uuid;
    private $Stamp;
    public $Name;
    public $Type;
    public $Generator;
    public $StartTime;
    public $EndTime;
    public $SubmitTime;
    public $Command;
    public $Log;
    public $Information;
    public $BuildErrorCount;
    public $TestFailedCount;

    // For the moment we accept only one group per build
    public $GroupId;

    public $Errors;
    public $ErrorDiffs;
    public $MissingTests;

    public $SubProjectId;
    public $SubProjectName;
    public $Append;
    public $Done;
    public $Labels;

    // Only the build.xml has information about errors and warnings
    // when the InsertErrors is false the build is created but not the errors and warnings
    public $InsertErrors;

    // Used to comment on pull/merge requests when something goes wrong
    // with this build.
    private $PullRequest;

    // Used to mark whether this object already has its fields set.
    public $Filled;

    // Not set by FillFromId(), but cached the first time they are
    // computed.
    public $NightlyStartTime;
    public $BeginningOfDay;
    public $EndOfDay;
    private $Failures;
    private $PDO;

    public function __construct()
    {
        $this->ProjectId = 0;
        $this->Errors = array();
        $this->ErrorDiffs = array();
        $this->Append = false;
        $this->InsertErrors = true;
        $this->Filled = false;
        $this->PDO = get_link_identifier()->getPdo();
    }

    public function IsParentBuild()
    {
        return $this->ParentId == -1;
    }

    public function AddError($error)
    {
        $error->BuildId = $this->Id;
        $this->Errors[] = $error;
    }

    public function AddLabel($label)
    {
        if (!isset($this->Labels)) {
            $this->Labels = array();
        }
        $label->BuildId = $this->Id;
        $this->Labels[] = $label;
    }

    public function SetStamp($stamp)
    {
        $this->Stamp = $stamp;
        if (strlen($this->Type) == 0) {
            $this->Type = extract_type_from_buildstamp($this->Stamp);
        }
    }

    public function GetStamp()
    {
        return $this->Stamp;
    }

    /** Set the subproject id */
    public function SetSubProject($subproject)
    {
        if (!empty($this->SubProjectId)) {
            return $this->SubProjectId;
        }

        if (empty($subproject)) {
            return false;
        }

        if (empty($this->ProjectId)) {
            add_log('ProjectId not set' . $subproject, 'Build::SetSubProject', LOG_ERR,
                $this->ProjectId, $this->Id,
                CDASH_OBJECT_BUILD, $this->Id);
            return false;
        }

        $query = pdo_query(
            "SELECT id FROM subproject WHERE name='$subproject' AND " .
            'projectid=' . qnum($this->ProjectId) . " AND endtime='1980-01-01 00:00:00'"
        );
        if (!$query) {
            add_last_sql_error('Build:SetSubProject()', $this->ProjectId);
            return false;
        }

        $this->SubProjectName = $subproject;
        $label = new Label;
        $label->Text = $subproject;
        $this->AddLabel($label);

        // Add this subproject as a label on the parent build.
        $this->SetParentId($this->LookupParentBuildId());
        if ($this->ParentId > 0) {
            $parent = new Build();
            $parent->Id = $this->ParentId;
            $parent->AddLabel($label);
            $parent->InsertLabelAssociations();
        }

        if (pdo_num_rows($query) > 0) {
            $query_array = pdo_fetch_array($query);
            $this->SubProjectId = $query_array['id'];
            return $this->SubProjectId;
        }

        // If the subproject wasn't found, add it here.
        // A proper Project.xml file will still need to be uploaded later to
        // load dependency data.
        $subProject = new SubProject();
        $subProject->SetProjectId($this->ProjectId);
        $subProject->SetName($subproject);
        $subProject->Save();

        // Insert the label too.
        $Label = new Label;
        $Label->Text = $subProject->GetName();
        $Label->Insert();

        add_log('New subproject detected: ' . $subproject, 'Build::SetSubProject',
            LOG_INFO, $this->ProjectId, $this->Id, CDASH_OBJECT_BUILD, $this->Id);
        return true;
    }

    /** Return the subproject id */
    public function GetSubProjectName()
    {
        if (empty($this->Id)) {
            return false;
        }

        if (!empty($this->SubProjectName)) {
            return $this->SubProjectName;
        }

        $query = pdo_query('SELECT name FROM subproject,subproject2build WHERE subproject.id=subproject2build.subprojectid
                AND subproject2build.buildid=' . qnum($this->Id));
        if (!$query) {
            add_last_sql_error('Build:GetSubProjectName()', $this->ProjectId, $this->Id);
            return false;
        }

        if (pdo_num_rows($query) > 0) {
            $query_array = pdo_fetch_array($query);
            $this->SubProjectName = $query_array['name'];
            return $this->SubProjectName;
        }
        return false;
    }

    /** Update the total testing duration */
    public function SaveTotalTestsTime($duration, $update_parent=true)
    {
        if (!$this->Id || !is_numeric($this->Id)) {
            return false;
        }

        // Check if an entry already exists for this build.
        $query = pdo_query(
            'SELECT buildid FROM buildtesttime
                WHERE buildid=' . qnum($this->Id));
        if (!$query) {
            add_last_sql_error('SaveTotalTestsTime',
                $this->ProjectId, $this->Id);
            return false;
        }

        if (pdo_num_rows($query) > 0) {
            $query =
                "UPDATE buildtesttime SET time = time + $duration
                WHERE buildid=" . qnum($this->Id);
        } else {
            $query = "INSERT INTO buildtesttime (buildid, time)
                VALUES ('" . $this->Id . "','" . $duration . "')";
        }
        if (!pdo_query($query)) {
            add_last_sql_error('Build:SaveTotalTestsTime', $this->ProjectId, $this->Id);
            return false;
        }

        if (!$update_parent) {
            return true;
        }
        // If this is a child build, add this duration
        // to the parent's test duration sum.
        $this->SetParentId($this->LookupParentBuildId());
        if ($this->ParentId > 0) {
            $parent = new Build();
            $parent->Id = $this->ParentId;
            $parent->SaveTotalTestsTime($duration);
        }
    }

    /** Update the end time */
    public function UpdateEndTime($end_time)
    {
        if (!$this->Id || !is_numeric($this->Id)) {
            return false;
        }

        $query = "UPDATE build SET endtime='$end_time' WHERE id='$this->Id'";
        if (!pdo_query($query)) {
            add_last_sql_error('Build:UpdateEndTime', $this->ProjectId, $this->Id);
            return false;
        }
    }

    public function QuerySubProjectId($buildid)
    {
        $query = pdo_query(
            'SELECT id FROM subproject, subproject2build ' .
            'WHERE subproject.id=subproject2build.subprojectid AND subproject2build.buildid=' . qnum($buildid));
        if (!$query) {
            add_last_sql_error('Build:QuerySubProjectId', $this->ProjectId, $buildid);
            return false;
        }
        $query_array = pdo_fetch_array($query);
        return $query_array['id'];
    }

    /** Fill the current build information from the buildid */
    public function FillFromId($buildid)
    {
        if ($this->Filled) {
            // Already filled, no need to do it again.
            return false;
        }

        $query = pdo_query(
            'SELECT
                projectid,
                starttime,
                endtime,
                siteid,
                name,
                stamp,
                type,
                parentid,
                done,
                builderrors,
                testfailed,
                generator,
                command
            FROM build
            WHERE id=' . qnum($buildid));

        if (!$query) {
            add_last_sql_error('Build:FillFromId()', $this->ProjectId, $this->Id);
            return false;
        }

        $build_array = pdo_fetch_array($query);
        $this->Name = $build_array['name'];
        $this->SetStamp($build_array['stamp']);
        $this->Type = $build_array['type'];
        $this->StartTime = $build_array['starttime'];
        $this->EndTime = $build_array['endtime'];
        $this->SiteId = $build_array['siteid'];
        $this->ProjectId = $build_array['projectid'];
        $this->SetParentId($build_array['parentid']);
        $this->Done = $build_array['done'];
        $this->Generator = $build_array['generator'];
        $this->Command = $build_array['command'];
        $this->BuildErrorCount = $build_array['builderrors'];
        $this->TestFailedCount = $build_array['testfailed'];

        $subprojectid = $this->QuerySubProjectId($buildid);
        if ($subprojectid) {
            $this->SubProjectId = $subprojectid;
        }

        $result = pdo_fetch_array(pdo_query(
            "SELECT groupid FROM build2group WHERE buildid='$buildid'"));
        $this->GroupId = $result['groupid'];
        $this->Filled = true;
    }

    /**
     * @param Build $build
     * @param array $optional_values
     * @return array
     */
    public static function MarshalResponseArray($build, $optional_values = [])
    {
        $response = [
            'id' => $build->Id,
            'buildid' => $build->Id,
            'siteid' => $build->SiteId,
            'name' => $build->Name,
            'buildname' => $build->Name,
            'stamp' => $build->Stamp,
            'projectid' => $build->ProjectId,
            'starttime' => $build->StartTime,
            'endtime' => $build->EndTime,
            'groupid' => $build->GroupId,

        ];

        return array_merge($response, $optional_values);
    }

    /** Get the previous build id. */
    public function GetPreviousBuildId($previous_parentid=null)
    {
        if (!$this->Id) {
            return 0;
        }
        $this->FillFromId($this->Id);

        $previous_clause =
            "AND starttime<'$this->StartTime' ORDER BY starttime DESC";
        return $this->GetRelatedBuildId($previous_clause, $previous_parentid);
    }

    /** Get the next build id. */
    public function GetNextBuildId($next_parentid=null)
    {
        if (!$this->Id) {
            return 0;
        }
        $this->FillFromId($this->Id);

        $next_clause = "AND starttime>'$this->StartTime' ORDER BY starttime";
        return $this->GetRelatedBuildId($next_clause, $next_parentid);
    }

    /** Get the most recent build id. */
    public function GetCurrentBuildId($current_parentid=null)
    {
        if (!$this->Id) {
            return 0;
        }
        $this->FillFromId($this->Id);

        $current_clause = 'ORDER BY starttime DESC';
        return $this->GetRelatedBuildId($current_clause, $current_parentid);
    }

    /** Private helper function to encapsulate the common parts of
     * Get{Previous,Next,Current}BuildId()
     **/
    private function GetRelatedBuildId($which_build_criteria,
            $related_parentid=null)
    {
        $related_build_criteria =
            "WHERE siteid=" . qnum($this->SiteId) . "
            AND type='$this->Type'
            AND name='$this->Name'
            AND projectid=" . qnum($this->ProjectId);

        // Take subproject into account, such that if there is one, then the
        // previous build must be associated with the same subproject...
        //
        if ($this->SubProjectId && !$related_parentid) {
            // Look up the related parent.  This makes it easy to find the
            // corresponding child build.
            $parent_query = pdo_query("
                    SELECT id FROM build
                    $related_build_criteria
                    AND build.parentid=-1
                    $which_build_criteria
                    LIMIT 1");
            if (pdo_num_rows($parent_query) < 1) {
                return 0;
            }
            $parent_array = pdo_fetch_array($parent_query);
            $related_parentid = $parent_array['id'];
        }

        $subproj_table = '';
        $subproj_criteria = '';
        $parent_criteria = '';

        if ($this->SubProjectId) {
            $subproj_table =
                'INNER JOIN subproject2build AS sp2b ON (build.id=sp2b.buildid)';
            $subproj_criteria =
                'AND sp2b.subprojectid=' . qnum($this->SubProjectId) . ' ';
        }
        if ($this->ParentId == -1) {
            // Only search for other parents.
            $parent_criteria = 'AND build.parentid=-1';
        }

        // If we know the parent of the build we're looking for, use that as our
        // search criteria rather than matching site, name, type, and project.
        if ($related_parentid) {
            $related_build_criteria =
                "WHERE parentid=" . qnum($related_parentid);
        }

        $query = pdo_query("
                SELECT id FROM build
                $subproj_table
                $related_build_criteria
                $subproj_criteria
                $parent_criteria
                $which_build_criteria
                LIMIT 1");

        if (!$query) {
            add_last_sql_error(
                'Build:GetRelatedBuildId', $this->ProjectId, $this->Id);
            return 0;
        }

        if (pdo_num_rows($query) > 0) {
            $relatedbuild_array = pdo_fetch_array($query);
            return $relatedbuild_array['id'];
        }
        return 0;
    }

    /**
     * Return the errors that have been resolved from this build.
     * @todo This doesn't support getting resolved build errors across parent builds.
     **/
    public function GetResolvedBuildErrors($type)
    {
        // This returns an empty result if there was no previous build
        return pdo_query(
            'SELECT * FROM
             (SELECT * FROM builderror
              WHERE buildid=' . $this->GetPreviousBuildId() . ' AND type=' . $type . ') AS builderrora
             LEFT JOIN
             (SELECT crc32 AS crc32b FROM builderror
              WHERE buildid=' . $this->Id . ' AND type=' . $type . ') AS builderrorb
              ON builderrora.crc32=builderrorb.crc32b
             WHERE builderrorb.crc32b IS NULL');
    }

    /**
     * Returns all errors, including warnings, from the database, caches, and
     * returns the filtered results
     *
     * @param int $fetchStyle
     * @param array $filters
     * @return array|bool
     */
    public function GetErrors(array $propertyFilters = [], $fetchStyle = PDO::FETCH_ASSOC)
    {
        // This needs to take into account that this build may be a parent build
        if (!$this->Errors) {
            if (!$this->Id) {
                add_log('BuildId not set', 'Build::GetErrors', LOG_WARNING);
                return false;
            }

            if ($this->IsParentBuild()) {
                $this->Errors = $this->GetErrorsForChildren($fetchStyle);
            } else {
                $buildErrors = new BuildError();
                $buildErrors->BuildId = $this->Id;
                $this->Errors = $buildErrors->GetErrorsForBuild($fetchStyle);
            }
        }
        return $this->PropertyFilter($this->Errors, $propertyFilters);
    }

    /**
     * Returns all failures (errors), including warnings, for current build
     *
     * @param int $fetchStyle
     * @return array|bool
     */
    public function GetFailures(array $propertyFilters = [], $fetchStyle = PDO::FETCH_ASSOC)
    {
        // This needs to take into account that this build may be a parent build
        if (!$this->Failures) {
            if (!$this->Id) {
                add_log('BuildId not set', 'Build::GetFailures', LOG_WARNING);
                return false;
            }

            if ($this->isParentBuild()) {
                $this->Failures = $this->GetFailuresForChildren($fetchStyle);
            } else {
                $buildFailure = new BuildFailure();
                $buildFailure->BuildId = $this->Id;
                $this->Failures = $buildFailure->GetFailuresForBuild($fetchStyle);
            }
        }
        return $this->PropertyFilter($this->Failures, $propertyFilters);
    }

    /**
     * Apply filter to rows
     *
     * @param array $filters
     * @return array
     */
    protected function PropertyFilter(array $rows, array $filters)
    {
        return array_filter($rows, function ($row) use ($filters) {
            foreach ($filters as $prop => $value) {
                if (is_object($row)) {
                    if (!property_exists($row, $prop)) {
                        add_log("Cannot filter on {$prop}: property does not exist", 'Build::PropertyFilter', LOG_WARNING);
                        continue;
                    }

                    if ($row->$prop != $value) {
                        return false;
                    }
                } elseif (is_array($row)) {
                    if (!array_key_exists($prop, $row)) {
                        add_log("Cannot filter on {$prop}: property does not exist", 'Build::PropertyFilter', LOG_WARNING);
                        continue;
                    }

                    if ($row[$prop] != $value) {
                        return false;
                    }
                }
            }
            return true;
        });
    }

    /**
     * Get build failures (with details) that occurred in the most recent build
     * but NOT this build.
     * @todo This doesn't support getting resolved build failures across parent builds.
     **/
    public function GetResolvedBuildFailures($type)
    {
        $currentFailuresQuery = "SELECT bf.detailsid FROM buildfailure AS bf
                                 LEFT JOIN buildfailuredetails AS bfd ON (bf.detailsid=bfd.id)
                                 WHERE bf.buildid=" . $this->Id . " AND bfd.type=$type";

        $resolvedBuildFailures = pdo_query(
                "SELECT bf.id, bfd.language, bf.sourcefile, bfd.targetname, bfd.outputfile,
                bfd.outputtype, bf.workingdirectory, bfd.stderror, bfd.stdoutput,
                bfd.exitcondition
                FROM buildfailure AS bf
                LEFT JOIN buildfailuredetails AS bfd ON (bfd.id=bf.detailsid)
                WHERE bf.buildid=" . $this->GetPreviousBuildId() . "
                AND bfd.type = $type
                AND bfd.id NOT IN ($currentFailuresQuery)"
        );

        return $resolvedBuildFailures;
    }

    public function GetConfigures()
    {
        if ($this->IsParentBuild()) {
            // Count how many separate configure rows are associated with
            // this parent build.
            $configures_stmt = $this->PDO->prepare('
                SELECT DISTINCT c.id FROM configure c
                JOIN build2configure b2c ON b2c.configureid = c.id
                JOIN build b ON b.id = b2c.buildid
                WHERE b.parentid = ?');
            pdo_execute($configures_stmt, [$this->Id]);
            $configure_rows = $configures_stmt->fetchAll();
            if (count($configure_rows) > 1) {
                // Each SubProject build has its own configure row.
                $stmt = $this->PDO->prepare('
                    SELECT sp.name subprojectname, sp.id subprojectid, c.*,
                           b.configureerrors, b.configurewarnings
                    FROM configure c
                    JOIN build2configure b2c ON b2c.configureid = c.id
                    JOIN subproject2build sp2b ON sp2b.buildid = b2c.buildid
                    JOIN subproject sp ON sp.id = sp2b.subprojectid
                    JOIN build b ON b.id = b2c.buildid
                    WHERE b.parentid = ?');
            } else {
                // One configure row is shared by all the SubProjects.
                $stmt = $this->PDO->prepare('
                    SELECT c.*, b.configureerrors, b.configurewarnings
                    FROM configure c
                    JOIN build2configure b2c ON b2c.configureid = c.id
                    JOIN build b ON b.id = b2c.buildid
                    WHERE c.id = ?');
                pdo_execute($stmt, [$configure_rows[0]['id']]);
                return $stmt;
            }
        } else {
            $stmt = $this->PDO->prepare('
                SELECT c.*, b.configureerrors, b.configurewarnings
                FROM configure c
                JOIN build2configure b2c ON b2c.configureid = c.id
                JOIN build b ON b.id = b2c.buildid
                WHERE b2c.buildid = ?');
        }
        pdo_execute($stmt, [$this->Id]);
        return $stmt;
    }

    /** Get the build id from its name */
    public function GetIdFromName($subproject)
    {
        // Make sure subproject name and id fields are set:
        //
        $this->SetSubProject($subproject);

        $stmt = null;
        $params = [$this->ProjectId, $this->SiteId, $this->Name, $this->Stamp];

        if ($this->SubProjectId != 0) {
            $stmt = $this->PDO->prepare(
                'SELECT id FROM build
                JOIN subproject2build ON subproject2build.buildid = build.id
                WHERE projectid = ? AND siteid = ? AND name = ? AND
                      stamp = ? AND subprojectid = ?');
            $params[] = $this->SubProjectId;
        } else {
            $stmt = $this->PDO->prepare(
                'SELECT id FROM build
                WHERE projectid = ? AND siteid = ? AND name = ? AND stamp = ?
                AND parentid IN (0, -1)');
        }
        pdo_execute($stmt, $params);
        $id = $stmt->fetchColumn();
        if ($id > 0) {
            $this->Id = $id;
            return $this->Id;
        }

        return 0;
    }

    public function InsertLabelAssociations()
    {
        if ($this->Id) {
            if (!isset($this->Labels)) {
                return;
            }

            foreach ($this->Labels as $label) {
                $label->BuildId = $this->Id;
                $label->Insert();
            }
        } else {
            add_log('No Build::Id - cannot call $label->Insert...', 'Build::InsertLabelAssociations', LOG_ERR,
                $this->ProjectId, $this->Id,
                CDASH_OBJECT_BUILD, $this->Id);
            return false;
        }
    }

    /** Return if exists */
    public function Exists()
    {
        if (!$this->Id) {
            return false;
        }
        $query = pdo_query("SELECT count(*) FROM build WHERE id='" . $this->Id . "'");
        add_last_sql_error('Build::Exists', $this->ProjectId, $this->Id);

        $query_array = pdo_fetch_array($query);
        if ($query_array[0] > 0) {
            return true;
        }
        return false;
    }

    // Save in the database
    public function Save()
    {
        $this->StartTime = pdo_real_escape_string($this->StartTime);
        $this->EndTime = pdo_real_escape_string($this->EndTime);
        $this->SubmitTime = pdo_real_escape_string($this->SubmitTime);
        $this->Command = pdo_real_escape_string(trim($this->Command));
        $this->Log = pdo_real_escape_string(trim($this->Log));

        // Compute the number of errors and warnings.
        // This speeds up the display of the main table.
        $nbuilderrors = -1;
        $nbuildwarnings = -1;
        if ($this->InsertErrors) {
            $nbuilderrors = 0;
            $nbuildwarnings = 0;
            foreach ($this->Errors as $error) {
                if ($error->Type == 0) {
                    $nbuilderrors++;
                } else {
                    $nbuildwarnings++;
                }
            }
        }

        if (!$this->Exists()) {
            $id = '';
            $idvalue = '';
            if ($this->Id) {
                $id = 'id,';
                $idvalue = qnum($this->Id) . ',';
            }

            if (strlen($this->Type) == 0) {
                $this->Type = extract_type_from_buildstamp($this->Stamp);
            }

            $this->Name = pdo_real_escape_string($this->Name);
            $this->Stamp = pdo_real_escape_string($this->Stamp);
            $this->Type = pdo_real_escape_string($this->Type);
            $this->Generator = pdo_real_escape_string($this->Generator);

            $this->SetParentId(0);
            $justCreatedParent = false;
            if ($this->SubProjectName) {
                $this->SetParentId($this->LookupParentBuildId());
                if ($this->ParentId == 0) {
                    // This is the first subproject to submit for a new build.
                    // Create a new parent build for it.
                    $justCreatedParent = $this->CreateParentBuild($nbuilderrors, $nbuildwarnings);
                }
            }
            $this->Uuid = Build::GenerateUuid($this->Stamp, $this->Name,
                $this->SiteId, $this->ProjectId, $this->SubProjectName);

            $query =
                'INSERT INTO build
                (' . $id . 'siteid, projectid, stamp, name, type, generator,
                 starttime, endtime, submittime, command, log, builderrors,
                 buildwarnings, parentid, uuid, changeid)
                VALUES
                (' . $idvalue . "'$this->SiteId', '$this->ProjectId',
                 '$this->Stamp', '$this->Name', '$this->Type',
                 '$this->Generator', '$this->StartTime', '$this->EndTime',
                 '$this->SubmitTime', '$this->Command', '$this->Log',
                 $nbuilderrors, $nbuildwarnings, $this->ParentId,
                 '$this->Uuid', '$this->PullRequest')";

            if (!pdo_query($query)) {
                $error = pdo_error(null, false);
                // This error might be due to a unique constraint violation
                // for this UUID.  Query for such a previously existing build.
                $existing_id_result = pdo_single_row_query(
                    "SELECT id FROM build WHERE uuid = '$this->Uuid'");
                if ($existing_id_result &&
                    array_key_exists('id', $existing_id_result)
                ) {
                    $this->Id = $existing_id_result['id'];
                    // If a previously existing build with this UUID was found
                    // call UpdateBuild() on it.  This also sets ParentId
                    // if an existing parent was found.
                    $this->UpdateBuild($this->Id,
                        $nbuilderrors, $nbuildwarnings);
                    // Does the parent still need to be created?
                    if ($this->SubProjectName && $this->ParentId < 1) {
                        if (!$this->CreateParentBuild(
                            $nbuilderrors, $nbuildwarnings)
                        ) {
                            // Someone else created the parent after we called
                            // UpdateBuild(this->Id,...).
                            // In this case we also need to manually update
                            // the parent as well.
                            $this->UpdateBuild($this->ParentId,
                                $nbuilderrors, $nbuildwarnings);
                        }
                    }
                    // Now that the existing build and its parent (if any) have
                    // been updated we can return early.
                    return true;
                }
                add_log("SQL error: $error", 'Build Insert', LOG_ERR, $this->ProjectId, $this->Id);
                return false;
            }

            if (!$this->Id) {
                $this->Id = pdo_insert_id('build');
            }

            // Add the groupid
            if ($this->GroupId) {
                $query = "INSERT INTO build2group (groupid,buildid) VALUES ('$this->GroupId','$this->Id')";
                if (!pdo_query($query)) {
                    add_last_sql_error('Build2Group Insert', $this->ProjectId, $this->Id);
                }
                // Associate the parent with this group too.
                if ($this->ParentId > 0) {
                    $result = pdo_query(
                        'SELECT groupid FROM build2group WHERE buildid=' . qnum($this->ParentId));
                    if (pdo_num_rows($result) == 0) {
                        global $CDASH_DB_TYPE;
                        $duplicate_sql = '';
                        if ($CDASH_DB_TYPE !== 'pgsql') {
                            $duplicate_sql =
                                'ON DUPLICATE KEY UPDATE groupid=groupid';
                        }
                        $query =
                            "INSERT INTO build2group (groupid,buildid)
                            VALUES ('$this->GroupId','$this->ParentId')
                            $duplicate_sql";
                        if (!pdo_query($query)) {
                            add_last_sql_error('Parent Build2Group Insert', $this->ProjectId, $this->ParentId);
                        }
                    }
                }
            }

            // Add the subproject2build relationship:
            if ($this->SubProjectId) {
                $query = "INSERT INTO subproject2build (subprojectid,buildid) VALUES ('$this->SubProjectId','$this->Id')";
                if (!pdo_query($query)) {
                    add_last_sql_error('SubProject2Build Insert', $this->ProjectId, $this->Id);
                }
            }

            // Save the information
            if (!empty($this->Information)) {
                if ($this->ParentId > 0) {
                    $this->Information->BuildId = $this->ParentId;
                } else {
                    $this->Information->BuildId = $this->Id;
                }
                $this->Information->Save();
            }

            // Update parent's tally of total build errors & warnings.
            if (!$justCreatedParent) {
                $this->UpdateBuild($this->ParentId, $nbuilderrors, $nbuildwarnings);
            } elseif ($this->ParentId > 0) {
                // If we just created a child build, associate it with
                // the parent's updates (if any).
                require_once 'models/buildupdate.php';
                BuildUpdate::AssignUpdateToChild($this->Id, $this->ParentId);
            }
        } else {
            // Build already exists.
            // Update this build and its parent (if necessary).
            $this->UpdateBuild($this->Id, $nbuilderrors, $nbuildwarnings);
        }

        // Add errors/warnings
        foreach ($this->Errors as $error) {
            $error->BuildId = $this->Id;
            $error->Insert();
        }

        // Add ErrorDiff
        foreach ($this->ErrorDiffs as $diff) {
            $diff->BuildId = $this->Id;
            $diff->Insert();
        }

        // Add label associations regardless of how Build::Save gets called:
        //
        $this->InsertLabelAssociations();

        // Should we post build errors to a pull request?
        if (isset($this->PullRequest)) {
            $hasErrors = false;
            foreach ($this->Errors as $error) {
                if ($error->Type == 0) {
                    $hasErrors = true;
                    break;
                }
            }

            if ($hasErrors) {
                $message = 'This build experienced errors';
                $url = get_server_URI(false) .
                    "/viewBuildError.php?buildid=$this->Id";
                $this->NotifyPullRequest($message, $url);
            }
        }
        return true;
    }

    /** Get number of failed tests */
    public function GetNumberOfFailedTests()
    {
        $result =
            pdo_query('SELECT testfailed FROM build WHERE id=' . qnum($this->Id));
        if (pdo_num_rows($result) > 0) {
            $build_array = pdo_fetch_array($result);
            $numTestsFailed = $build_array['testfailed'];
            if ($numTestsFailed < 0) {
                return 0;
            }
            return $numTestsFailed;
        }
        return 0;
    }

    /** Get number of passed tests */
    public function GetNumberOfPassedTests()
    {
        $result =
            pdo_query('SELECT testpassed FROM build WHERE id=' . qnum($this->Id));
        if (pdo_num_rows($result) > 0) {
            $build_array = pdo_fetch_array($result);
            $numTestsPassed = $build_array['testpassed'];
            if ($numTestsPassed < 0) {
                return 0;
            }
            return $numTestsPassed;
        }
        return 0;
    }

    /** Get number of not run tests */
    public function GetNumberOfNotRunTests()
    {
        $result =
            pdo_query('SELECT testnotrun FROM build WHERE id=' . qnum($this->Id));
        if (pdo_num_rows($result) > 0) {
            $build_array = pdo_fetch_array($result);
            $numTestsNotRun = $build_array['testnotrun'];
            if ($numTestsNotRun < 0) {
                return 0;
            }
            return $numTestsNotRun;
        }
        return 0;
    }

    /** Update the test numbers */
    public function UpdateTestNumbers($numberTestsPassed, $numberTestsFailed, $numberTestsNotRun)
    {
        if (!is_numeric($numberTestsPassed) || !is_numeric($numberTestsFailed) || !is_numeric($numberTestsNotRun)) {
            return;
        }

        // If this is a subproject build, we also have to update its parents test numbers.
        $newFailed = $numberTestsFailed - $this->GetNumberOfFailedTests();
        $newNotRun = $numberTestsNotRun - $this->GetNumberOfNotRunTests();
        $newPassed = $numberTestsPassed - $this->GetNumberOfPassedTests();
        $this->SetParentId($this->LookupParentBuildId());
        $this->UpdateParentTestNumbers($newFailed, $newNotRun, $newPassed);

        // Update this build's test numbers.
        pdo_query("UPDATE build SET testnotrun='$numberTestsNotRun',
                testfailed='$numberTestsFailed',
                testpassed='$numberTestsPassed' WHERE id=" . qnum($this->Id));

        add_last_sql_error('Build:UpdateTestNumbers', $this->ProjectId, $this->Id);

        // Should we should post test failures to a pull request?
        if (isset($this->PullRequest) && $numberTestsFailed > 0) {
            $message = 'This build experienced failing tests';
            $url = get_server_URI(false) .
                "/viewTest.php?onlyfailed&buildid=$this->Id";
            $this->NotifyPullRequest($message, $url);
        }
    }

    /**
     * Get missing tests' names relative to previous build
     *
     * @return array
     */
    public function GetMissingTests()
    {
        if (!$this->Id) {
            add_log('BuildId is not set', 'Build::GetMissingTests', LOG_ERR,
                $this->ProjectId, $this->Id, CDASH_OBJECT_BUILD, $this->Id);
            return false;
        }

        $previous_build_tests = [];
        $current_build_tests = [];

        $previous_build = $this->GetPreviousBuildId();

        $sql = "SELECT DISTINCT B.name FROM build2test A
            LEFT JOIN test B
              ON A.testid=B.id
            WHERE A.buildid=?
            ORDER BY B.name
         ";

        $pdo = get_link_identifier()->getPdo();
        $query = $pdo->prepare($sql);

        pdo_execute($query, [$previous_build]);
        foreach ($query->fetchAll(PDO::FETCH_OBJ) as $test) {
            $previous_build_tests[$test->name] = $test->name;
        }

        pdo_execute($query, [$this->Id]);
        foreach ($query->fetchAll(PDO::FETCH_OBJ) as $test) {
            $current_build_tests[$test->name] = $test->name;
        }

        $this->MissingTests = array_diff($previous_build_tests, $current_build_tests);
        return $this->MissingTests;
    }

    /**
     * Gut the number of missing tests relative to previous build
     *
     * @return int
     */
    public function GetNumberOfMissingTests()
    {
        if (!is_array($this->MissingTests)) {
            $this->GetMissingTests();
        }

        return count($this->MissingTests);
    }

    /**
     * Get this build's tests that match the supplied WHERE clause.
     *
     * @return array
     */
    private function GetTests($criteria, $maxitems = 0)
    {
        if (!$this->Id) {
            add_log('BuildId is not set', 'Build::GetTests', LOG_ERR,
                $this->ProjectId, $this->Id, CDASH_OBJECT_BUILD, $this->Id);
            return false;
        }

        $limit_clause = '';
        $limit = (int) trim($maxitems);
        if ($limit > 0) {
            $limit_clause = "LIMIT $limit";
        }

        $sql = "
            SELECT t.name, t.id, t.details
            FROM test t
            JOIN build2test b2t ON t.id = b2t.testid
            WHERE b2t.buildid = :buildid
            AND $criteria
            ORDER BY t.id
            $limit_clause";

        $query = $this->PDO->prepare($sql);
        $query->bindParam(':buildid', $this->Id);

        if (!pdo_execute($query)) {
            return [];
        }
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get this build's tests that failed but did not timeout.
     *
     * @return array
     */
    public function GetFailedTests($maxitems = 0)
    {
        $criteria = "b2t.status = 'failed'";
        return $this->GetTests($criteria, $maxitems);
    }

    /**
     * Get this build's tests that failed the time status check.
     *
     * @return array
     */
    public function GetFailedTimeStatusTests($maxitems = 0, $max_time_status = 3)
    {
        $max_time_status = (int) trim($max_time_status);
        $criteria = "b2t.timestatus > $max_time_status";
        return $this->GetTests($criteria, $maxitems);
    }

    /**
     * Get this build's tests whose details indicate a timeout.
     *
     * @return array
     */
    public function GetTimedoutTests($maxitems = 0)
    {
        $criteria = "b2t.status = 'failed' AND t.details LIKE '%%Timeout%%'";
        return $this->GetTests($criteria, $maxitems);
    }

    /**
     * Get this build's tests whose status is "Not Run" and whose details
     * is not 'Disabled'.
     *
     * @return array
     */
    public function GetNotRunTests($maxitems = 0)
    {
        $criteria = "b2t.status = 'notrun' AND t.details != 'Disabled'";
        return $this->GetTests($criteria, $maxitems);
    }

    /** Get the errors differences for the build */
    public function GetErrorDifferences()
    {
        if (!$this->Id) {
            add_log('BuildId is not set', 'Build::GetErrorDifferences', LOG_ERR,
                $this->ProjectId, $this->Id, CDASH_OBJECT_BUILD, $this->Id);
            return false;
        }

        $diff = array();

        $sqlquery = 'SELECT id,builderrordiff.type AS builderrortype,
            builderrordiff.difference_positive AS builderrorspositive,
            builderrordiff.difference_negative AS builderrorsnegative,
            configureerrordiff.type AS configureerrortype,
            configureerrordiff.difference AS configureerrors,
            testdiff.type AS testerrortype,
            testdiff.difference_positive AS testerrorspositive,
            testdiff.difference_negative AS testerrorsnegative
                FROM build
                LEFT JOIN builderrordiff ON builderrordiff.buildid=build.id
                LEFT JOIN configureerrordiff ON configureerrordiff.buildid=build.id
                LEFT JOIN testdiff ON testdiff.buildid=build.id
                WHERE id=' . qnum($this->Id);
        $query = pdo_query($sqlquery);
        add_last_sql_error('Build:GetErrorDifferences', $this->ProjectId, $this->Id);

        while ($query_array = pdo_fetch_array($query)) {
            if ($query_array['builderrortype'] == 0) {
                $diff['builderrorspositive'] = $query_array['builderrorspositive'];
                $diff['builderrorsnegative'] = $query_array['builderrorsnegative'];
            } else {
                $diff['buildwarningspositive'] = $query_array['builderrorspositive'];
                $diff['buildwarningsnegative'] = $query_array['builderrorsnegative'];
            }

            if ($query_array['configureerrortype'] == 0) {
                $diff['configureerrors'] = $query_array['configureerrors'];
            } else {
                $diff['configurewarnings'] = $query_array['configureerrors'];
            }

            if ($query_array['testerrortype'] == 2) {
                $diff['testpassedpositive'] = $query_array['testerrorspositive'];
                $diff['testpassednegative'] = $query_array['testerrorsnegative'];
            } elseif ($query_array['testerrortype'] == 1) {
                $diff['testfailedpositive'] = $query_array['testerrorspositive'];
                $diff['testfailednegative'] = $query_array['testerrorsnegative'];
            } elseif ($query_array['testerrortype'] == 0) {
                $diff['testnotrunpositive'] = $query_array['testerrorspositive'];
                $diff['testnotrunnegative'] = $query_array['testerrorsnegative'];
            }
        }

        // If some of the errors are not set default to zero
        $variables = array('builderrorspositive', 'builderrorsnegative',
            'buildwarningspositive', 'buildwarningsnegative',
            'configureerrors', 'configurewarnings',
            'testpassedpositive', 'testpassednegative',
            'testfailedpositive', 'testfailednegative',
            'testnotrunpositive', 'testnotrunnegative');
        foreach ($variables as $var) {
            if (!isset($diff[$var])) {
                $diff[$var] = 0;
            }
        }
        return $diff;
    }

    /** Compute the build errors differences */
    public function ComputeDifferences()
    {
        if (!$this->Id) {
            add_log('BuildId is not set', 'Build::ComputeDifferences', LOG_ERR,
                $this->ProjectId, $this->Id,
                CDASH_OBJECT_BUILD, $this->Id);
            return false;
        }

        $previousbuildid = $this->GetPreviousBuildId();
        if ($previousbuildid == 0) {
            return;
        }
        compute_error_difference($this->Id, $previousbuildid, 0); // errors
        compute_error_difference($this->Id, $previousbuildid, 1); // warnings
    }

    /** Compute the difference in configure warnings between this build and the
     *  previous one.
     *  TODO: we should probably also do configure errors here too.
     */
    public function ComputeConfigureDifferences()
    {
        if (!$this->Id) {
            add_log('BuildId is not set', 'Build::ComputeConfigureDifferences',
                LOG_ERR, $this->ProjectId, $this->Id, CDASH_OBJECT_BUILD,
                $this->Id);
            return false;
        }

        $previousbuildid = $this->GetPreviousBuildId();
        if ($previousbuildid == 0) {
            return;
        }

        // Look up the number of configure warnings for this build
        // and the previous one.
        $stmt = $this->PDO->prepare(
            'SELECT configurewarnings FROM build WHERE id = ?');

        pdo_execute($stmt, [$this->Id]);
        $row = $stmt->fetch();
        $nwarnings = $row['configurewarnings'];

        pdo_execute($stmt, [$previousbuildid]);
        $row = $stmt->fetch();
        $npreviouswarnings = $row['configurewarnings'];

        // Check if a diff already exists for this build.
        $this->PDO->beginTransaction();
        $stmt = $this->PDO->prepare(
                'SELECT * FROM configureerrordiff
                WHERE buildid = :buildid AND type = 1 FOR UPDATE');
        $stmt->bindParam(':buildid', $this->Id);
        pdo_execute($stmt);
        $row = $stmt->fetch();
        $existing_diff = 0;
        if ($row) {
            $existing_diff = $row['difference'];
        }

        // Don't log if no diff.
        $warningdiff = $nwarnings - $npreviouswarnings;
        if ($warningdiff == 0 && $existing_diff == 0) {
            $this->PDO->commit();
            return;
        }

        // UPDATE or INSERT a new record as necessary.
        if ($row) {
            $stmt = $this->PDO->prepare(
                    'UPDATE configureerrordiff SET difference = :difference
                    WHERE buildid = :buildid AND type = 1');
        } else {
            $duplicate_sql = '';
            global $CDASH_DB_TYPE;
            if ($CDASH_DB_TYPE !== 'pgsql') {
                $duplicate_sql = 'ON DUPLICATE KEY UPDATE difference = :difference';
            }
            $stmt = $this->PDO->prepare(
                    "INSERT INTO configureerrordiff (buildid, type, difference)
                    VALUES(:buildid, 1, :difference)
                    $duplicate_sql");
        }

        $stmt->bindValue(':buildid', $previousbuildid);
        $stmt->bindValue(':difference', $warningdiff);
        if (!pdo_execute($stmt)) {
            $this->PDO->rollBack();
            return;
        }
        $this->PDO->commit();
    }

    /** Compute the test timing as a weighted average of the previous test.
     *  Also compute the difference in errors and tests between builds.
     *  We do that in one shot for speed reasons. */
    public function ComputeTestTiming()
    {
        if (!$this->Id) {
            add_log('BuildId is not set', 'Build::ComputeTestTiming', LOG_ERR,
                $this->ProjectId, $this->Id, CDASH_OBJECT_BUILD, $this->Id);
            return false;
        }

        if (!$this->ProjectId) {
            add_log('ProjectId is not set', 'Build::ComputeTestTiming', LOG_ERR,
                $this->ProjectId, $this->Id, CDASH_OBJECT_BUILD, $this->Id);
            return false;
        }

        $testtimestatusfailed = 0;

        // TEST TIMING
        $weight = 0.3; // weight of the current test compared to the previous mean/std (this defines a window)
        $build = pdo_query('SELECT projectid,starttime,siteid,name,type FROM build WHERE id=' . qnum($this->Id));
        add_last_sql_error('Build:ComputeTestTiming', $this->ProjectId, $this->Id);

        $buildid = $this->Id;
        $build_array = pdo_fetch_array($build);
        $buildname = $build_array['name'];
        $buildtype = $build_array['type'];
        $starttime = $build_array['starttime'];
        $siteid = $build_array['siteid'];
        $projectid = $build_array['projectid'];

        $project = pdo_query('SELECT testtimestd,testtimestdthreshold,testtimemaxstatus FROM project WHERE id=' . qnum($this->ProjectId));
        add_last_sql_error('Build:ComputeTestTiming', $this->ProjectId, $this->Id);

        $project_array = pdo_fetch_array($project);
        $projecttimestd = $project_array['testtimestd'];
        $projecttimestdthreshold = $project_array['testtimestdthreshold'];
        $projecttestmaxstatus = $project_array['testtimemaxstatus'];

        // Find the previous build
        $previousbuildid = $this->GetPreviousBuildId();
        if ($previousbuildid == 0) {
            return;
        }

        // If we have one
        if ($previousbuildid > 0) {
            compute_test_difference($buildid, $previousbuildid, 0, $projecttestmaxstatus); // not run
            compute_test_difference($buildid, $previousbuildid, 1, $projecttestmaxstatus); // fail
            compute_test_difference($buildid, $previousbuildid, 2, $projecttestmaxstatus); // pass
            compute_test_difference($buildid, $previousbuildid, 3, $projecttestmaxstatus); // time

            // Loop through the tests
            $tests = pdo_query('SELECT build2test.time,build2test.testid,test.name,build2test.status,
                    build2test.timestatus
                    FROM build2test,test WHERE build2test.buildid=' . qnum($this->Id) . '
                    AND build2test.testid=test.id
                    ');
            add_last_sql_error('Build:ComputeTestTiming', $this->ProjectId, $this->Id);

            // Find the previous test
            $previoustest = pdo_query('SELECT build2test.testid,test.name FROM build2test,test
                    WHERE build2test.buildid=' . qnum($previousbuildid) . '
                    AND test.id=build2test.testid
                    ');
            add_last_sql_error('Build:ComputeTestTiming', $this->ProjectId, $this->Id);

            $testarray = array();
            while ($test_array = pdo_fetch_array($previoustest)) {
                $test = array();
                $test['id'] = $test_array['testid'];
                $test['name'] = $test_array['name'];
                $testarray[] = $test;
            }

            while ($test_array = pdo_fetch_array($tests)) {
                $testtime = $test_array['time'];
                $testid = $test_array['testid'];
                $teststatus = $test_array['status'];
                $testname = $test_array['name'];
                $previoustestid = 0;
                $timestatus = $test_array['timestatus'];

                foreach ($testarray as $test) {
                    if ($test['name'] == $testname) {
                        $previoustestid = $test['id'];
                        break;
                    }
                }

                if ($previoustestid > 0) {
                    $previoustest = pdo_query('SELECT timemean,timestd,timestatus FROM build2test
                            WHERE buildid=' . qnum($previousbuildid) . '
                            AND build2test.testid=' . qnum($previoustestid)
                    );
                    add_last_sql_error('Build:ComputeTestTiming', $this->ProjectId, $this->Id);

                    $previoustest_array = pdo_fetch_array($previoustest);
                    $previoustimemean = $previoustest_array['timemean'];
                    $previoustimestd = $previoustest_array['timestd'];
                    $previoustimestatus = $previoustest_array['timestatus'];

                    if ($teststatus == 'passed') {
                        // if the current test passed

                        // Check the current status
                        if ($previoustimestd < $projecttimestdthreshold) {
                            $previoustimestd = $projecttimestdthreshold;
                        }

                        if ($testtime > $previoustimemean + $projecttimestd * $previoustimestd) {
                            // only do positive std

                            $timestatus = $previoustimestatus + 1; // flag
                        } else {
                            $timestatus = 0; // reset the time status to 0
                        }

                        if ($timestatus > 0 && $timestatus <= $projecttestmaxstatus) {
                            // if we are currently detecting the time changed we should use previous mean std

                            $timemean = $previoustimemean;
                            $timestd = $previoustimestd;
                        } else {
                            // Update the mean and std
                            $timemean = (1 - $weight) * $previoustimemean + $weight * $testtime;
                            $timestd = sqrt((1 - $weight) * $previoustimestd * $previoustimestd + $weight * ($testtime - $timemean) * ($testtime - $timemean));
                        }
                    } else {
                        // the test failed so we just replicate the previous test time

                        $timemean = $previoustimemean;
                        $timestd = $previoustimestd;
                        $timestatus = 0;
                    }
                } else {
                    // the test doesn't exist

                    $timestd = 0;
                    $timestatus = 0;
                    $timemean = $testtime;
                }

                pdo_query('UPDATE build2test SET timemean=' . qnum($timemean) . ',timestd=' . qnum($timestd) . ',timestatus=' . qnum($timestatus) . '
                        WHERE buildid=' . qnum($this->Id) . ' AND testid=' . qnum($testid));
                add_last_sql_error('Build:ComputeTestTiming', $this->ProjectId, $this->Id);
                if ($timestatus >= $projecttestmaxstatus) {
                    $testtimestatusfailed++;
                }
            }
        } else {
            // this is the first build

            $timestd = 0;
            $timestatus = 0;

            // Loop throught the tests
            $tests = pdo_query('SELECT time,testid FROM build2test WHERE buildid=' . qnum($this->Id));
            while ($test_array = pdo_fetch_array($tests)) {
                $timemean = $test_array['time'];
                $testid = $test_array['testid'];

                pdo_query('UPDATE build2test SET timemean=' . qnum($timemean) . ',timestd=' . qnum($timestd) . ',timestatus=' . qnum($timestatus) . '
                        WHERE buildid=' . qnum($this->Id) . ' AND testid=' . qnum($testid));
                add_last_sql_error('Build:ComputeTestTiming', $this->ProjectId, $this->Id);
                if ($timestatus >= $projecttestmaxstatus) {
                    $testtimestatusfailed++;
                }
            } // loop through the tests
        }

        pdo_query('UPDATE build SET testtimestatusfailed=' . qnum($testtimestatusfailed) . ' WHERE id=' . $this->Id);
        add_last_sql_error('Build:ComputeTestTiming', $this->ProjectId, $this->Id);
        return true;
    }

    /** Compute the user statistics */
    public function ComputeUpdateStatistics()
    {
        if (!$this->Id) {
            add_log('Id is not set', 'Build::ComputeUpdateStatistics', LOG_ERR,
                $this->ProjectId, $this->Id, CDASH_OBJECT_BUILD, $this->Id);
            return false;
        }

        if (!$this->ProjectId) {
            add_log('ProjectId is not set', 'Build::ComputeUpdateStatistics', LOG_ERR, 0, $this->Id);
            return false;
        }

        $previousbuildid = $this->GetPreviousBuildId();
        if ($previousbuildid < 1) {
            // Nothing to compare the current results against.
            return false;
        }

        // Find how the number of errors, warnings and test failures have changed.
        $previousbuild = new Build();
        $previousbuild->Id = $previousbuildid;

        $errordiff = $this->GetNumberOfErrors() -
            $previousbuild->GetNumberOfErrors();
        $warningdiff = $this->GetNumberOfWarnings() -
            $previousbuild->GetNumberOfWarnings();
        $testdiff =
            ($this->GetNumberOfFailedTests() + $this->GetNumberOfNotRunTests()) -
            ($previousbuild->GetNumberOfFailedTests() + $previousbuild->GetNumberOfNotRunTests());

        // Find the number of authors that contributed to this changeset.
        $nauthors_stmt = $this->PDO->prepare(
                'SELECT count(author) FROM
                    (SELECT uf.author FROM updatefile AS uf
                     JOIN build2update AS b2u ON (b2u.updateid=uf.updateid)
                     WHERE b2u.buildid=? GROUP BY author)
                 AS test');
        pdo_execute($nauthors_stmt, [$this->Id]);
        $nauthors_array = $nauthors_stmt->fetch();
        $nauthors = $nauthors_array[0];

        $newbuild = 1;
        $previousauthor = '';
        // Record user statistics for each updated file.
        $updatefiles_stmt = $this->PDO->prepare(
            "SELECT author,email,checkindate,filename FROM updatefile AS uf
            JOIN build2update AS b2u ON b2u.updateid=uf.updateid
            WHERE b2u.buildid=? AND checkindate>'1980-01-01T00:00:00'
            ORDER BY author ASC, checkindate ASC");
        pdo_execute($updatefiles_stmt, [$this->Id]);

        while ($updatefiles_array = $updatefiles_stmt->fetch()) {
            $checkindate = $updatefiles_array['checkindate'];
            $author = $updatefiles_array['author'];
            $filename = $updatefiles_array['filename'];
            $email = $updatefiles_array['email'];
            $warnings = 0;
            $errors = 0;
            $tests = 0;

            if ($author != $previousauthor) {
                $newbuild = 1;
            }
            $previousauthor = $author;

            if ($warningdiff > 1) {
                $warnings = $this->FindRealErrors('WARNING', $author, $this->Id, $filename);
            } elseif ($warningdiff < 0) {
                $warnings = $this->FindRealErrors('WARNING', $author, $previousbuildid, $filename) * -1;
            }
            if ($errordiff > 1) {
                $errors = $this->FindRealErrors('ERROR', $author, $this->Id, $filename);
            } elseif ($errordiff < 0) {
                $errors = $this->FindRealErrors('ERROR', $author, $previousbuildid, $filename) * -1;
            }
            if ($nauthors > 1) {
                // When multiple authors contribute to a changeset it is
                // too difficult to determine which modified file caused a
                // change in test behavior.
                $tests = 0;
            } else {
                $tests = $testdiff;
            }

            $this->AddUpdateStatistics($author, $email, $checkindate, $newbuild,
                $warnings, $errors, $tests);

            $warningdiff -= $warnings;
            $errordiff -= $errors;
            $testdiff -= $tests;
            $newbuild = 0;
        }
        return true;
    }

    /** Helper function for AddUpdateStatistics */
    private function AddUpdateStatistics($author, $email, $checkindate, $firstbuild,
                                         $warningdiff, $errordiff, $testdiff)
    {
        // Find user by email address.
        require_once('models/user.php');
        $user = new User();
        $userid = $user->GetIdFromEmail($email);
        if (!$userid) {
            // Find user by author name.
            $stmt = $this->PDO->prepare(
                'SELECT up.userid FROM user2project AS up
                JOIN user2repository AS ur ON (ur.userid=up.userid)
                WHERE up.projectid=:projectid
                AND (ur.credential=:author OR ur.credential=:email)
                AND (ur.projectid=0 OR ur.projectid=:projectid)');
            $stmt->bindParam(':projectid', $this->ProjectId);
            $stmt->bindParam(':author', $author);
            $stmt->bindParam(':email', $email);
            pdo_execute($stmt);
            $row = $stmt->fetch();
            if (!$row) {
                // Unable to find user, return early.
                return;
            }
            $userid = $row['userid'];
        }

        $totalbuilds = 0;
        if ($firstbuild == 1) {
            $totalbuilds = 1;
        }

        // Convert errordiff to nfailederrors & nfixederrors (etc).
        $nfailedwarnings = 0;
        $nfixedwarnings = 0;
        $nfailederrors = 0;
        $nfixederrors = 0;
        $nfailedtests = 0;
        $nfixedtests = 0;
        if ($warningdiff > 0) {
            $nfailedwarnings = $warningdiff;
        } else {
            $nfixedwarnings = abs($warningdiff);
        }
        if ($errordiff > 0) {
            $nfailederrors = $errordiff;
        } else {
            $nfixederrors = abs($errordiff);
        }
        if ($testdiff > 0) {
            $nfailedtests = $testdiff;
        } else {
            $nfixedtests = abs($testdiff);
        }

        // Insert or update appropriately.
        $this->PDO->beginTransaction();
        $stmt = $this->PDO->prepare(
                'SELECT totalupdatedfiles FROM userstatistics
                WHERE userid=:userid AND projectid=:projectid AND
                checkindate=:checkindate FOR UPDATE');
        $stmt->bindParam(':userid', $userid);
        $stmt->bindParam(':projectid', $this->ProjectId);
        $stmt->bindParam(':checkindate', $checkindate);
        pdo_execute($stmt);
        $row = $stmt->fetch();

        if ($row) {
            // Update existing entry.
            $stmt = $this->PDO->prepare(
                    'UPDATE userstatistics SET
                    totalupdatedfiles=totalupdatedfiles+1,
                    totalbuilds=totalbuilds+:totalbuilds,
                    nfixedwarnings=nfixedwarnings+:nfixedwarnings,
                    nfailedwarnings=nfailedwarnings+:nfailedwarnings,
                    nfixederrors=nfixederrors+:nfixederrors,
                    nfailederrors=nfailederrors+:nfailederrors,
                    nfixedtests=nfixedtests+:nfixedtests,
                    nfailedtests=nfailedtests+:nfailedtests
                    WHERE userid=:userid AND projectid=:projectid AND
                    checkindate>=:checkindate');
            $stmt->bindParam(':totalbuilds', $totalbuilds);
            $stmt->bindParam(':nfixedwarnings', $nfixedwarnings);
            $stmt->bindParam(':nfailedwarnings', $nfailedwarnings);
            $stmt->bindParam(':nfixederrors', $nfixederrors);
            $stmt->bindParam(':nfailederrors', $nfailederrors);
            $stmt->bindParam(':nfixedtests', $nfixedtests);
            $stmt->bindParam(':nfailedtests', $nfailedtests);
            $stmt->bindParam(':userid', $userid);
            $stmt->bindParam(':projectid', $this->ProjectId);
            $stmt->bindParam(':checkindate', $checkindate);
            pdo_execute($stmt);
        } else {
            // Insert a new row into the database.
            $stmt = $this->PDO->prepare(
                    'INSERT INTO userstatistics
                    (userid,projectid,checkindate,totalupdatedfiles,totalbuilds,
                     nfixedwarnings,nfailedwarnings,nfixederrors,nfailederrors,
                     nfixedtests,nfailedtests)
                    VALUES
                    (:userid,:projectid,:checkindate,1,:totalbuilds,
                    :nfixedwarnings,:nfailedwarnings,:nfixederrors,
                    :nfailederrors,:nfixedtests,:nfailedtests)');
            $stmt->bindParam(':userid', $userid);
            $stmt->bindParam(':projectid', $this->ProjectId);
            $stmt->bindParam(':checkindate', $checkindate);
            $stmt->bindParam(':totalbuilds', $totalbuilds);
            $stmt->bindParam(':nfixedwarnings', $nfixedwarnings);
            $stmt->bindParam(':nfailedwarnings', $nfailedwarnings);
            $stmt->bindParam(':nfixederrors', $nfixederrors);
            $stmt->bindParam(':nfailederrors', $nfailederrors);
            $stmt->bindParam(':nfixedtests', $nfixedtests);
            $stmt->bindParam(':nfailedtests', $nfailedtests);
            pdo_execute($stmt);
        }
        $this->PDO->commit();
    }

    /** Find the errors associated with a user
     *  For now the author is not used, we assume that the filename is sufficient */
    private function FindRealErrors($type, $author, $buildid, $filename)
    {
        $errortype = 0;
        if ($type == 'WARNING') {
            $errortype = 1;
        }

        $errors = pdo_query('SELECT count(*) FROM builderror WHERE type=' . qnum($errortype) . "
                AND sourcefile LIKE '%$filename%' AND buildid=" . qnum($buildid));
        $errors_array = pdo_fetch_array($errors);
        $nerrors = $errors_array[0];
        // Adding the buildfailure
        $failures = pdo_query(
            'SELECT count(*) FROM buildfailure AS bf
                LEFT JOIN buildfailuredetails AS bfd ON (bfd.id=bf.detailsid)
                WHERE bfd.type=' . qnum($errortype) . " AND
                bf.sourcefile LIKE '%$filename%' AND bf.buildid=" . qnum($buildid));
        $failures_array = pdo_fetch_array($failures);
        $nerrors += $failures_array[0];
        return $nerrors;
    }

    /** Return the name of a build */
    public function GetName()
    {
        if (!$this->Id) {
            add_log('Id not set', 'Build GetName()', LOG_ERR);
            return false;
        }

        $build = pdo_query('SELECT name FROM build WHERE id=' . qnum($this->Id));
        if (!$build) {
            add_last_sql_error('Build:GetName', $this->ProjectId, $this->Id);
            return false;
        }
        $build_array = pdo_fetch_array($build);
        return $build_array['name'];
    }

    /** Get all the labels for a given build */
    public function GetLabels($labelarray = array())
    {
        if (!$this->Id) {
            add_log('Id not set', 'Build GetLabels()', LOG_ERR);
            return false;
        }

        $sql = 'SELECT label.id as labelid FROM label WHERE
            label.id IN (SELECT labelid AS id FROM label2build WHERE label2build.buildid=' . qnum($this->Id) . ')';

        if (empty($labelarray) || isset($labelarray['test']['errors'])) {
            $sql .= ' OR label.id IN (SELECT labelid AS id FROM label2test WHERE label2test.buildid=' . qnum($this->Id) . ')';
        }
        if (empty($labelarray) || isset($labelarray['coverage']['errors'])) {
            $sql .= ' OR label.id IN (SELECT labelid AS id FROM label2coveragefile WHERE label2coveragefile.buildid=' . qnum($this->Id) . ')';
        }
        if (empty($labelarray) || isset($labelarray['build']['errors'])) {
            $sql .= "  OR label.id IN (
                SELECT l2bf.labelid AS id
                FROM label2buildfailure AS l2bf
                LEFT JOIN buildfailure AS bf ON (bf.id=l2bf.buildfailureid)
                LEFT JOIN buildfailuredetails AS bfd ON (bfd.id=bf.detailsid)
                WHERE bfd.type='0' AND bf.buildid=" . qnum($this->Id) . ')';
        }
        if (empty($labelarray) || isset($labelarray['build']['warnings'])) {
            $sql .= "  OR label.id IN (
                SELECT l2bf.labelid AS id
                FROM label2buildfailure AS l2bf
                LEFT JOIN buildfailure AS bf ON (bf.id=l2bf.buildfailureid)
                LEFT JOIN buildfailuredetails AS bfd ON (bfd.id=bf.detailsid)
                WHERE bfd.type='1' AND bf.buildid=" . qnum($this->Id) . ')';
        }
        if (empty($labelarray) || isset($labelarray['dynamicanalysis']['errors'])) {
            $sql .= ' OR label.id IN (SELECT labelid AS id FROM label2dynamicanalysis,dynamicanalysis
                WHERE label2dynamicanalysis.dynamicanalysisid=dynamicanalysis.id AND dynamicanalysis.buildid=' . qnum($this->Id) . ')';
        }

        $labels = pdo_query($sql);

        if (!$labels) {
            add_last_sql_error('Build:GetLabels', $this->ProjectId, $this->Id);
            return false;
        }

        $labelids = array();
        while ($label_array = pdo_fetch_array($labels)) {
            $labelids[] = $label_array['labelid'];
        }
        return array_unique($labelids);
    }

    // Get the group for a build
    public function GetGroup()
    {
        if (!$this->Id) {
            add_log('Id not set', 'Build GetGroup()', LOG_ERR);
            return false;
        }
        $group = pdo_query('SELECT groupid FROM build2group WHERE buildid=' . qnum($this->Id));
        if (!$group) {
            add_last_sql_error('Build:GetGroup', $this->ProjectId, $this->Id);
            return false;
        }

        $buildgroup_array = pdo_fetch_array($group);
        return $buildgroup_array['groupid'];
    }

    /** Get the number of errors for a build */
    public function GetNumberOfErrors()
    {
        if (!$this->Id) {
            add_log('Id not set', 'Build GetNumberOfErrors()', LOG_ERR);
            return false;
        }

        $builderror = pdo_query('SELECT builderrors FROM build WHERE id=' . qnum($this->Id));
        add_last_sql_error('Build:GetNumberOfErrors', $this->ProjectId, $this->Id);
        $builderror_array = pdo_fetch_array($builderror);
        if ($builderror_array[0] == -1) {
            return 0;
        }
        return $builderror_array[0];
    }

    /** Get the number of warnings for a build */
    public function GetNumberOfWarnings()
    {
        if (!$this->Id) {
            add_log('Id not set', 'Build GetNumberOfWarnings()', LOG_ERR);
            return false;
        }

        $builderror = pdo_query('SELECT buildwarnings FROM build WHERE id=' . qnum($this->Id));
        add_last_sql_error('Build:GetNumberOfWarnings', $this->ProjectId, $this->Id);
        $builderror_array = pdo_fetch_array($builderror);
        if ($builderror_array[0] == -1) {
            return 0;
        }
        return $builderror_array[0];
    }

    /* Return all uploaded files or URLs for this build */
    public function GetUploadedFilesOrUrls()
    {
        if (!$this->Id) {
            add_log('Id not set', 'Build GetUploadedFilesOrUrls()', LOG_ERR);
            return false;
        }

        $results = pdo_query("SELECT fileid FROM build2uploadfile WHERE buildid='$this->Id'");
        $allUploadedFiles = array();
        while ($uploadfiles_array = pdo_fetch_array($results)) {
            $UploadFile = new UploadFile();
            $UploadFile->Id = $uploadfiles_array['fileid'];
            $UploadFile->Fill();
            $allUploadedFiles[] = $UploadFile;
        }
        return $allUploadedFiles;
    }

    /** Lookup this build's parentid, returning 0 if none is found. */
    public function LookupParentBuildId()
    {
        if (!$this->SiteId || !$this->Name || !$this->Stamp || !$this->ProjectId) {
            return 0;
        }

        $stmt = $this->PDO->prepare(
            'SELECT id FROM build WHERE parentid = -1 AND projectid = ? AND
            siteid = ? AND name = ? AND stamp = ?');
        pdo_execute($stmt, [$this->ProjectId, $this->SiteId, $this->Name, $this->Stamp]);
        $parentid = $stmt->fetchColumn();

        if ($parentid !== false) {
            return $parentid;
        }
        return 0;
    }

    /** Create a new build as a parent of $this and sets $this->ParentId.
     * Assumes many fields have been set prior to calling this function.
     **/
    public function CreateParentBuild($numErrors, $numWarnings)
    {
        if ($numErrors < 0) {
            $numErrors = 0;
        }
        if ($numWarnings < 0) {
            $numWarnings = 0;
        }

        // Check if there's an existing build that should be the parent.
        // This would be a standalone build (parent=0) with no subproject
        // that matches our name, site, stamp, and projectid.
        $query = "SELECT id FROM build
            WHERE parentid = 0 AND name = '$this->Name' AND
            siteid = '$this->SiteId' AND stamp = '$this->Stamp' AND
            projectid = '$this->ProjectId'
            ";
        $result = pdo_query($query);
        if (pdo_num_rows($result) > 0) {
            $result_array = pdo_fetch_array($result);
            $this->SetParentId($result_array['id']);

            // Mark it as a parent (parentid of -1).
            pdo_query("UPDATE build SET parentid = -1 WHERE id = $this->ParentId");
        } else {
            // Generate a UUID for the parent build.  It is distinguished
            // from its children by the lack of SubProject (final parameter).
            $uuid = Build::GenerateUuid($this->Stamp, $this->Name,
                $this->SiteId, $this->ProjectId, '');

            // Create the parent build here.  Note how parent builds
            // are indicated by parentid == -1.
            $query = "INSERT INTO build
                (parentid, siteid, projectid, stamp, name, type, generator,
                 starttime, endtime, submittime, builderrors, buildwarnings,
                 uuid, changeid)
                VALUES
                ('-1', '$this->SiteId', '$this->ProjectId', '$this->Stamp',
                 '$this->Name', '$this->Type', '$this->Generator',
                 '$this->StartTime', '$this->EndTime', '$this->SubmitTime',
                 0, 0, '$uuid', '$this->PullRequest')";

            if (!pdo_query($query)) {
                // Check if somebody else beat us to creating this parent build.
                $existing_id_result = pdo_single_row_query(
                    "SELECT id FROM build WHERE uuid = '$uuid'");
                if ($existing_id_result &&
                    array_key_exists('id', $existing_id_result)
                ) {
                    $this->SetParentId($existing_id_result['id']);
                    return false;
                } else {
                    add_last_sql_error('Build Insert Parent', $this->ProjectId, $this->Id);
                    return false;
                }
            }
            if (!$this->ParentId) {
                $this->SetParentId(pdo_insert_id('build'));
            }
        }

        // Update the parent's tally of build errors & warnings.
        $this->UpdateBuild($this->ParentId, $numErrors, $numWarnings);

        // Give the parent a label for this build's subproject.
        $label = new Label;
        $label->Text = $this->SubProjectName;
        $parent = new Build();
        $parent->Id = $this->ParentId;
        $parent->AddLabel($label);
        $parent->InsertLabelAssociations();

        // Since we just created a parent we should also update any existing
        // builds that should be a child of this parent but aren't yet.
        // This happens when Update.xml is parsed first, because it doesn't
        // contain info about what subproject it came from.
        // TODO: maybe we don't need this any more?
        $query =
            "UPDATE build SET parentid=$this->ParentId
            WHERE parentid=0 AND siteid='$this->SiteId' AND
            name='$this->Name' AND stamp='$this->Stamp' AND
            projectid=$this->ProjectId";
        if (!pdo_query($query)) {
            add_last_sql_error(
                'Build Insert Update Parent', $this->ProjectId, $this->ParentId);
        }
        return true;
    }

    /**
     * Update our database record of a build so that it accurately reflects
     * this object and the specified number of new warnings & errors.
     **/
    public function UpdateBuild($buildid, $newErrors, $newWarnings)
    {
        if ($buildid < 1) {
            return;
        }

        // Avoid a race condition when parallel processing.
        pdo_begin_transaction();

        $clauses = [];
        $params = [];

        $stmt = $this->PDO->prepare('
            SELECT builderrors, buildwarnings, starttime, endtime,
            submittime, log, command, parentid
            FROM build WHERE id = ? FOR UPDATE');
        pdo_execute($stmt, [$buildid]);
        $build = $stmt->fetch();

        // Special case: check if we should move from -1 to 0 errors/warnings.
        $errorsHandled = false;
        $warningsHandled = false;
        if ($this->InsertErrors) {
            if ($build['builderrors'] == -1 && $newErrors == 0) {
                $clauses[] = 'builderrors = 0';
                $errorsHandled = true;
            }
            if ($build['buildwarnings'] == -1 && $newWarnings == 0) {
                $clauses[] = 'buildwarnings = 0';
                $warningsHandled = true;
            }
        }

        // Check if we still need to modify builderrors or buildwarnings.
        if (!$errorsHandled) {
            if ($build['builderrors'] == -1) {
                $build['builderrors'] = 0;
            }
            if ($newErrors > 0) {
                $numErrors = $build['builderrors'] + $newErrors;
                $clauses[] = 'builderrors = ?';
                $params[] = $numErrors;
            }
        }
        if (!$warningsHandled) {
            if ($build['buildwarnings'] == -1) {
                $build['buildwarnings'] = 0;
            }
            if ($newWarnings > 0) {
                $numWarnings = $build['buildwarnings'] + $newWarnings;
                $clauses[] = 'buildwarnings = ?';
                $params[] = $numWarnings;
            }
        }

        // Check if we need to modify starttime or endtime.
        if (strtotime($build['starttime']) > strtotime($this->StartTime)) {
            $clauses[] = 'starttime = ?';
            $params[] = $this->StartTime;
        }
        if (strtotime($build['endtime']) < strtotime($this->EndTime)) {
            $clauses[] = 'endtime = ?';
            $params[] = $this->EndTime;
        }

        if ($build['parentid'] != -1) {
            // If this is not a parent build, check if its log or command
            // has changed.
            if ($this->Log && $this->Log != $build['log']) {
                if (!empty($build['log'])) {
                    $log = $build['log'] . " " . $this->Log;
                } else {
                    $log = $this->Log;
                }
                $clauses[] = 'log = ?';
                $params[] = $log;
            }
            if ($this->Command && $this->Command != $build['command']) {
                if (!empty($build['command'])) {
                    $command = $build['command'] . "; " . $this->Command;
                } else {
                    $command = $this->Command;
                }
                $clauses[] = 'command = ?';
                $params[] = $command;
            }
        }

        $num_clauses = count($clauses);
        if ($num_clauses > 0) {
            $query = 'UPDATE build SET ' . $clauses[0];
            for ($i = 1; $i < $num_clauses; $i++) {
                $query .= ', ' . $clauses[$i];
            }
            $query .= ' WHERE id = ?';
            $params[] = $buildid;
            $stmt = $this->PDO->prepare($query);
            if (!pdo_execute($stmt, $params)) {
                pdo_rollback();
                return false;
            }
        }

        pdo_commit();

        // Also update the parent if necessary.
        $stmt = $this->PDO->prepare('SELECT parentid FROM build WHERE id = ?');
        pdo_execute($stmt, [$buildid]);
        $parentid = $stmt->fetchColumn();
        if ($parentid > 0) {
            if ($buildid == $parentid) {
                // Avoid infinite recursion.
                // This should never happen, but we might as well be careful.
                add_log("$buildid is its own parent",
                        'Build::UpdateBuild', LOG_ERR,
                        $this->ProjectId, $this->Id,
                        CDASH_OBJECT_BUILD, $this->Id);
                return;
            }
            $this->UpdateBuild($parentid, $newErrors, $newWarnings);
            if ($buildid == $this->Id) {
                $this->SetParentId($parentid);
            }
        }
    }

    /** Update the testing numbers for our parent build. */
    public function UpdateParentTestNumbers($newFailed, $newNotRun, $newPassed)
    {
        if ($this->ParentId < 1) {
            return;
        }

        // Avoid a race condition when parallel processing.
        pdo_begin_transaction();

        $numFailed = 0;
        $numNotRun = 0;
        $numPassed = 0;

        $parent = pdo_single_row_query(
            'SELECT testfailed, testnotrun, testpassed
                FROM build WHERE id=' . qnum($this->ParentId) . ' FOR UPDATE');

        // Don't let the -1 default value screw up our math.
        if ($parent['testfailed'] == -1) {
            $parent['testfailed'] = 0;
        }
        if ($parent['testnotrun'] == -1) {
            $parent['testnotrun'] = 0;
        }
        if ($parent['testpassed'] == -1) {
            $parent['testpassed'] = 0;
        }

        $numFailed = $newFailed + $parent['testfailed'];
        $numNotRun = $newNotRun + $parent['testnotrun'];
        $numPassed = $newPassed + $parent['testpassed'];

        pdo_query(
            "UPDATE build SET testnotrun='$numNotRun',
                testfailed='$numFailed',
                testpassed='$numPassed'
                WHERE id=" . qnum($this->ParentId));

        add_last_sql_error('Build:UpdateParentTestNumbers', $this->ProjectId, $this->Id);

        pdo_commit();

        // NOTE: as far as I can tell, build.testtimestatusfailed isn't used,
        // so for now it isn't being updated for parent builds.
    }

    /** Set number of configure warnings for this build. */
    public function SetNumberOfConfigureWarnings($numWarnings)
    {
        if (!$this->Id || !is_numeric($this->Id)) {
            return;
        }

        pdo_query(
            "UPDATE build SET configurewarnings='$numWarnings'
                WHERE id=" . qnum($this->Id));

        add_last_sql_error('Build:SetNumberOfConfigureWarnings',
            $this->ProjectId, $this->Id);
    }

    /** Set number of configure errors for this build. */
    public function SetNumberOfConfigureErrors($numErrors)
    {
        if (!$this->Id || !is_numeric($this->Id)) {
            return;
        }

        pdo_query(
            "UPDATE build SET configureerrors='$numErrors'
                WHERE id=" . qnum($this->Id));

        add_last_sql_error('Build:SetNumberOfConfigureErrors',
            $this->ProjectId, $this->Id);

        // Should we post configure errors to a pull request?
        if (isset($this->PullRequest) && $numErrors > 0) {
            $message = 'This build failed to configure';
            $url = get_server_URI(false) .
                "/viewConfigure.php?buildid=$this->Id";
            $this->NotifyPullRequest($message, $url);
        }
    }

    /**
     * Update the tally of configure errors & warnings for this build's
     * parent.
     **/
    public function UpdateParentConfigureNumbers($newWarnings, $newErrors)
    {
        $this->SetParentId($this->LookupParentBuildId());
        if ($this->ParentId < 1) {
            return;
        }
        // Avoid a race condition when parallel processing.
        pdo_begin_transaction();

        $numErrors = 0;
        $numWarnings = 0;

        $parent = pdo_single_row_query(
            'SELECT configureerrors, configurewarnings
                FROM build WHERE id=' . qnum($this->ParentId) . ' FOR UPDATE');

        // Don't let the -1 default value screw up our math.
        if ($parent['configureerrors'] == -1) {
            $parent['configureerrors'] = 0;
        }
        if ($parent['configurewarnings'] == -1) {
            $parent['configurewarnings'] = 0;
        }

        $numErrors = $newErrors + $parent['configureerrors'];
        $numWarnings = $newWarnings + $parent['configurewarnings'];

        pdo_query(
            "UPDATE build SET configureerrors='$numErrors',
                configurewarnings='$numWarnings'
                WHERE id=" . qnum($this->ParentId));

        add_last_sql_error('Build:UpdateParentConfigureNumbers',
            $this->ProjectId, $this->Id);

        pdo_commit();
    }

    /** Get/set pull request for this build. */
    public function GetPullRequest()
    {
        return $this->PullRequest;
    }

    public function SetPullRequest($pr)
    {
        $this->PullRequest = $pr;
    }

    private function NotifyPullRequest($message, $url)
    {
        // Figure out if we should notify this build or its parent.
        $idToNotify = $this->Id;
        if ($this->ParentId > 0) {
            $idToNotify = $this->ParentId;
        }

        // Return early if this build already posted a comment on this PR.
        $notified = true;
        $row = pdo_single_row_query(
            'SELECT notified FROM build WHERE id=' . qnum($idToNotify));
        if ($row && array_key_exists('notified', $row)) {
            $notified = $row['notified'];
        }
        if ($notified) {
            return;
        }

        // Mention which SubProject caused this error (if any).
        if ($this->GetSubProjectName()) {
            $message .= " during $this->SubProjectName";
        }
        $message .= '.';

        // Post the PR comment & mark this build as 'notified'.
        post_pull_request_comment($this->ProjectId, $this->PullRequest,
            $message, $url);
        pdo_query("UPDATE build SET notified='1' WHERE id=" . qnum($idToNotify));
    }

    public function SetConfigureDuration($duration, $update_parent=true)
    {
        if (!$this->Id || !is_numeric($this->Id)) {
            return;
        }

        // Set configure duration for this build.
        pdo_query(
            "UPDATE build SET configureduration=$duration
                WHERE id=" . qnum($this->Id));

        add_last_sql_error('Build:SetConfigureDuration',
            $this->ProjectId, $this->Id);

        if (!$update_parent) {
            return;
        }
        // If this is a child build, add this duration
        // to the parent's configure duration sum.
        $this->SetParentId($this->LookupParentBuildId());
        if ($this->ParentId > 0) {
            pdo_query(
                "UPDATE build
                    SET configureduration = configureduration + $duration
                    WHERE id=" . qnum($this->ParentId));

            add_last_sql_error('Build:SetConfigureDuration',
                $this->ProjectId, $this->ParentId);
        }
    }

    public function UpdateBuildDuration($duration, $update_parent=true)
    {
        if ($duration === 0 || !$this->Id || !is_numeric($this->Id)) {
            return;
        }

        // Update build step duration for this build.
        pdo_query(
                "UPDATE build SET buildduration=buildduration + $duration
                WHERE id=" . qnum($this->Id));
        add_last_sql_error('Build:UpdateBuildDuration',
            $this->ProjectId, $this->Id);

        if (!$update_parent) {
            return;
        }
        // If this is a child build, add this duration
        // to the parent's sum.
        $this->SetParentId($this->LookupParentBuildId());
        if ($this->ParentId > 0) {
            pdo_query(
                "UPDATE build
                    SET buildduration = buildduration + $duration
                    WHERE id=" . qnum($this->ParentId));
            add_last_sql_error('Build:UpdateBuildDuration',
                $this->ProjectId, $this->ParentId);
        }
    }

    // Return the dashboard date (in Y-m-d format) for this build.
    public function GetDate()
    {
        if (!$this->Id || !is_numeric($this->Id)) {
            return date(FMT_DATE);
        }
        $this->FillFromId($this->Id);

        if (!$this->NightlyStartTime) {
            $query =
                'SELECT nightlytime FROM project WHERE id = ' .
                qnum($this->ProjectId);
            $row = pdo_single_row_query($query);
            $this->NightlyStartTime = strtotime($row['nightlytime']);
        }

        // If the build was started after the nightly start time
        // then it should appear on the dashboard results for the
        // subsequent day.
        $build_start_time = strtotime($this->StartTime);

        if (date(FMT_TIME, $this->NightlyStartTime) < '12:00:00') {
            // If the "nightly" start time is in the morning then any build
            // that occurs before it is part of the previous testing day.
            if (date(FMT_TIME, $build_start_time) <
                date(FMT_TIME, $this->NightlyStartTime)
            ) {
                $build_start_time -= (3600 * 24);
            }
        } else {
            // If the nightly start time is NOT in the morning then any build
            // that occurs after it is part of the next testing day.
            if (date(FMT_TIME, $build_start_time) >=
                date(FMT_TIME, $this->NightlyStartTime)
            ) {
                $build_start_time += (3600 * 24);
            }
        }

        $build_date = date(FMT_DATE, $build_start_time);
        return $build_date;
    }

    /** Return whether or not this build has been marked as done. */
    public function GetDone()
    {
        if (empty($this->Id)) {
            return false;
        }

        if (!empty($this->Done)) {
            return $this->Done;
        }

        $query = pdo_query(
            'SELECT done FROM build WHERE build.id=' . qnum($this->Id));
        if (!$query) {
            add_last_sql_error('Build:GetDone()', $this->ProjectId, $this->Id);
            return false;
        }

        if (pdo_num_rows($query) > 0) {
            $query_array = pdo_fetch_array($query);
            $this->Done = $query_array['done'];
            return $this->Done;
        }
        return false;
    }

    /** Remove this build if it exists and has been marked as done.
     * This is called by XML handlers when a new replacement
     * submission is received.
     **/
    public function RemoveIfDone()
    {
        if (!$this->Exists() || !$this->GetDone()) {
            return false;
        }

        remove_build($this->Id);
        $this->Id = 0;
        return true;
    }

    /** Generate a UUID from the specified build details. */
    public static function GenerateUuid($stamp, $name, $siteid, $projectid,
                                        $subprojectname)
    {
        $input_string =
            $stamp . '_' . $name . '_' . $siteid . '_' . '_' .
            $projectid . '_' . $subprojectname;
        return md5($input_string);
    }

    /** Get/set the parentid for this build. */
    public function GetParentId()
    {
        return $this->ParentId;
    }

    public function SetParentId($parentid)
    {
        if ($parentid > 0 && $parentid == $this->Id) {
            add_log("Attempt to mark build $this->Id as its own parent",
                    'Build::SetParentId', LOG_ERR,
                $this->ProjectId, $this->Id,
                CDASH_OBJECT_BUILD, $this->Id);
            return;
        }
        $this->ParentId = $parentid;
    }


    /* Get the beginning and the end of the testing day for this build
     * in DATETIME format.
     */
    public function ComputeTestingDayBounds()
    {
        if ($this->ProjectId < 1) {
            return false;
        }

        if (isset($this->BeginningOfDay) && isset($this->EndOfDay)) {
            return true;
        }

        $build_date = $this->GetDate();
        list($previousdate, $currentstarttime, $nextdate) =
            get_dates($build_date, $this->NightlyStartTime);

        $beginning_timestamp = $currentstarttime;
        $end_timestamp = $currentstarttime + 3600 * 24;
        $this->BeginningOfDay = gmdate(FMT_DATETIME, $beginning_timestamp);
        $this->EndOfDay = gmdate(FMT_DATETIME, $end_timestamp);

        return true;
    }

    /**
     * Get all errors, including warnings, for all children builds of this build.
     *
     * @param int $fetchStyle
     * @return array|bool
     */
    public function GetErrorsForChildren($fetchStyle = PDO::FETCH_ASSOC)
    {
        if (!$this->Id) {
            add_log('Id not set', 'Build::GetErrorsForChildren', LOG_WARNING);
            return false;
        }

        $sql = '
            SELECT sp2b.subprojectid, sp.name subprojectname, be.*
            FROM builderror be
            JOIN build AS b
                ON b.id = be.buildid
            JOIN subproject2build AS sp2b
                ON sp2b.buildid = be.buildid
            JOIN subproject AS sp
                ON sp.id = sp2b.subprojectid
            WHERE b.parentid = ?
        ';

        $query = $this->PDO->prepare($sql);
        pdo_execute($query, [$this->Id]);

        return $query->fetchAll($fetchStyle);
    }

    /**
     * Get all failures, including warnings, for all children builds of this build.
     *
     * @param int $fetchStyle
     * @return array|bool
     */
    public function GetFailuresForChildren($fetchStyle = PDO::FETCH_ASSOC)
    {
        if (!$this->Id) {
            add_log('Id not set', 'Build::GetFailuresForChildren', LOG_WARNING);
            return false;
        }

        $sql = '
            SELECT
                bf.id,
                bf.sourcefile,
                bfd.language,
                bfd.targetname,
                bfd.outputfile,
                bfd.outputtype,
                bf.workingdirectory,
                bfd.stderror,
                bfd.stdoutput,
                bfd.type,
                bfd.exitcondition,
                sp2b.subprojectid,
                sp.name subprojectname
             FROM buildfailure AS bf
             LEFT JOIN buildfailuredetails AS bfd
                ON (bfd.id=bf.detailsid)
            JOIN subproject2build AS sp2b
                ON bf.buildid = sp2b.buildid
            JOIN subproject AS sp
                ON sp.id = sp2b.subprojectid
            JOIN build b on bf.buildid = b.id
            WHERE b.parentid = ?
        ';

        $query = $this->PDO->prepare($sql);

        pdo_execute($query, [$this->Id]);

        return $query->fetchAll($fetchStyle);
    }

    /**
     * Return a SubProject build for a particular parent if it exists.
     */
    public static function GetSubProjectBuild($parentid, $subprojectid)
    {
        $pdo = get_link_identifier()->getPdo();
        $stmt = $pdo->prepare(
            'SELECT b.id FROM build b
            JOIN subproject2build sp2b ON (sp2b.buildid = b.id)
            WHERE b.parentid = ? AND sp2b.subprojectid = ?');
        pdo_execute($stmt, [$parentid, $subprojectid]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $build = new Build();
        $build->Id = $row['id'];
        $build->FillFromId($build->Id);
        return $build;
    }
}
