<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE.  See the above copyright notices for more information.

  =========================================================================*/
// It is assumed that appropriate headers should be included before including this file
include_once('include/ctestparserutils.php');
include_once("include/repository.php");
include_once('models/builderror.php');
include_once('models/builderrordiff.php');
include_once('models/buildinformation.php');
include_once('models/buildusernote.php');
include_once('models/constants.php');
include_once('models/label.php');
include_once('models/subproject.php');
include_once('models/test.php');
include_once('models/uploadfile.php');

class build
{
    public $Id;
    public $SiteId;
    public $ProjectId;
    public $ParentId;
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

    // For the moment we accept only one group per build
    public $GroupId;

    public $Errors;
    public $ErrorDiffs;

    public $SubProjectId;
    public $SubProjectName;
    public $Append;
    public $Labels;

    // Only the build.xml has information about errors and warnings
    // when the InsertErrors is false the build is created but not the errors and warnings
    public $InsertErrors;

    // Used to comment on pull/merge requests when something goes wrong
    // with this build.
    private $PullRequest;

    // Used to mark whether this object already has its fields set.
    public $Filled;

    public function __construct()
    {
        $this->ProjectId = 0;
        $this->Errors = array();
        $this->ErrorDiffs = array();
        $this->Append = false;
        $this->InsertErrors = true;
        $this->Filled = false;
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
        if (strlen($this->Type)==0) {
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
                "projectid=".qnum($this->ProjectId)." AND endtime='1980-01-01 00:00:00'"
                );
        if (!$query) {
            add_last_sql_error("Build:SetSubProject()", $this->ProjectId);
            return false;
        }

        $this->SubProjectName = $subproject;

        // Add this subproject as a label on the parent build.
        $this->ParentId = $this->GetParentBuildId();
        if ($this->ParentId > 0) {
            $parent = new Build();
            $parent->Id = $this->ParentId;

            $label = new Label;
            $label->Text = $subproject;

            $parent->AddLabel($label);
            $parent->InsertLabelAssociations();
        }

        if (pdo_num_rows($query)>0) {
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

        add_log('New subproject detected: '.$subproject, 'Build::SetSubProject',
                LOG_WARNING, $this->ProjectId, $this->Id, CDASH_OBJECT_BUILD, $this->Id);
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

        $query = pdo_query("SELECT name FROM subproject,subproject2build WHERE subproject.id=subproject2build.subprojectid
                AND subproject2build.buildid=".qnum($this->Id));
        if (!$query) {
            add_last_sql_error("Build:GetSubProjectName()", $this->ProjectId, $this->Id);
            return false;
        }

        if (pdo_num_rows($query)>0) {
            $query_array = pdo_fetch_array($query);
            $this->SubProjectName = $query_array['name'];
            return $this->SubProjectName;
        }

        return false;
    }

    /** Save the total tests time */
    public function SaveTotalTestsTime($time)
    {
        if (!$this->Id || !is_numeric($this->Id)) {
            return false;
        }

        // Check if already exists
        $query = pdo_query("SELECT buildid FROM buildtesttime WHERE buildid=".qnum($this->Id));
        if (!$query) {
            add_last_sql_error("SaveTotalTestsTime", $this->ProjectId, $this->Id);
            return false;
        }

        $time = pdo_real_escape_string($time);
        if (pdo_num_rows($query)>0) {
            $query = "UPDATE buildtesttime SET time='".$time."' WHERE buildid=".qnum($this->Id);
        } else {
            $query = "INSERT INTO buildtesttime (buildid, time) VALUES ('".$this->Id."','".$time."')";
        }

        if (!pdo_query($query)) {
            add_last_sql_error("Build:SaveTotalTestsTime", $this->ProjectId, $this->Id);
            return false;
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
            add_last_sql_error("Build:UpdateEndTime", $this->ProjectId, $this->Id);
            return false;
        }
    }


    public function QuerySubProjectId($buildid)
    {
        $query = pdo_query(
                "SELECT id FROM subproject, subproject2build " .
                "WHERE subproject.id=subproject2build.subprojectid AND subproject2build.buildid=".qnum($buildid));
        if (!$query) {
            add_last_sql_error("Build:QuerySubProjectId", $this->ProjectId, $buildid);
            return false;
        }
        $query_array = pdo_fetch_array($query);
        return $query_array["id"];
    }


    /** Fill the current build information from the buildid */
    public function FillFromId($buildid)
    {
        if ($this->Filled) {
            // Already filled, no need to do it again.
            return false;
        }

        $query = pdo_query("SELECT projectid,starttime,siteid,name,type,parentid FROM build WHERE id=".qnum($buildid));

        if (!$query) {
            add_last_sql_error("Build:FillFromId()", $this->ProjectId, $this->Id);
            return false;
        }

        $build_array = pdo_fetch_array($query);
        $this->Name = $build_array["name"];
        $this->Type = $build_array["type"];
        $this->StartTime = $build_array["starttime"];
        $this->SiteId = $build_array["siteid"];
        $this->ProjectId = $build_array["projectid"];
        $this->ParentId = $build_array["parentid"];

        $subprojectid = $this->QuerySubProjectId($buildid);
        if ($subprojectid) {
            $this->SubProjectId = $subprojectid;
        }

        $result = pdo_fetch_array(pdo_query(
                    "SELECT groupid FROM build2group WHERE buildid='$buildid'"));
        $this->GroupId = $result["groupid"];
        $this->Filled = true;
    }


    /** Get the previous build id. */
    public function GetPreviousBuildId()
    {
        if (!$this->Id) {
            return 0;
        }
        $this->FillFromId($this->Id);

        $previous_clause =
            "AND starttime<'$this->StartTime' ORDER BY starttime DESC";
        return $this->GetRelatedBuildId($previous_clause);
    }


    /** Get the next build id. */
    public function GetNextBuildId()
    {
        if (!$this->Id) {
            return 0;
        }
        $this->FillFromId($this->Id);

        $next_clause = "AND starttime>'$this->StartTime' ORDER BY starttime";
        return $this->GetRelatedBuildId($next_clause);
    }


    /** Get the most recent build id. */
    public function GetCurrentBuildId()
    {
        if (!$this->Id) {
            return 0;
        }
        $this->FillFromId($this->Id);

        $current_clause = "ORDER BY starttime DESC";
        return $this->GetRelatedBuildId($current_clause);
    }


    /** Private helper function to encapsulate the common parts of
     * Get{Previous,Next,Current}BuildId()
     **/
    private function GetRelatedBuildId($which_build_criteria)
    {
        // Take subproject into account, such that if there is one, then the
        // previous build must be associated with the same subproject...
        //
        $subproj_table = "";
        $subproj_criteria = "";
        $parent_criteria = "";

        if ($this->SubProjectId) {
            $subproj_table = ", subproject2build";
            $subproj_criteria =
                "AND build.id=subproject2build.buildid ".
                "AND subproject2build.subprojectid=".qnum($this->SubProjectId)." ";
        }
        if ($this->ParentId == -1) {
            // Only search for other parents.
            $parent_criteria = "AND build.parentid=-1";
        }

        $query = pdo_query("
                SELECT id FROM build$subproj_table
                WHERE siteid=".qnum($this->SiteId)."
                AND type='$this->Type'
                AND name='$this->Name'
                AND projectid=".qnum($this->ProjectId)."
                $subproj_criteria
                $parent_criteria
                $which_build_criteria
                LIMIT 1");

        if (!$query) {
            add_last_sql_error(
                    "Build:GetRelatedBuildId", $this->ProjectId, $this->Id);
            return 0;
        }

        if (pdo_num_rows($query)>0) {
            $relatedbuild_array = pdo_fetch_array($query);
            return $relatedbuild_array['id'];
        }

        return 0;
    }


    /** Get the build id from its name */
    public function GetIdFromName($subproject)
    {
        $buildid = 0;

        // Make sure subproject name and id fields are set:
        //
        $this->SetSubProject($subproject);

        if ($this->SubProjectId != 0) {
            $build = pdo_query("SELECT id FROM build, subproject2build".
                    " WHERE projectid=".qnum($this->ProjectId).
                    " AND siteid=".qnum($this->SiteId).
                    " AND name='".$this->Name."'".
                    " AND stamp='".$this->Stamp."'".
                    " AND build.id=subproject2build.buildid".
                    " AND subproject2build.subprojectid=".qnum($this->SubProjectId));
        } else {
            $build = pdo_query("SELECT id FROM build".
                    " WHERE projectid=".qnum($this->ProjectId).
                    " AND siteid=".qnum($this->SiteId).
                    " AND name='".$this->Name."'".
                    " AND stamp='".$this->Stamp."'");
        }

        if (pdo_num_rows($build)>0) {
            $build_array = pdo_fetch_array($build);
            $buildid = $build_array["id"];
            return $buildid;
        }

        add_last_sql_error("GetIdFromName", $this->ProjectId);
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
        $query = pdo_query("SELECT count(*) FROM build WHERE id='".$this->Id."'");
        add_last_sql_error("Build::Exists", $this->ProjectId, $this->Id);

        $query_array = pdo_fetch_array($query);
        if ($query_array[0]>0) {
            return true;
        }
        return false;
    }


    // Save in the database
    public function Save()
    {
        if (!$this->Exists()) {
            $id = "";
            $idvalue = "";
            if ($this->Id) {
                $id = "id,";
                $idvalue =  qnum($this->Id).",";
            }

            if (strlen($this->Type)==0) {
                $this->Type = extract_type_from_buildstamp($this->Stamp);
            }

            $this->Name = pdo_real_escape_string($this->Name);
            $this->Stamp = pdo_real_escape_string($this->Stamp);
            $this->Type = pdo_real_escape_string($this->Type);
            $this->Generator = pdo_real_escape_string($this->Generator);
            $this->StartTime = pdo_real_escape_string($this->StartTime);
            $this->EndTime = pdo_real_escape_string($this->EndTime);
            $this->SubmitTime = pdo_real_escape_string($this->SubmitTime);
            $this->Command = pdo_real_escape_string($this->Command);
            $this->Log = pdo_real_escape_string($this->Log);

            // Compute the number of errors and warnings (this speeds up the display of the main table)
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
            } else {
                $nbuilderrors = -1;
                $nbuildwarnings = -1;
            }

            $parentId = 0;
            $justCreatedParent = false;
            if ($this->SubProjectName) {
                $parentId = $this->GetParentBuildId();
                if ($parentId == 0) {
                    // This is the first subproject to submit for a new build.
                    // Create a new parent build for it.
                    $parentId = $this->CreateParentBuild($nbuilderrors, $nbuildwarnings);
                    $justCreatedParent = true;
                }
            }
            $this->ParentId = $parentId;

            $query = "INSERT INTO build (".$id."siteid,projectid,stamp,name,type,generator,starttime,endtime,submittime,command,log,builderrors,buildwarnings,parentid)
                VALUES (".$idvalue."'$this->SiteId','$this->ProjectId','$this->Stamp','$this->Name',
                        '$this->Type','$this->Generator','$this->StartTime',
                        '$this->EndTime','$this->SubmitTime','$this->Command','$this->Log',$nbuilderrors,$nbuildwarnings, $this->ParentId)";
            if (!pdo_query($query)) {
                add_last_sql_error("Build Insert", $this->ProjectId, $this->Id);
                return false;
            }

            if (!$this->Id) {
                $this->Id = pdo_insert_id("build");
            }

            // Add the groupid
            if ($this->GroupId) {
                $query = "INSERT INTO build2group (groupid,buildid) VALUES ('$this->GroupId','$this->Id')";
                if (!pdo_query($query)) {
                    add_last_sql_error("Build Insert", $this->ProjectId, $this->Id);
                    return false;
                }
                // Associate the parent with this group too.
                if ($this->ParentId > 0) {
                    $result = pdo_query(
                            "SELECT groupid FROM build2group WHERE buildid=".qnum($this->ParentId));
                    if (pdo_num_rows($result) == 0) {
                        $query =
                            "INSERT INTO build2group (groupid,buildid)
                            VALUES ('$this->GroupId','$this->ParentId')";
                        if (!pdo_query($query)) {
                            add_last_sql_error("Build Insert", $this->ProjectId, $this->ParentId);
                            return false;
                        }
                    }
                }
            }

            // Add the subproject2build relationship:
            if ($this->SubProjectId) {
                $query = "INSERT INTO subproject2build (subprojectid,buildid) VALUES ('$this->SubProjectId','$this->Id')";
                if (!pdo_query($query)) {
                    add_last_sql_error("Build Insert", $this->ProjectId, $this->Id);
                    return false;
                }
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

            // Save the information
            if (!empty($this->Information)) {
                $this->Information->BuildId = $this->Id;
                $this->Information->Save();
            }

            // Update parent's tally of total build errors & warnings.
            if (!$justCreatedParent) {
                $this->UpdateParentBuild($nbuilderrors, $nbuildwarnings);
            }
        } else {
            if ($this->Append) {
                $this->EndTime = pdo_real_escape_string($this->EndTime);
                $this->SubmitTime = pdo_real_escape_string($this->SubmitTime);
                $this->Command = pdo_real_escape_string(' '.$this->Command);
                $this->Log = pdo_real_escape_string(' '.$this->Log);

                // Compute the number of errors and warnings (this speeds up the display of the main table)
                if ($this->InsertErrors) {
                    $nbuilderrors = $this->GetNumberOfErrors();
                    $nbuildwarnings = $this->GetNumberOfWarnings();
                    foreach ($this->Errors as $error) {
                        if ($error->Type == 0) {
                            $nbuilderrors++;
                        } else {
                            $nbuildwarnings++;
                        }
                    }
                } else {
                    $nbuilderrors = -1;
                    $nbuildwarnings = -1;
                }

                if ($this->SubProjectName) {
                    $newErrors = 0;
                    $newWarnings = 0;
                    if ($nbuilderrors > 0 || $nbuildwarnings > 0) {
                        // If we are adding errors or warnings to this build we need to know
                        // how many builderrors & buildwarnings it had previously so we can
                        // update the parent's tally properly.
                        $priorResult = pdo_single_row_query(
                                "SELECT builderrors, buildwarnings FROM build
                                WHERE id=".qnum($this->Id));
                        if ($priorResult['builderrors'] == -1) {
                            $priorResult['builderrors'] = 0;
                        }
                        if ($priorResult['buildwarnings'] == -1) {
                            $priorResult['buildwarnings'] = 0;
                        }
                        $newErrors = $nbuilderrors - $priorResult['builderrors'];
                        $newWarnings = $nbuildwarnings - $priorResult['buildwarnings'];
                    }
                    $this->ParentId = $this->GetParentBuildId();
                    $this->UpdateParentBuild($newErrors, $newWarnings);
                }

                include('config/config.php');
                if ($CDASH_DB_TYPE == 'pgsql') {
                    // pgsql doesn't have concat...

                    $query = "UPDATE build SET
                        endtime='$this->EndTime',submittime='$this->SubmitTime',
                        builderrors='$nbuilderrors',buildwarnings='$nbuildwarnings'," .
                            "command=command || '$this->Command',
                        log=log || '$this->Log'" .
                            "WHERE id=".qnum($this->Id);
                } else {
                    $query = "UPDATE build SET
                        endtime='$this->EndTime',submittime='$this->SubmitTime',
                        builderrors='$nbuilderrors',buildwarnings='$nbuildwarnings'," .
                            "command=CONCAT(command, '$this->Command'),
                        log=CONCAT(log, '$this->Log')" .
                            "WHERE id=".qnum($this->Id);
                }

                if (!pdo_query($query)) {
                    add_last_sql_error("Build Insert (Append)", $this->ProjectId, $this->Id);
                    return false;
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
            } else {
                //echo "info: nothing<br/>";
            }
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
                $message = "This build experienced errors";
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
            pdo_query("SELECT testfailed FROM build WHERE id=".qnum($this->Id));
        if (pdo_num_rows($result) > 0) {
            $build_array = pdo_fetch_array($result);
            $numTestsFailed = $build_array["testfailed"];
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
            pdo_query("SELECT testpassed FROM build WHERE id=".qnum($this->Id));
        if (pdo_num_rows($result) > 0) {
            $build_array = pdo_fetch_array($result);
            $numTestsPassed = $build_array["testpassed"];
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
            pdo_query("SELECT testnotrun FROM build WHERE id=".qnum($this->Id));
        if (pdo_num_rows($result) > 0) {
            $build_array = pdo_fetch_array($result);
            $numTestsNotRun = $build_array["testnotrun"];
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
        if (!is_numeric($numberTestsPassed) ||!is_numeric($numberTestsFailed) || !is_numeric($numberTestsNotRun)) {
            return;
        }

        // If this is a subproject build, we also have to update its parents test numbers.
        $newFailed = $numberTestsFailed - $this->GetNumberOfFailedTests();
        $newNotRun = $numberTestsNotRun - $this->GetNumberOfNotRunTests();
        $newPassed = $numberTestsPassed - $this->GetNumberOfPassedTests();
        $this->ParentId = $this->GetParentBuildId();
        $this->UpdateParentTestNumbers($newFailed, $newNotRun, $newPassed);

        // Update this build's test numbers.
        pdo_query("UPDATE build SET testnotrun='$numberTestsNotRun',
                testfailed='$numberTestsFailed',
                testpassed='$numberTestsPassed' WHERE id=".qnum($this->Id));

        add_last_sql_error("Build:UpdateTestNumbers", $this->ProjectId, $this->Id);

        // Should we should post test failures to a pull request?
        if (isset($this->PullRequest) && $numberTestsFailed > 0) {
            $message = "This build experienced failing tests";
            $url = get_server_URI(false) .
                "/viewTest.php?onlyfailed&buildid=$this->Id";
            $this->NotifyPullRequest($message, $url);
        }
    }

    /** Get the errors differences for the build */
    public function GetErrorDifferences()
    {
        if (!$this->Id) {
            add_log("BuildId is not set", "Build::GetErrorDifferences", LOG_ERR,
                    $this->ProjectId, $this->Id, CDASH_OBJECT_BUILD, $this->Id);
            return false;
        }

        $diff = array();

        $sqlquery = "SELECT id,builderrordiff.type AS builderrortype,
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
                WHERE id=".qnum($this->Id);
        $query = pdo_query($sqlquery);
        add_last_sql_error("Build:GetErrorDifferences", $this->ProjectId, $this->Id);

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
        $variables = array('builderrorspositive','builderrorsnegative',
                'buildwarningspositive','buildwarningsnegative',
                'configureerrors','configurewarnings',
                'testpassedpositive','testpassednegative',
                'testfailedpositive','testfailednegative',
                'testnotrunpositive','testnotrunnegative');
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
            add_log("BuildId is not set", "Build::ComputeDifferences", LOG_ERR,
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

    /** Compute the build errors differences */
    public function ComputeConfigureDifferences()
    {
        if (!$this->Id) {
            add_log("BuildId is not set", "Build::ComputeConfigureDifferences", LOG_ERR,
                    $this->ProjectId, $this->Id,
                    CDASH_OBJECT_BUILD, $this->Id);
            return false;
        }

        $previousbuildid = $this->GetPreviousBuildId();
        if ($previousbuildid == 0) {
            return;
        }
        compute_configure_difference($this->Id, $previousbuildid, 1); // warnings
    }

    /** Compute the test timing as a weighted average of the previous test.
     *  Also compute the difference in errors and tests between builds.
     *  We do that in one shot for speed reasons. */
    public function ComputeTestTiming()
    {
        if (!$this->Id) {
            add_log("BuildId is not set", "Build::ComputeTestTiming", LOG_ERR,
                    $this->ProjectId, $this->Id, CDASH_OBJECT_BUILD, $this->Id);
            return false;
        }

        if (!$this->ProjectId) {
            add_log("ProjectId is not set", "Build::ComputeTestTiming", LOG_ERR,
                    $this->ProjectId, $this->Id, CDASH_OBJECT_BUILD, $this->Id);
            return false;
        }

        $testtimestatusfailed = 0;

        // TEST TIMING
        $weight = 0.3; // weight of the current test compared to the previous mean/std (this defines a window)
        $build = pdo_query("SELECT projectid,starttime,siteid,name,type FROM build WHERE id=".qnum($this->Id));
        add_last_sql_error("Build:ComputeTestTiming", $this->ProjectId, $this->Id);

        $buildid = $this->Id;
        $build_array = pdo_fetch_array($build);
        $buildname = $build_array["name"];
        $buildtype = $build_array["type"];
        $starttime = $build_array["starttime"];
        $siteid = $build_array["siteid"];
        $projectid = $build_array["projectid"];

        $project = pdo_query("SELECT testtimestd,testtimestdthreshold,testtimemaxstatus FROM project WHERE id=".qnum($this->ProjectId));
        add_last_sql_error("Build:ComputeTestTiming", $this->ProjectId, $this->Id);

        $project_array = pdo_fetch_array($project);
        $projecttimestd = $project_array["testtimestd"];
        $projecttimestdthreshold = $project_array["testtimestdthreshold"];
        $projecttestmaxstatus = $project_array["testtimemaxstatus"];

        // Find the previous build
        $previousbuildid = $this->GetPreviousBuildId();
        if ($previousbuildid == 0) {
            return;
        }

        // If we have one
        if ($previousbuildid>0) {
            compute_test_difference($buildid, $previousbuildid, 0, $projecttestmaxstatus); // not run
            compute_test_difference($buildid, $previousbuildid, 1, $projecttestmaxstatus); // fail
            compute_test_difference($buildid, $previousbuildid, 2, $projecttestmaxstatus); // pass
            compute_test_difference($buildid, $previousbuildid, 3, $projecttestmaxstatus); // time

            // Loop through the tests
            $tests = pdo_query("SELECT build2test.time,build2test.testid,test.name,build2test.status,
                    build2test.timestatus
                    FROM build2test,test WHERE build2test.buildid=".qnum($this->Id)."
                    AND build2test.testid=test.id
                    ");
            add_last_sql_error("Build:ComputeTestTiming", $this->ProjectId, $this->Id);

            // Find the previous test
            $previoustest = pdo_query("SELECT build2test.testid,test.name FROM build2test,test
                    WHERE build2test.buildid=".qnum($previousbuildid)."
                    AND test.id=build2test.testid
                    ");
            add_last_sql_error("Build:ComputeTestTiming", $this->ProjectId, $this->Id);

            $testarray = array();
            while ($test_array = pdo_fetch_array($previoustest)) {
                $test = array();
                $test['id'] = $test_array["testid"];
                $test['name'] = $test_array["name"];
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
                    if ($test['name']==$testname) {
                        $previoustestid = $test['id'];
                        break;
                    }
                }

                if ($previoustestid>0) {
                    $previoustest = pdo_query("SELECT timemean,timestd,timestatus FROM build2test
                            WHERE buildid=".qnum($previousbuildid)."
                            AND build2test.testid=".qnum($previoustestid)
                            );
                    add_last_sql_error("Build:ComputeTestTiming", $this->ProjectId, $this->Id);

                    $previoustest_array = pdo_fetch_array($previoustest);
                    $previoustimemean = $previoustest_array["timemean"];
                    $previoustimestd = $previoustest_array["timestd"];
                    $previoustimestatus = $previoustest_array["timestatus"];

                    if ($teststatus == "passed") {
                        // if the current test passed

                        if ($timestatus>0 && $timestatus<=$projecttestmaxstatus) {
                            // if we are currently detecting the time changed we should use previous mean std

                            $timemean = $previoustimemean;
                            $timestd = $previoustimestd;
                        } else {
                            // Update the mean and std
                            $timemean = (1-$weight)*$previoustimemean+$weight*$testtime;
                            $timestd = sqrt((1-$weight)*$previoustimestd*$previoustimestd + $weight*($testtime-$timemean)*($testtime-$timemean));
                        }

                        // Check the current status
                        if ($previoustimestd<$projecttimestdthreshold) {
                            $previoustimestd = $projecttimestdthreshold;
                        }

                        if ($testtime > $previoustimemean+$projecttimestd*$previoustimestd) {
                            // only do positive std

                            $timestatus = $previoustimestatus+1; // flag
                        } else {
                            $timestatus = 0; // reset the time status to 0
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

                pdo_query("UPDATE build2test SET timemean=".qnum($timemean).",timestd=".qnum($timestd).",timestatus=".qnum($timestatus)."
                        WHERE buildid=".qnum($this->Id)." AND testid=".qnum($testid));
                add_last_sql_error("Build:ComputeTestTiming", $this->ProjectId, $this->Id);
                if ($timestatus>=$projecttestmaxstatus) {
                    $testtimestatusfailed++;
                }
            }  // end loop through the test
        } else {
            // this is the first build

            $timestd = 0;
            $timestatus = 0;

            // Loop throught the tests
            $tests = pdo_query("SELECT time,testid FROM build2test WHERE buildid=".qnum($this->Id));
            while ($test_array = pdo_fetch_array($tests)) {
                $timemean = $test_array['time'];
                $testid = $test_array['testid'];

                pdo_query("UPDATE build2test SET timemean=".qnum($timemean).",timestd=".qnum($timestd).",timestatus=".qnum($timestatus)."
                        WHERE buildid=".qnum($this->Id)." AND testid=".qnum($testid));
                add_last_sql_error("Build:ComputeTestTiming", $this->ProjectId, $this->Id);
                if ($timestatus>=$projecttestmaxstatus) {
                    $testtimestatusfailed++;
                }
            } // loop through the tests
        } // end if first build

        pdo_query("UPDATE build SET testtimestatusfailed=".qnum($testtimestatusfailed)." WHERE id=".$this->Id);
        add_last_sql_error("Build:ComputeTestTiming", $this->ProjectId, $this->Id);
        return true;
    } // end function compute_test_timing


    /** Compute the user statistics */
    public function ComputeUpdateStatistics()
    {
        if (!$this->Id) {
            add_log("Id is not set", "Build::ComputeUpdateStatistics", LOG_ERR,
                    $this->ProjectId, $this->Id, CDASH_OBJECT_BUILD, $this->Id);
            return false;
        }

        if (!$this->ProjectId) {
            add_log("ProjectId is not set", "Build::ComputeUpdateStatistics", LOG_ERR, 0, $this->Id);
            return false;
        }

        $previousbuildid = $this->GetPreviousBuildId();

        // Find the errors, warnings and test failures
        // Find the current number of errors
        $errors = pdo_query("SELECT builderrors,buildwarnings,testnotrun,testfailed FROM build WHERE id=".qnum($this->Id));
        add_last_sql_error("Build:ComputeUpdateStatistics", $this->ProjectId, $this->Id);
        $errors_array = pdo_fetch_array($errors);
        $nerrors = $errors_array[0];
        $nwarnings = $errors_array[1];
        $ntests = $errors_array[2]+$errors_array[3];

        // If we have a previous build
        if ($previousbuildid>0) {
            $previouserrors = pdo_query("SELECT builderrors,buildwarnings,testnotrun,testfailed FROM build WHERE id=".qnum($previousbuildid));
            add_last_sql_error("Build:ComputeUpdateStatistics", $this->ProjectId, $this->Id);
            $previouserrors_array  = pdo_fetch_array($previouserrors);
            $npreviouserrors = $previouserrors_array[0];
            $npreviouswarnings = $previouserrors_array[1];
            $nprevioustests = $previouserrors_array[2]+$previouserrors_array[3];

            $warningdiff = $nwarnings-$npreviouswarnings;
            $errordiff = $nerrors-$npreviouserrors;
            $testdiff = $ntests-$nprevioustests;
        } else {
            // this is the first build

            $warningdiff = $nwarnings;
            $errordiff = $nerrors;
            $testdiff = $ntests;
        }

        // Find the number of different users
        $nauthors_array = pdo_fetch_array(pdo_query("SELECT count(author) FROM (SELECT uf.author FROM updatefile AS uf,build2update AS b2u
            WHERE b2u.updateid=uf.updateid AND b2u.buildid=".qnum($this->Id)." GROUP BY author) AS test"));
        add_last_sql_error("Build:ComputeUpdateStatistics", $this->ProjectId, $this->Id);
        $nauthors = $nauthors_array[0];

        $newbuild = 1;
        $previousauthor = "";
        // Loop through the updated files
        $updatefiles = pdo_query("SELECT author,email,checkindate,filename FROM updatefile AS uf,build2update AS b2u
                WHERE b2u.updateid=uf.updateid AND b2u.buildid=".qnum($this->Id).
                " AND checkindate>'1980-01-01T00:00:00' ORDER BY author ASC, checkindate ASC");
        add_last_sql_error("Build:ComputeUpdateStatistics", $this->ProjectId, $this->Id);
        $nupdatedfiles = pdo_num_rows($updatefiles);

        while ($updatefiles_array = pdo_fetch_array($updatefiles)) {
            $checkindate = $updatefiles_array["checkindate"];
            $author = $updatefiles_array["author"];
            $filename = $updatefiles_array["filename"];
            $email = $updatefiles_array["email"];

            if ($author != $previousauthor) {
                $newbuild = 1;
            }
            $previousauthor  = $author;

            // If we have more than one author we need to find who caused the error
            if ($nauthors>1) {
                $warningdiff = $this->FindRealErrors("WARNING", $author, $this->Id, $filename);
                $errordiff = $this->FindRealErrors("ERROR", $author, $this->Id, $filename);
                $testdiff = 0; // no idea how to find if the update file is responsible for the test failure
            } else {
                $warningdiff /= $nupdatedfiles;
                $errordiff /= $nupdatedfiles;
                $testdiff /= $nupdatedfiles;
            }

            $this->AddUpdateStatistics($author, $email, $checkindate, $newbuild,
                    $warningdiff, $errordiff, $testdiff);

            $newbuild = 0;
        } // end updatefiles

        return true;
    } // end function ComputeUpdateStatistics


    /** Helper function for AddUpdateStatistics */
    private function AddUpdateStatistics($author, $email, $checkindate, $firstbuild,
            $warningdiff, $errordiff, $testdiff)
    {
        // Find the userid from the author name
        $user2project = pdo_query("SELECT up.userid FROM user2project AS up,user2repository AS ur
                WHERE up.userid=ur.userid
                AND up.projectid=".qnum($this->ProjectId)."
                AND (ur.credential='$author' OR ur.credential='$email')
                AND (ur.projectid=0 OR ur.projectid=".qnum($this->ProjectId).")"
                );
        if (pdo_num_rows($user2project)==0) {
            return;
        }

        $user2project_array = pdo_fetch_array($user2project);
        $userid = $user2project_array["userid"];

        // Check if we already have a checkin date for this user
        $userstatistics = pdo_query("SELECT totalupdatedfiles
                FROM userstatistics WHERE userid=".qnum($userid)." AND projectid=".qnum($this->ProjectId)." AND checkindate='$checkindate'");
        add_last_sql_error("Build:AddUpdateStatistics", $this->ProjectId, $this->Id);

        if (pdo_num_rows($userstatistics)>0) {
            $userstatistics_array = pdo_fetch_array($userstatistics);
            $totalbuilds = 0;
            if ($firstbuild==1) {
                $totalbuilds=1;
            }

            $nfailedwarnings = 0;
            $nfixedwarnings = 0;
            $nfailederrors = 0;
            $nfixederrors = 0;
            $nfailedtests = 0;
            $nfixedtests = 0;

            if ($warningdiff>0) {
                $nfailedwarnings = $warningdiff;
            } else {
                $nfixedwarnings = abs($warningdiff);
            }

            if ($errordiff>0) {
                $nfailederrors = $errordiff;
            } else {
                $nfixederrors = abs($errordiff);
            }

            if ($testdiff>0) {
                $nfailedtests = $testdiff;
            } else {
                $nfixedtests = abs($testdiff);
            }

            pdo_query("UPDATE userstatistics
                    SET totalupdatedfiles=totalupdatedfiles+".qnum(1).",
                    totalbuilds=totalbuilds+".qnum($totalbuilds).",
                    nfixedwarnings=nfixedwarnings+".qnum($nfixedwarnings).",
                    nfailedwarnings=nfailedwarnings+".qnum($nfailedwarnings).",
                    nfixederrors=nfixederrors+".qnum($nfixederrors).",
                    nfailederrors=nfailederrors+".qnum($nfailederrors).",
                    nfixedtests=nfixedtests+".qnum($nfixedtests).",
                    nfailedtests=nfailedtests+".qnum($nfailedtests)."
                    WHERE userid=".qnum($userid)." AND projectid=".qnum($this->ProjectId)." AND checkindate>='$checkindate'");
            add_last_sql_error("Build:AddUpdateStatistics", $this->ProjectId, $this->Id);
        } else {
            // insert into the database

            if ($warningdiff>0) {
                $nfixedwarnings = 0;
                $nfailedwarnings = $warningdiff;
            } else {
                $nfixedwarnings = $warningdiff;
                $nfailedwarnings = 0;
            }

            if ($errordiff>0) {
                $nfixederrors = 0;
                $nfailederrors = $errordiff;
            } else {
                $nfixederrors = $errordiff;
                $nfailederrors = 0;
            }

            if ($testdiff>0) {
                $nfixedtests = 0;
                $nfailedtests = $testdiff;
            } else {
                $nfixedtests = $testdiff;
                $nfailedtests = 0;
            }

            $totalupdatedfiles=1;
            $totalbuilds = 0;
            if ($firstbuild==1) {
                $totalbuilds=1;
            }

            pdo_query("UPDATE userstatistics
                    SET totalupdatedfiles=totalupdatedfiles+".qnum(1).",
                    totalbuilds=totalbuilds+".qnum(1).",
                    nfixedwarnings=nfixedwarnings+".qnum($nfixedwarnings).",
                    nfailedwarnings=nfailedwarnings+".qnum($nfailedwarnings).",
                    nfixederrors=nfixederrors+".qnum($nfixederrors).",
                    nfailederrors=nfailederrors+".qnum($nfailederrors).",
                    nfixedtests=nfixedtests+".qnum($nfixedtests).",
                    nfailedtests=nfailedtests+".qnum($nfailedtests)."
                    WHERE userid=".qnum($userid)." AND projectid=".qnum($this->ProjectId)." AND checkindate>'$checkindate'");

            add_last_sql_error("Build:AddUpdateStatistics", $this->ProjectId, $this->Id);

            // Find the previous userstatistics
            $previous = pdo_query("SELECT totalupdatedfiles,totalbuilds,nfixedwarnings,nfailedwarnings,nfixederrors,nfailederrors,nfixedtests,nfailedtests
                    FROM userstatistics WHERE userid='$userid' AND projectid=".qnum($this->ProjectId)." AND checkindate<'$checkindate' ORDER BY checkindate DESC LIMIT 1");
            add_last_sql_error("Build:AddUpdateStatistics", $this->ProjectId, $this->Id);
            if (pdo_num_rows($previous)>0) {
                $previous_array = pdo_fetch_array($previous);
                $totalupdatedfiles += $previous_array["totalupdatedfiles"];
                $totalbuilds += $previous_array["totalbuilds"];
                $nfixedwarnings += $previous_array["nfixedwarnings"];
                $nfailedwarnings += $previous_array["nfailedwarnings"];
                $nfixederrors += $previous_array["nfixederrors"];
                $nfailederrors += $previous_array["nfailederrors"];
                $nfixedtests += $previous_array["nfixedtests"];
                $nfailedtests += $previous_array["nfailedtests"];
            }

            pdo_query("INSERT INTO userstatistics (userid,projectid,checkindate,totalupdatedfiles,totalbuilds,
                nfixedwarnings,nfailedwarnings,nfixederrors,nfailederrors,nfixedtests,nfailedtests)
                    VALUES (".qnum($userid).",".qnum($this->ProjectId).",'$checkindate',$totalupdatedfiles,$totalbuilds,$nfixedwarnings,
                        $nfailedwarnings,$nfixederrors,$nfailederrors,$nfixedtests,$nfailedtests)
                    ");
            add_last_sql_error("Build:AddUpdateStatistics", $this->ProjectId, $this->Id);
        }
    } // end AddUpdateStatistics


    /** Find the errors associated with a user
     *  For now the author is not used, we assume that the filename is sufficient */
    private function FindRealErrors($type, $author, $buildid, $filename)
    {
        $errortype=0;
        if ($type=="WARNING") {
            $errortype=1;
        }

        $errors = pdo_query("SELECT count(*) FROM builderror WHERE type=".qnum($errortype)."
                AND sourcefile LIKE '%$filename%' AND buildid=".qnum($buildid));
        $errors_array  = pdo_fetch_array($errors);
        $nerrors = $errors_array[0];
        // Adding the buildfailure
        $failures = pdo_query(
                "SELECT count(*) FROM buildfailure AS bf
                LEFT JOIN buildfailuredetails AS bfd ON (bfd.id=bf.detailsid)
                WHERE bfd.type=".qnum($errortype)." AND
                bf.sourcefile LIKE '%$filename%' AND bf.buildid=".qnum($buildid));
        $failures_array  = pdo_fetch_array($failures);
        $nerrors += $failures_array[0];

        return $nerrors;
    } // end FindRealErrors

    /** Return the name of a build */
    public function GetName()
    {
        if (!$this->Id) {
            echo "Build GetName(): Id not set";
            return false;
        }

        $build = pdo_query("SELECT name FROM build WHERE id=".qnum($this->Id));
        if (!$build) {
            add_last_sql_error("Build:GetName", $this->ProjectId, $this->Id);
            return false;
        }
        $build_array = pdo_fetch_array($build);
        return $build_array['name'];
    }

    /** Get all the labels for a given build */
    public function GetLabels($labelarray=array())
    {
        if (!$this->Id) {
            echo "Build GetLabels(): Id not set";
            return false;
        }

        $sql = "SELECT label.id as labelid FROM label WHERE
            label.id IN (SELECT labelid AS id FROM label2build WHERE label2build.buildid=".qnum($this->Id).")";

        if (empty($labelarray) || isset($labelarray['test']['errors'])) {
            $sql .= " OR label.id IN (SELECT labelid AS id FROM label2test WHERE label2test.buildid=".qnum($this->Id).")";
        }
        if (empty($labelarray) || isset($labelarray['coverage']['errors'])) {
            $sql .= " OR label.id IN (SELECT labelid AS id FROM label2coveragefile WHERE label2coveragefile.buildid=".qnum($this->Id).")";
        }
        if (empty($labelarray) || isset($labelarray['build']['errors'])) {
            $sql .= "  OR label.id IN (
                SELECT l2bf.labelid AS id
                FROM label2buildfailure AS l2bf
                LEFT JOIN buildfailure AS bf ON (bf.id=l2bf.buildfailureid)
                LEFT JOIN buildfailuredetails AS bfd ON (bfd.id=bf.detailsid)
                WHERE bfd.type='0' AND bf.buildid=".qnum($this->Id).")";
        }
        if (empty($labelarray) || isset($labelarray['build']['warnings'])) {
            $sql .= "  OR label.id IN (
                SELECT l2bf.labelid AS id
                FROM label2buildfailure AS l2bf
                LEFT JOIN buildfailure AS bf ON (bf.id=l2bf.buildfailureid)
                LEFT JOIN buildfailuredetails AS bfd ON (bfd.id=bf.detailsid)
                WHERE bfd.type='1' AND bf.buildid=".qnum($this->Id).")";
        }
        if (empty($labelarray) || isset($labelarray['dynamicanalysis']['errors'])) {
            $sql .= " OR label.id IN (SELECT labelid AS id FROM label2dynamicanalysis,dynamicanalysis
                WHERE label2dynamicanalysis.dynamicanalysisid=dynamicanalysis.id AND dynamicanalysis.buildid=".qnum($this->Id).")";
        }

        $labels = pdo_query($sql);

        if (!$labels) {
            add_last_sql_error("Build:GetLabels", $this->ProjectId, $this->Id);
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
            echo "Build GetGroup(): Id not set";
            return false;
        }
        $group = pdo_query("SELECT groupid FROM build2group WHERE buildid=".qnum($this->Id));
        if (!$group) {
            add_last_sql_error("Build:GetGroup", $this->ProjectId, $this->Id);
            return false;
        }

        $buildgroup_array = pdo_fetch_array($group);
        return $buildgroup_array["groupid"];
    }

    /** Get the number of errors for a build */
    public function GetNumberOfErrors()
    {
        if (!$this->Id) {
            echo "Build::GetNumberOfErrors(): Id not set";
            return false;
        }

        $builderror = pdo_query("SELECT builderrors FROM build WHERE id=".qnum($this->Id));
        add_last_sql_error("Build:GetNumberOfErrors", $this->ProjectId, $this->Id);
        $builderror_array = pdo_fetch_array($builderror);
        if ($builderror_array[0] == -1) {
            return 0;
        }
        return $builderror_array[0];
    } // end GetNumberOfErrors()

    /** Get the number of warnings for a build */
    public function GetNumberOfWarnings()
    {
        if (!$this->Id) {
            echo "Build::GetNumberOfWarnings(): Id not set";
            return false;
        }

        $builderror = pdo_query("SELECT buildwarnings FROM build WHERE id=".qnum($this->Id));
        add_last_sql_error("Build:GetNumberOfWarnings", $this->ProjectId, $this->Id);
        $builderror_array = pdo_fetch_array($builderror);
        if ($builderror_array[0] == -1) {
            return 0;
        }
        return $builderror_array[0];
    } // end GetNumberOfWarnings()

    /* Return all uploaded files or URLs for this build */
    public function GetUploadedFilesOrUrls()
    {
        if (!$this->Id) {
            echo "Build::GetUploadedFilesOrUrls(): Id not set";
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

    /** Get the parent's build id */
    public function GetParentBuildId()
    {
        if (!$this->SiteId || !$this->Name || !$this->Stamp) {
            return 0;
        }

        $parent = pdo_single_row_query(
                "SELECT id FROM build WHERE parentid=-1 AND
                siteid='$this->SiteId' AND name='$this->Name' AND stamp='$this->Stamp'");

        if ($parent && array_key_exists('id', $parent)) {
            return $parent['id'];
        }
        return 0;
    }

    /** Create a new build as a parent of $this.
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
        // that matches our name, site, and stamp.
        $query = "SELECT id FROM build
            WHERE parentid = 0 AND name = '$this->Name' AND
            siteid = '$this->SiteId' AND stamp = '$this->Stamp'";
        $result = pdo_query($query);
        if (pdo_num_rows($result) > 0) {
            $result_array = pdo_fetch_array($result);
            $parentId = $result_array['id'];
            $this->ParentId = $parentId;

            // Mark it as a parent (parentid of -1) and update its tally of
            // build errors & warnings.
            pdo_query("UPDATE build SET parentid = -1 WHERE id = $parentId");
            $this->UpdateParentBuild($numErrors, $numWarnings);
        } else {
            // Create the parent build here.  Note how parent builds
            // are indicated by parentid == -1.
            $query = "INSERT INTO build
                (parentid, siteid, projectid, stamp, name, type, generator,
                 starttime, endtime, submittime, builderrors, buildwarnings)
                VALUES
                ('-1','$this->SiteId','$this->ProjectId','$this->Stamp',
                 '$this->Name','$this->Type','$this->Generator',
                 '$this->StartTime','$this->EndTime','$this->SubmitTime',
                 $numErrors,$numWarnings)";
            if (!pdo_query($query)) {
                add_last_sql_error("Build Insert Parent", $this->ProjectId, $this->Id);
                return false;
            }

            $parentId = pdo_insert_id("build");
        }

        // Since we just created a parent we should also update any existing
        // builds that should be a child of this parent but aren't yet.
        // This happens when Update.xml is parsed first, because it doesn't
        // contain info about what subproject it came from.
        // TODO: maybe we don't need this any more?
        $query =
            "UPDATE build SET parentid=$parentId
            WHERE parentid=0 AND siteid='$this->SiteId' AND
            name='$this->Name' AND stamp='$this->Stamp'";
        if (!pdo_query($query)) {
            add_last_sql_error(
                    "Build Insert Update Parent", $this->ProjectId, $parentId);
        }

        return $parentId;
    }

    /**
     * Update our parent build so that it is an accurate summary
     * of all of its subprojects.
     **/
    public function UpdateParentBuild($newErrors, $newWarnings)
    {
        if ($this->ParentId < 1) {
            return;
        }

        $clauses = array();

        $parent = pdo_single_row_query(
                "SELECT builderrors, buildwarnings, starttime, endtime
                FROM build WHERE id='$this->ParentId'");

        // Check if we need to modify builderrors or buildwarnings.
        if ($parent['builderrors'] == -1) {
            $parent['builderrors'] = 0;
        }
        if ($parent['buildwarnings'] == -1) {
            $parent['buildwarnings'] = 0;
        }
        if ($newErrors > -1) {
            $numErrors = $parent['builderrors'] + $newErrors;
            $clauses[] = "builderrors = $numErrors";
        }
        if ($newWarnings > -1) {
            $numWarnings = $parent['buildwarnings'] + $newWarnings;
            $clauses[] = "buildwarnings = $numWarnings";
        }

        // Check if we need to modify starttime or endtime.
        if (strtotime($parent['starttime']) > strtotime($this->StartTime)) {
            $clauses[] = "starttime = '$this->StartTime'";
        }
        if (strtotime($parent['endtime']) < strtotime($this->EndTime)) {
            $clauses[] = "endtime = '$this->EndTime'";
        }

        $num_clauses = count($clauses);
        if ($num_clauses > 0) {
            $query = "UPDATE build SET " . $clauses[0];
            for ($i = 1; $i < $num_clauses; $i++) {
                $query .= ", " . $clauses[$i];
            }
            $query .= " WHERE id = '$this->ParentId'";
            if (!pdo_query($query)) {
                add_last_sql_error("UpdateParentBuild", $this->ProjectId, $this->ParentId);
                return false;
            }
        }
    }

    /** Update the testing numbers for our parent build. */
    public function UpdateParentTestNumbers($newFailed, $newNotRun, $newPassed)
    {
        if ($this->ParentId < 1) {
            return;
        }

        $numFailed = 0;
        $numNotRun = 0;
        $numPassed = 0;

        $parent = pdo_single_row_query(
                "SELECT testfailed, testnotrun, testpassed
                FROM build WHERE id=".qnum($this->ParentId));

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
                WHERE id=".qnum($this->ParentId));

        add_last_sql_error("Build:UpdateParentTestNumbers", $this->ProjectId, $this->Id);

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
                WHERE id=".qnum($this->Id));

        add_last_sql_error("Build:SetNumberOfConfigureWarnings",
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
                WHERE id=".qnum($this->Id));

        add_last_sql_error("Build:SetNumberOfConfigureErrors",
                $this->ProjectId, $this->Id);

        // Should we post configure errors to a pull request?
        if (isset($this->PullRequest) && $numErrors > 0) {
            $message = "This build failed to configure";
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
        $this->ParentId = $this->GetParentBuildId();
        if ($this->ParentId < 1) {
            return;
        }

        $numErrors = 0;
        $numWarnings = 0;

        $parent = pdo_single_row_query(
                "SELECT configureerrors, configurewarnings
                FROM build WHERE id=".qnum($this->ParentId));

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
                WHERE id=".qnum($this->ParentId));

        add_last_sql_error("Build:UpdateParentConfigureNumbers",
                $this->ProjectId, $this->Id);
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
                "SELECT notified FROM build WHERE id=".qnum($idToNotify));
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
        $message .= ".";

        // Post the PR comment & mark this build as 'notified'.
        post_pull_request_comment($this->ProjectId, $this->PullRequest,
                $message, $url);
        pdo_query("UPDATE build SET notified='1' WHERE id=".qnum($idToNotify));
    }

    public function SetConfigureDuration($duration)
    {
        if (!$this->Id || !is_numeric($this->Id)) {
            return;
        }

        // Set configure duration for this build.
        pdo_query(
                "UPDATE build SET configureduration=$duration
                WHERE id=".qnum($this->Id));

        add_last_sql_error("Build:SetConfigureDuration",
                $this->ProjectId, $this->Id);

        // If this is a child build, add this duration
        // to the parent's configure duration sum.
        $this->ParentId = $this->GetParentBuildId();
        if ($this->ParentId > 0) {
            pdo_query(
                    "UPDATE build
                    SET configureduration = configureduration + $duration
                    WHERE id=".qnum($this->ParentId));

            add_last_sql_error("Build:SetConfigureDuration",
                    $this->ProjectId, $this->ParentId);
        }
    }
} // end class Build;
