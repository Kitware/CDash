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

require_once  'include/common.php';
require_once 'include/cdashmail.php';

use CDash\Collection\SubscriberCollection;

use CDash\Config;
use CDash\Database;
use CDash\Messaging\Preferences\BitmaskNotificationPreferences;
use CDash\Messaging\Preferences\NotificationPreferences;
use CDash\Messaging\Preferences\NotificationPreferencesInterface;
use CDash\ServiceContainer;
use CDash\Model\Subscriber;

/** Main project class */
class Project
{
    const PROJECT_ADMIN = 2;
    const SITE_MAINTAINER = 1;
    const PROJECT_USER = 0;

    public $Name;
    public $Id;
    public $Description;
    public $HomeUrl;
    public $CvsUrl;
    public $DocumentationUrl;
    public $BugTrackerUrl;
    public $BugTrackerFileUrl;
    public $BugTrackerNewIssueUrl;
    public $BugTrackerType;
    public $ImageId;
    public $Public;
    public $CoverageThreshold;
    public $TestingDataUrl;
    public $NightlyTime;
    public $GoogleTracker;
    public $EmailLowCoverage;
    public $EmailTestTimingChanged;
    public $EmailBrokenSubmission;
    public $EmailRedundantFailures;
    public $CvsViewerType;
    public $TestTimeStd;
    public $TestTimeStdThreshold;
    public $ShowTestTime;
    public $TestTimeMaxStatus;
    public $EmailMaxItems;
    public $EmailMaxChars;
    public $EmailAdministrator;
    public $ShowIPAddresses;
    public $DisplayLabels;
    public $ShareLabelFilters;
    public $AuthenticateSubmissions;
    public $ShowCoverageCode;
    public $AutoremoveTimeframe;
    public $AutoremoveMaxBuilds;
    public $UploadQuota;
    public $RobotName;
    public $RobotRegex;
    public $CTestTemplateScript;
    public $WebApiKey;
    public $WarningsFilter;
    public $ErrorsFilter;
    /** @var \PDO $PDO */
    private $PDO;

    /**
     * @var SubscriberCollection
     */
    private $SubscriberCollection;

    public function __construct()
    {
        $this->Initialize();
    }

    /** Initialize non defined variables */
    private function Initialize()
    {
        if (empty($this->EmailLowCoverage)) {
            $this->EmailLowCoverage = 0;
        }
        if (empty($this->EmailTestTimingChanged)) {
            $this->EmailTestTimingChanged = 0;
        }
        if (empty($this->EmailBrokenSubmission)) {
            $this->EmailBrokenSubmission = 0;
        }
        if (empty($this->EmailRedundantFailures)) {
            $this->EmailRedundantFailures = 0;
        }
        if (empty($this->EmailAdministrator)) {
            $this->EmailAdministrator = 0;
        }
        if (empty($this->ShowIPAddresses)) {
            $this->ShowIPAddresses = 0;
        }
        if (empty($this->ShowTestTime)) {
            $this->ShowTestTime = 0;
        }
        if (empty($this->DisplayLabels)) {
            $this->DisplayLabels = 0;
        }
        if (empty($this->ShareLabelFilters)) {
            $this->ShareLabelFilters = 0;
        }
        if (empty($this->AuthenticateSubmissions)) {
            $this->AuthenticateSubmissions = 0;
        }
        if (empty($this->ShowCoverageCode)) {
            $this->ShowCoverageCode = 0;
        }
        if (empty($this->AutoremoveTimeframe)) {
            $this->AutoremoveTimeframe = 0;
        }
        if (empty($this->AutoremoveMaxBuilds)) {
            $this->AutoremoveMaxBuilds = 300;
        }
        if (empty($this->UploadQuota)) {
            $this->UploadQuota = 0;
        }
        if (empty($this->WebApiKey)) {
            $this->WebApiKey = '';
        }
        if (empty($this->EmailMaxItems)) {
            $this->EmailMaxItems = 5;
        }
        if (empty($this->EmailMaxChars)) {
            $this->EmailMaxChars = 255;
        }
        if (empty($this->WarningsFilter)) {
            $this->WarningsFilter = '';
        }
        if (empty($this->ErrorsFilter)) {
            $this->ErrorsFilter = '';
        }
        $this->PDO = Database::getInstance()->getPdo();
    }

    /** Add a build group */
    public function AddBuildGroup($buildgroup)
    {
        $buildgroup->SetProjectId($this->Id);
        $buildgroup->Save();
    }

    /** Delete a project */
    public function Delete()
    {
        if (!$this->Id) {
            return false;
        }
        // Remove the project groups and rules
        $buildgroup = pdo_query("SELECT * FROM buildgroup WHERE projectid=$this->Id");
        while ($buildgroup_array = pdo_fetch_array($buildgroup)) {
            $groupid = $buildgroup_array['id'];
            pdo_query("DELETE FROM buildgroupposition WHERE buildgroupid=$groupid");
            pdo_query("DELETE FROM build2grouprule WHERE groupid=$groupid");
            pdo_query("DELETE FROM build2group WHERE groupid=$groupid");
        }

        pdo_query("DELETE FROM buildgroup WHERE projectid=$this->Id");
        pdo_query("DELETE FROM blockbuild WHERE projectid=$this->Id");
        pdo_query("DELETE FROM user2project WHERE projectid=$this->Id");
        pdo_query("DELETE FROM labelemail WHERE projectid=$this->Id");
        pdo_query("DELETE FROM labelemail WHERE projectid=$this->Id");
        pdo_query("DELETE FROM project2repositories WHERE projectid=$this->Id");

        $dailyupdate = pdo_query("SELECT id FROM dailyupdate WHERE projectid=$this->Id");
        while ($dailyupdate_array = pdo_fetch_array($dailyupdate)) {
            $dailyupdateid = $dailyupdate_array['id'];
            pdo_query("DELETE FROM dailyupdatefile WHERE dailyupdateid='$dailyupdateid'");
        }

        pdo_query("DELETE FROM dailyupdate WHERE projectid=$this->Id");
        pdo_query("DELETE FROM projectrobot WHERE projectid=$this->Id");
        pdo_query("DELETE FROM projectjobscript WHERE projectid=$this->Id");
        pdo_query("DELETE FROM build_filters WHERE projectid=$this->Id");

        // Delete any repositories that aren't shared with other projects.
        $repositories_query = pdo_query(
            'SELECT repositoryid FROM project2repositories
       WHERE projectid=' . qnum($this->Id) . '
       ORDER BY repositoryid');
        add_last_sql_error('Project DeleteRepositories1', $this->Id);
        while ($repository_array = pdo_fetch_array($repositories_query)) {
            $repoid = $repository_array['repositoryid'];
            $projects_query = pdo_query(
                'SELECT projectid FROM project2repositories
         WHERE repositoryid=' . qnum($repoid));
            add_last_sql_error('Project DeleteRepositories1', $this->Id);
            if (pdo_num_rows($projects_query) > 1) {
                continue;
            }
            pdo_query("DELETE FROM repositories WHERE id=$repoid");
        }
        pdo_query("DELETE FROM project2repositories WHERE projectid=$this->Id");

        pdo_query("DELETE FROM project WHERE id=$this->Id");
    }

    /** Return if a project exists */
    public function Exists()
    {
        // If no id specify return false
        if (!$this->Id) {
            return false;
        }
        /** @var \PDOStatement $stmt */
        $stmt = $this->PDO->prepare("SELECT count(*) FROM project WHERE id=:id");
        $stmt->bindParam(':id', $this->Id);
        $stmt->execute();
        $query_array = pdo_fetch_array($stmt);
        if ($query_array[0] > 0) {
            return true;
        }
        return false;
    }

    // Save the project in the database
    public function Save()
    {
        // Escape the values
        $Description = pdo_real_escape_string($this->Description);
        $HomeUrl = pdo_real_escape_string($this->HomeUrl);
        $CvsUrl = pdo_real_escape_string($this->CvsUrl);
        $DocumentationUrl = pdo_real_escape_string($this->DocumentationUrl);
        $BugTrackerUrl = pdo_real_escape_string($this->BugTrackerUrl);
        $BugTrackerFileUrl = pdo_real_escape_string($this->BugTrackerFileUrl);
        $BugTrackerNewIssueUrl = pdo_real_escape_string($this->BugTrackerNewIssueUrl);
        $BugTrackerType = pdo_real_escape_string($this->BugTrackerType);
        $TestingDataUrl = pdo_real_escape_string($this->TestingDataUrl);
        $NightlyTime = pdo_real_escape_string($this->NightlyTime);
        $GoogleTracker = pdo_real_escape_string($this->GoogleTracker);
        $RobotName = pdo_real_escape_string($this->RobotName);
        $RobotRegex = pdo_real_escape_string($this->RobotRegex);
        $Name = pdo_real_escape_string($this->Name);
        $CvsViewerType = pdo_real_escape_string($this->CvsViewerType);
        $WarningsFilter = pdo_real_escape_string($this->WarningsFilter);
        $ErrorsFilter = pdo_real_escape_string($this->ErrorsFilter);

        // Check if the project is already
        if ($this->Exists()) {
            // Trim the name
            $this->Name = trim($this->Name);
            $this->Initialize();
            // Update the project
            $query = 'UPDATE project SET ';
            $query .= "description='" . $Description . "'";
            $query .= ",homeurl='" . $HomeUrl . "'";
            $query .= ",cvsurl='" . $CvsUrl . "'";
            $query .= ",documentationurl='" . $DocumentationUrl . "'";
            $query .= ",bugtrackerurl='" . $BugTrackerUrl . "'";
            $query .= ",bugtrackerfileurl='" . $BugTrackerFileUrl . "'";
            $query .= ",bugtrackernewissueurl='" . $BugTrackerNewIssueUrl . "'";
            $query .= ",bugtrackertype='" . $BugTrackerType . "'";
            $query .= ',public=' . qnum($this->Public);
            $query .= ',coveragethreshold=' . qnum($this->CoverageThreshold);
            $query .= ",testingdataurl='" . $TestingDataUrl . "'";
            $query .= ",nightlytime='" . $NightlyTime . "'";
            $query .= ",googletracker='" . $GoogleTracker . "'";
            $query .= ',emaillowcoverage=' . qnum($this->EmailLowCoverage);
            $query .= ',emailtesttimingchanged=' . qnum($this->EmailTestTimingChanged);
            $query .= ',emailbrokensubmission=' . qnum($this->EmailBrokenSubmission);
            $query .= ',emailredundantfailures=' . qnum($this->EmailRedundantFailures);
            $query .= ',emailadministrator=' . qnum($this->EmailAdministrator);
            $query .= ',showipaddresses=' . qnum($this->ShowIPAddresses);
            $query .= ',displaylabels=' . qnum($this->DisplayLabels);
            $query .= ',sharelabelfilters=' . qnum($this->ShareLabelFilters);
            $query .= ',authenticatesubmissions=' . qnum($this->AuthenticateSubmissions);
            $query .= ',showcoveragecode=' . qnum($this->ShowCoverageCode);
            $query .= ',autoremovetimeframe=' . qnum($this->AutoremoveTimeframe);
            $query .= ',autoremovemaxbuilds=' . qnum($this->AutoremoveMaxBuilds);
            $query .= ',uploadquota=' . qnum($this->UploadQuota);
            $query .= ",cvsviewertype='" . $CvsViewerType . "'";
            $query .= ',testtimestd=' . qnum($this->TestTimeStd);
            $query .= ',testtimestdthreshold=' . qnum($this->TestTimeStdThreshold);
            $query .= ',showtesttime=' . qnum($this->ShowTestTime);
            $query .= ',testtimemaxstatus=' . qnum($this->TestTimeMaxStatus);
            $query .= ',emailmaxitems=' . qnum($this->EmailMaxItems);
            $query .= ',emailmaxchars=' . qnum($this->EmailMaxChars);
            $query .= ' WHERE id=' . qnum($this->Id) . '';

            if (!pdo_query($query)) {
                add_last_sql_error('Project Update', $this->Id);
                return false;
            }

            if ($this->RobotName != '') {
                // Check if it exists
                $robot = pdo_query('SELECT projectid FROM projectrobot WHERE projectid=' . qnum($this->Id));
                if (pdo_num_rows($robot) > 0) {
                    $query = "UPDATE projectrobot SET robotname='" . $RobotName . "',authorregex='" . $RobotRegex .
                        "' WHERE projectid=" . qnum($this->Id);
                    if (!pdo_query($query)) {
                        add_last_sql_error('Project Update', $this->Id);
                        return false;
                    }
                } else {
                    $query = 'INSERT INTO projectrobot(projectid,robotname,authorregex)
                   VALUES (' . qnum($this->Id) . ",'" . $RobotName . "','" . $RobotRegex . "')";
                    if (!pdo_query($query)) {
                        add_last_sql_error('Project Update', $this->Id);
                        return false;
                    }
                }
            }

            if (!$this->UpdateBuildFilters()) {
                return false;
            }

            // Insert the ctest template
            if ($this->CTestTemplateScript != '') {
                $CTestTemplateScript = pdo_real_escape_string($this->CTestTemplateScript);

                // Check if it exists
                $script = pdo_query('SELECT projectid FROM projectjobscript WHERE projectid=' . qnum($this->Id));
                if (pdo_num_rows($script) > 0) {
                    $query = "UPDATE projectjobscript SET script='" . $CTestTemplateScript . "' WHERE projectid=" . qnum($this->Id);
                    if (!pdo_query($query)) {
                        return false;
                    }
                } else {
                    $query = 'INSERT INTO projectjobscript(projectid,script)
                   VALUES (' . qnum($this->Id) . ",'" . $CTestTemplateScript . "')";
                    if (!pdo_query($query)) {
                        return false;
                    }
                }
            } else {
                pdo_query("DELETE FROM projectjobscript WHERE projectid=$this->Id");
            }
        } else {
            // insert the project

            $id = '';
            $idvalue = '';
            if ($this->Id) {
                $id = 'id,';
                $idvalue = "'" . $this->Id . "',";
            }

            if (strlen($this->ImageId) == 0) {
                $this->ImageId = 0;
            }

            // Trim the name
            $this->Name = trim($this->Name);
            $this->Initialize();
            $query = 'INSERT INTO project(' . $id . 'name,description,homeurl,cvsurl,bugtrackerurl,bugtrackerfileurl,bugtrackernewissueurl,bugtrackertype,documentationurl,public,imageid,coveragethreshold,testingdataurl,
                                    nightlytime,googletracker,emailbrokensubmission,emailredundantfailures,
                                    emaillowcoverage,emailtesttimingchanged,cvsviewertype,
                                    testtimestd,testtimestdthreshold,testtimemaxstatus,emailmaxitems,emailmaxchars,showtesttime,emailadministrator,showipaddresses
                                    ,displaylabels,sharelabelfilters,authenticatesubmissions,showcoveragecode,autoremovetimeframe,autoremovemaxbuilds,uploadquota,webapikey)
                 VALUES (' . $idvalue . "'$Name','$Description','$HomeUrl','$CvsUrl','$BugTrackerUrl','$BugTrackerFileUrl','$BugTrackerNewIssueUrl','$BugTrackerType','$DocumentationUrl',
                 " . qnum($this->Public) . ',' . qnum($this->ImageId) . ',' . qnum($this->CoverageThreshold) . ",'$TestingDataUrl','$NightlyTime',
                 '$GoogleTracker'," . qnum($this->EmailBrokenSubmission) . ',' . qnum($this->EmailRedundantFailures) . ','
                . qnum($this->EmailLowCoverage) . ',' . qnum($this->EmailTestTimingChanged) . ",'$CvsViewerType'," . qnum($this->TestTimeStd)
                . ',' . qnum($this->TestTimeStdThreshold) . ',' . qnum($this->TestTimeMaxStatus) . ',' . qnum($this->EmailMaxItems) . ',' . qnum($this->EmailMaxChars) . ','
                . qnum($this->ShowTestTime) . ',' . qnum($this->EmailAdministrator) . ',' . qnum($this->ShowIPAddresses) . ',' . qnum($this->DisplayLabels) . ',' . qnum($this->ShareLabelFilters) . ',' . qnum($this->AuthenticateSubmissions) . ',' . qnum($this->ShowCoverageCode)
                . ',' . qnum($this->AutoremoveTimeframe) . ',' . qnum($this->AutoremoveMaxBuilds) . ',' . qnum($this->UploadQuota) . ",'" . $this->WebApiKey . "')";

            if (!pdo_query($query)) {
                add_last_sql_error('Project Create');
                return false;
            }

            if (!$this->Id) {
                $this->Id = pdo_insert_id('project');
            }

            if ($this->RobotName != '') {
                $query = 'INSERT INTO projectrobot(projectid,robotname,authorregex)
                 VALUES (' . qnum($this->Id) . ",'" . $RobotName . "','" . $RobotRegex . "')";
                if (!pdo_query($query)) {
                    return false;
                }
            }

            if ($this->CTestTemplateScript != '') {
                $CTestTemplateScript = pdo_real_escape_string($this->CTestTemplateScript);

                $query = 'INSERT INTO projectjobscript(projectid,script)
                 VALUES (' . qnum($this->Id) . ",'" . $$CTestTemplateScript . "')";
                if (!pdo_query($query)) {
                    return false;
                }
            }

            if (!$this->UpdateBuildFilters()) {
                return false;
            }
        }
        return true;
    }

    /** Get the user's role */
    public function GetUserRole($userid)
    {
        if (!$this->Id || !is_numeric($this->Id)) {
            return -1;
        }

        $role = -1;

        $user2project = pdo_query("SELECT role FROM user2project WHERE userid='$userid' AND projectid='" . $this->Id . "'");
        if (pdo_num_rows($user2project) > 0) {
            $user2project_array = pdo_fetch_array($user2project);
            $role = $user2project_array['role'];
        }
        return $role;
    }

    public function GetIdByName()
    {
        $pdo = Database::getInstance()->getPdo();
        $stmt = $pdo->prepare('SELECT id FROM project WHERE name = :name');
        $stmt->bindParam(':name', $this->Name);
        pdo_execute($stmt);
        $stmt->bindColumn('id', $this->Id);
        $stmt->fetch(\PDO::FETCH_BOUND);
        return $this->Id;
    }

    public function FindByName($name)
    {
        $this->Name = $name;
        $this->GetIdByName();
        if ($this->Id) {
            $this->Fill();
            return true;
        }
        return false;
    }

    /** Return true if the project exists */
    public function ExistsByName($name)
    {
        $this->Name = $name;
        if ($this->GetIdByName()) {
            return true;
        }
        return false;
    }

    /** Get the logo id */
    public function GetLogoId()
    {
        $query = pdo_query('SELECT imageid FROM project WHERE id=' . $this->Id);

        if (!$query) {
            add_last_sql_error('Project GetLogoId', $this->Id);
            return 0;
        }

        if ($query_array = pdo_fetch_array($query)) {
            return $query_array['imageid'];
        }
        return 0;
    }

    /** Fill in all the information from the database */
    public function Fill()
    {
        if (!$this->Id) {
            echo 'Project Fill(): Id not set';
        }

        $project = pdo_query('SELECT * FROM project WHERE id=' . $this->Id);
        if (!$project) {
            add_last_sql_error('Project Fill', $this->Id);
            return;
        }

        if ($project_array = pdo_fetch_array($project)) {
            $this->Name = $project_array['name'];
            $this->Description = $project_array['description'];
            $this->HomeUrl = $project_array['homeurl'];
            $this->CvsUrl = $project_array['cvsurl'];
            $this->DocumentationUrl = $project_array['documentationurl'];
            $this->BugTrackerUrl = $project_array['bugtrackerurl'];
            $this->BugTrackerFileUrl = $project_array['bugtrackerfileurl'];
            $this->BugTrackerNewIssueUrl = $project_array['bugtrackernewissueurl'];
            $this->BugTrackerType = $project_array['bugtrackertype'];
            $this->ImageId = $project_array['imageid'];
            $this->Public = $project_array['public'];
            $this->CoverageThreshold = $project_array['coveragethreshold'];
            $this->TestingDataUrl = $project_array['testingdataurl'];
            $this->NightlyTime = $project_array['nightlytime'];
            $this->GoogleTracker = $project_array['googletracker'];
            $this->EmailLowCoverage = $project_array['emaillowcoverage'];
            $this->EmailTestTimingChanged = $project_array['emailtesttimingchanged'];
            $this->EmailBrokenSubmission = $project_array['emailbrokensubmission'];
            $this->EmailRedundantFailures = $project_array['emailredundantfailures'];
            $this->EmailAdministrator = $project_array['emailadministrator'];
            $this->ShowIPAddresses = $project_array['showipaddresses'];
            $this->DisplayLabels = $project_array['displaylabels'];
            $this->ShareLabelFilters = $project_array['sharelabelfilters'];
            $this->AuthenticateSubmissions = $project_array['authenticatesubmissions'];
            $this->ShowCoverageCode = $project_array['showcoveragecode'];
            $this->AutoremoveTimeframe = $project_array['autoremovetimeframe'];
            $this->AutoremoveMaxBuilds = $project_array['autoremovemaxbuilds'];
            $this->UploadQuota = $project_array['uploadquota'];
            $this->CvsViewerType = $project_array['cvsviewertype'];
            $this->TestTimeStd = $project_array['testtimestd'];
            $this->TestTimeStdThreshold = $project_array['testtimestdthreshold'];
            $this->ShowTestTime = $project_array['showtesttime'];
            $this->TestTimeMaxStatus = $project_array['testtimemaxstatus'];
            $this->EmailMaxItems = $project_array['emailmaxitems'];
            $this->EmailMaxChars = $project_array['emailmaxchars'];
            $this->WebApiKey = $project_array['webapikey'];
            if ($this->WebApiKey == '') {
                // If no web API key exists, we add one
                include_once 'include/common.php';
                $newKey = generate_password(40);
                pdo_query("UPDATE project SET webapikey='$newKey' WHERE id=" . $this->Id);
                $this->WebApiKey = $newKey;
            }
        }

        // Check if we have a robot
        $robot = pdo_query('SELECT * FROM projectrobot WHERE projectid=' . $this->Id);
        if (!$robot) {
            add_last_sql_error('Project Fill', $this->Id);
            return;
        }

        if ($robot_array = pdo_fetch_array($robot)) {
            $this->RobotName = $robot_array['robotname'];
            $this->RobotRegex = $robot_array['authorregex'];
        }

        // Check if we have filters
        $build_filters = pdo_query('SELECT * FROM build_filters WHERE projectid=' . $this->Id);
        if (!$build_filters) {
            add_last_sql_error('Project Fill', $this->Id);
            return;
        }

        if ($build_filters_array = pdo_fetch_array($build_filters)) {
            $this->WarningsFilter = $build_filters_array['warnings'];
            $this->ErrorsFilter = $build_filters_array['errors'];
        }

        // Check if we have a ctest script
        $script = pdo_query('SELECT script FROM projectjobscript WHERE projectid=' . $this->Id);
        if (!$script) {
            add_last_sql_error('Project Fill', $this->Id);
            return;
        }
        if ($script_array = pdo_fetch_array($script)) {
            $this->CTestTemplateScript = $script_array['script'];
        }
    }

    /** Add a logo */
    public function AddLogo($contents, $filetype)
    {
        if (strlen($contents) == 0) {
            return;
        }

        $image = new Image();
        $image->Data = $contents;
        $image->Checksum = crc32($contents);
        $image->Extension = $filetype;

        $imgid = $this->GetLogoId();
        if ($imgid > 0) {
            $image->Id = $imgid;
        }

        if ($image->Save(true)) {
            pdo_query('UPDATE project SET imageid=' . qnum($image->Id) . ' WHERE id=' . $this->Id);
            add_last_sql_error('Project AddLogo', $this->Id);
        }
        return $image->Id;
    }

    /** Add CVS/SVN repositories */
    public function AddRepositories($repositories, $usernames, $passwords, $branches)
    {
        // First we update/delete any registered repositories
        $currentRepository = 0;
        $repositories_query = pdo_query('SELECT repositoryid FROM project2repositories WHERE projectid=' . qnum($this->Id) . ' ORDER BY repositoryid');
        add_last_sql_error('Project AddRepositories', $this->Id);
        while ($repository_array = pdo_fetch_array($repositories_query)) {
            $repositoryid = $repository_array['repositoryid'];
            if (!isset($repositories[$currentRepository]) || strlen($repositories[$currentRepository]) == 0) {
                $query = pdo_query('SELECT * FROM project2repositories WHERE repositoryid=' . qnum($repositoryid));
                add_last_sql_error('Project AddRepositories', $this->Id);
                if (pdo_num_rows($query) == 1) {
                    pdo_query("DELETE FROM repositories WHERE id='$repositoryid'");
                    add_last_sql_error('Project AddRepositories', $this->Id);
                }
                pdo_query('DELETE FROM project2repositories WHERE projectid=' . qnum($this->Id) . ' AND repositoryid=' . qnum($repositoryid));
                add_last_sql_error('Project AddRepositories', $this->Id);
            } else {
                // If the repository is not shared by any other project we update
                $count_query = pdo_query('SELECT count(*) as c FROM project2repositories WHERE repositoryid=' . qnum($repositoryid));
                $count_array = pdo_fetch_array($count_query);
                if ($count_array['c'] == 1) {
                    pdo_query("UPDATE repositories SET url='$repositories[$currentRepository]',
                          username='$usernames[$currentRepository]',
                          password='$passwords[$currentRepository]',
                          branch='$branches[$currentRepository]'
                          WHERE id=" . qnum($repositoryid));
                    add_last_sql_error('Project AddRepositories', $this->Id);
                } else {
                    // Otherwise we remove it from the current project and add it to the queue to be created

                    pdo_query('DELETE FROM project2repositories WHERE projectid=' . qnum($this->Id) . ' AND repositoryid=' . qnum($repositoryid));
                    add_last_sql_error('Project AddRepositories', $this->Id);
                    $repositories[] = $repositories[$currentRepository];
                    $usernames[] = $usernames[$currentRepository];
                    $passwords[] = $passwords[$currentRepository];
                    $branches[] = $branches[$currentRepository];
                }
            }
            $currentRepository++;
        }

        //  Then we add new repositories
        for ($i = $currentRepository; $i < count($repositories); $i++) {
            $url = $repositories[$i];
            $username = $usernames[$i];
            $password = $passwords[$i];
            $branch = $branches[$i];
            if (strlen($url) == 0) {
                continue;
            }

            // Insert into repositories if not any
            $repositories_query = pdo_query("SELECT id FROM repositories WHERE url='$url'");
            if (pdo_num_rows($repositories_query) == 0) {
                pdo_query("INSERT INTO repositories (url, username, password, branch) VALUES ('$url', '$username', '$password','$branch')");
                add_last_sql_error('Project AddRepositories', $this->Id);
                $repositoryid = pdo_insert_id('repositories');
            } else {
                $repositories_array = pdo_fetch_array($repositories_query);
                $repositoryid = $repositories_array['id'];
            }
            pdo_query('INSERT INTO project2repositories (projectid,repositoryid) VALUES (' . qnum($this->Id) . ",'$repositoryid')");
            add_last_sql_error('Project AddRepositories', $this->Id);
        }
    }

    /** Get the repositories */
    public function GetRepositories()
    {
        $repositories = array();
        $repository = pdo_query('SELECT url,username,password,branch from repositories,project2repositories
                               WHERE repositories.id=project2repositories.repositoryid
                               AND project2repositories.projectid=' . qnum($this->Id));
        add_last_sql_error('Project GetRepositories', $this->Id);
        while ($repository_array = pdo_fetch_array($repository)) {
            $rep['url'] = $repository_array['url'];
            $rep['username'] = $repository_array['username'];
            $rep['password'] = $repository_array['password'];
            $rep['branch'] = $repository_array['branch'];
            $repositories[] = $rep;
        }
        return $repositories;
    }

    /** Get the build groups */
    public function GetBuildGroups()
    {
        $buildgroups = array();
        $query = pdo_query('
       SELECT id FROM buildgroup
       WHERE projectid=' . qnum($this->Id) . " AND
             endtime='1980-01-01 00:00:00'");

        add_last_sql_error('Project GetBuildGroups', $this->Id);
        while ($row = pdo_fetch_array($query)) {
            $buildgroup = new BuildGroup();
            $buildgroup->SetId($row['id']);
            $buildgroups[] = $buildgroup;
        }
        return $buildgroups;
    }

    /** Get the list of block builds */
    public function GetBlockedBuilds()
    {
        $sites = array();
        $site = pdo_query('SELECT id,buildname,sitename,ipaddress FROM blockbuild
                             WHERE projectid=' . qnum($this->Id));
        add_last_sql_error('Project GetBlockedBuilds', $this->Id);
        while ($site_array = pdo_fetch_array($site)) {
            $sites[] = $site_array;
        }
        return $sites;
    }

    /** Get Ids of all the project registered
     *  Maybe this function should go somewhere else but for now here */
    public function GetIds()
    {
        $ids = array();
        $query = pdo_query('SELECT id FROM project ORDER BY id');
        add_last_sql_error('Project GetIds', $this->Id);
        while ($query_array = pdo_fetch_array($query)) {
            $ids[] = $query_array['id'];
        }
        return $ids;
    }

    /** Get the Name of the project */
    public function GetName()
    {
        if (strlen($this->Name) > 0) {
            return $this->Name;
        }

        if (!$this->Id) {
            echo 'Project GetName(): Id not set';
            return false;
        }

        $project = pdo_query('SELECT name FROM project WHERE id=' . qnum($this->Id));
        if (!$project) {
            add_last_sql_error('Project GetName', $this->Id);
            return false;
        }
        $project_array = pdo_fetch_array($project);
        $this->Name = $project_array['name'];
        return $this->Name;
    }

    /** Get the coveragethreshold */
    public function GetCoverageThreshold()
    {
        if (strlen($this->CoverageThreshold) > 0) {
            return $this->CoverageThreshold;
        }

        if (!$this->Id) {
            echo 'Project GetCoverageThreshold(): Id not set';
            return false;
        }

        $project = pdo_query('SELECT coveragethreshold FROM project WHERE id=' . qnum($this->Id));
        if (!$project) {
            add_last_sql_error('Project GetCoverageThreshold', $this->Id);
            return false;
        }
        $project_array = pdo_fetch_array($project);
        $this->CoverageThreshold = $project_array['coveragethreshold'];
        return $this->CoverageThreshold;
    }

    /** Get the number of subproject */
    public function GetNumberOfSubProjects($date = null)
    {
        if (!$this->Id) {
            echo 'Project GetNumberOfSubProjects(): Id not set';
            return false;
        }

        if ($date == null) {
            $date = gmdate(FMT_DATETIME);
        }

        $project = pdo_query('SELECT count(*) AS c FROM subproject WHERE projectid=' . qnum($this->Id) . " AND (endtime='1980-01-01 00:00:00' OR endtime>'" . $date . "')");
        if (!$project) {
            add_last_sql_error('Project GetNumberOfSubProjects', $this->Id);
            return false;
        }
        $project_array = pdo_fetch_array($project);
        return intval($project_array['c']);
    }

    /** Get the subproject ids*/
    public function GetSubProjects($date = null)
    {
        if (!$this->Id) {
            echo 'Project GetNumberOfSubProjects(): Id not set';
            return false;
        }

        // If not set, the date is now
        if ($date == null) {
            $date = gmdate(FMT_DATETIME);
        }

        $project = pdo_query('SELECT id FROM subproject WHERE projectid=' . qnum($this->Id) . " AND
                          starttime<='" . $date . "' AND (endtime>'" . $date . "' OR endtime='1980-01-01 00:00:00')");
        if (!$project) {
            add_last_sql_error('Project GetSubProjects', $this->Id);
            return false;
        }

        $ids = array();
        while ($project_array = pdo_fetch_array($project)) {
            $ids[] = $project_array['id'];
        }
        return $ids;
    }

    /** Get the last submission of the subproject*/
    public function GetLastSubmission()
    {
        $config = Config::getInstance();
        if (!$config->get('CDASH_SHOW_LAST_SUBMISSION')) {
            return false;
        }

        if (!$this->Id) {
            echo 'Project GetLastSubmission(): Id not set';
            return false;
        }

        $build = pdo_query('SELECT submittime FROM build WHERE projectid=' . qnum($this->Id) .
            ' ORDER BY submittime DESC LIMIT 1');

        if (!$build) {
            add_last_sql_error('Project GetLastSubmission', $this->Id);
            return false;
        }
        $build_array = pdo_fetch_array($build);

        if (!is_array($build_array) ||
                !array_key_exists('submittime', $build_array)) {
            return false;
        }

        return date(FMT_DATETIMESTD, strtotime($build_array['submittime'] . 'UTC'));
    }

    /** Get the total number of builds for a project*/
    public function GetTotalNumberOfBuilds()
    {
        if (!$this->Id) {
            echo 'Project GetTotalNumberOfBuilds(): Id not set';
            return false;
        }

        $project = pdo_query(
            'SELECT count(*) FROM build
            WHERE parentid IN (-1, 0) AND projectid=' . qnum($this->Id));

        if (!$project) {
            add_last_sql_error('Project GetTotalNumberOfBuilds', $this->Id);
            return false;
        }
        $project_array = pdo_fetch_array($project);
        return intval($project_array[0]);
    }

    /** Get the number of builds given a date range */
    public function GetNumberOfBuilds($startUTCdate = null, $endUTCdate = null)
    {
        if (!$this->Id) {
            add_log('Id not set', 'Project::GetNumberOfBuilds', LOG_ERR,
                    $this->Id);
            return false;
        }

        // Construct our query given the optional parameters of this function.
        $sql = 'SELECT COUNT(build.id) FROM build
                WHERE projectid = :projectid AND parentid IN (-1, 0)';
        if (!is_null($startUTCdate)) {
            $sql .= ' AND build.starttime > :start';
        }
        if (!is_null($endUTCdate)) {
            $sql .= ' AND build.starttime <= :end';
        }

        $stmt = $this->PDO->prepare($sql);
        $stmt->bindParam(':projectid', $this->Id);
        if (!is_null($startUTCdate)) {
            $stmt->bindParam(':start', $startUTCdate);
        }
        if (!is_null($endUTCdate)) {
            $stmt->bindParam(':end', $endUTCdate);
        }

        if (!pdo_execute($stmt)) {
            return false;
        }

        return intval($stmt->fetchColumn());
    }

    /** Get the number of builds given per day */
    public function GetBuildsDailyAverage($startUTCdate, $endUTCdate)
    {
        if (!$this->Id) {
            echo 'Project GetNumberOfBuilds(): Id not set';
            return false;
        }
        $nbuilds = $this->GetNumberOfBuilds($startUTCdate, $endUTCdate);
        $project = pdo_query(
            'SELECT starttime FROM build
            WHERE projectid=' . qnum($this->Id) . "
            AND starttime>'$startUTCdate'
            AND starttime<='$endUTCdate'
            AND parentid IN (-1, 0)
            ORDER BY starttime ASC LIMIT 1");
        $first_build = pdo_fetch_array($project);
        $first_build = $first_build['starttime'];
        $nb_days = strtotime($endUTCdate) - strtotime($first_build);
        $nb_days = intval($nb_days / 86400) + 1;
        if (!$project) {
            return 0;
        }
        return $nbuilds / $nb_days;
    }

    /** Get the number of warning builds given a date range */
    public function GetNumberOfWarningBuilds($startUTCdate, $endUTCdate,
                                             $childrenOnly = false)
    {
        if (!$this->Id) {
            echo 'Project GetNumberOfWarningBuilds(): Id not set';
            return false;
        }

        $query = 'SELECT count(*) FROM build,build2group,buildgroup
              WHERE build.projectid=' . qnum($this->Id) . "
              AND build.starttime>'$startUTCdate'
              AND build.starttime<='$endUTCdate'
              AND build2group.buildid=build.id AND build2group.groupid=buildgroup.id
              AND buildgroup.includesubprojectotal=1
              AND build.buildwarnings>0";
        if ($childrenOnly) {
            $query .= ' AND build.parentid > 0';
        } else {
            $query .= ' AND build.parentid IN (-1, 0)';
        }

        $project = pdo_query($query);
        if (!$project) {
            add_last_sql_error('Project GetNumberOfWarningBuilds', $this->Id);
            return false;
        }
        $project_array = pdo_fetch_array($project);
        $count = intval($project_array[0]);
        return $count;
    }

    /** Get the number of error builds given a date range */
    public function GetNumberOfErrorBuilds($startUTCdate, $endUTCdate,
                                           $childrenOnly = false)
    {
        if (!$this->Id) {
            echo 'Project GetNumberOfErrorBuilds(): Id not set';
            return false;
        }

        // build failures
        $query =
            'SELECT count(*) FROM build,build2group,buildgroup
       WHERE build.projectid=' . qnum($this->Id) . "
       AND build.starttime>'$startUTCdate'
       AND build.starttime<='$endUTCdate'
       AND build2group.buildid=build.id AND build2group.groupid=buildgroup.id
       AND buildgroup.includesubprojectotal=1
       AND build.builderrors>0";
        if ($childrenOnly) {
            $query .= ' AND build.parentid > 0';
        } else {
            $query .= ' AND build.parentid IN (-1, 0)';
        }

        $project = pdo_query($query);
        if (!$project) {
            add_last_sql_error('Project GetNumberOfErrorBuilds', $this->Id);
            return false;
        }
        $project_array = pdo_fetch_array($project);
        $count = intval($project_array[0]);
        return $count;
    }

    /** Get the number of failing builds given a date range */
    public function GetNumberOfPassingBuilds($startUTCdate, $endUTCdate,
                                             $childrenOnly = false)
    {
        if (!$this->Id) {
            echo 'Project GetNumberOfPassingBuilds(): Id not set';
            return false;
        }

        $query =
            'SELECT count(*) FROM build b
            JOIN build2group b2g ON (b2g.buildid=b.id)
            JOIN buildgroup bg ON (bg.id=b2g.groupid)
            WHERE b.projectid=' . qnum($this->Id) . "
            AND b.starttime>'$startUTCdate'
            AND b.starttime<='$endUTCdate'
            AND bg.includesubprojectotal=1
            AND b.builderrors=0
            AND b.buildwarnings=0";
        if ($childrenOnly) {
            $query .= ' AND b.parentid > 0';
        } else {
            $query .= ' AND b.parentid IN (-1, 0)';
        }

        $project = pdo_query($query);
        if (!$project) {
            add_last_sql_error('Project GetNumberOfPassingBuilds', $this->Id);
            return false;
        }
        $project_array = pdo_fetch_array($project);
        return intval($project_array[0]);
    }

    /** Get the number of failing configure given a date range */
    public function GetNumberOfWarningConfigures($startUTCdate, $endUTCdate,
                                                 $childrenOnly = false)
    {
        if (!$this->Id) {
            echo 'Project GetNumberOfWarningConfigures(): Id not set';
            return false;
        }

        $query =
            'SELECT COUNT(*) FROM build b
            JOIN build2group b2g ON (b2g.buildid = b.id)
            JOIN buildgroup bg ON (bg.id = b2g.groupid)
            WHERE b.projectid = ' . qnum($this->Id) . "
            AND b.starttime > '$startUTCdate'
            AND b.starttime <= '$endUTCdate'
            AND b.configurewarnings > 0
            AND bg.includesubprojectotal = 1";
        if ($childrenOnly) {
            $query .= ' AND b.parentid > 0';
        } else {
            $query .= ' AND b.parentid IN (-1, 0)';
        }

        $project = pdo_query($query);
        if (!$project) {
            add_last_sql_error('Project GetNumberOfWarningConfigures', $this->Id);
            return false;
        }
        $project_array = pdo_fetch_array($project);
        return intval($project_array[0]);
    }

    /** Get the number of failing configure given a date range */
    public function GetNumberOfErrorConfigures($startUTCdate, $endUTCdate,
                                               $childrenOnly = false)
    {
        if (!$this->Id) {
            echo 'Project GetNumberOfErrorConfigures(): Id not set';
            return false;
        }

        $query =
            'SELECT COUNT(*) FROM build b
            JOIN build2group b2g ON (b2g.buildid = b.id)
            JOIN buildgroup bg ON (bg.id = b2g.groupid)
            WHERE b.projectid = ' . qnum($this->Id) . "
            AND b.starttime > '$startUTCdate'
            AND b.starttime <= '$endUTCdate'
            AND b.configureerrors > 0
            AND bg.includesubprojectotal = 1";
        if ($childrenOnly) {
            $query .= ' AND b.parentid > 0';
        } else {
            $query .= ' AND b.parentid IN (-1, 0)';
        }

        $project = pdo_query($query);
        if (!$project) {
            add_last_sql_error('Project GetNumberOfErrorConfigures', $this->Id);
            return false;
        }
        $project_array = pdo_fetch_array($project);
        return intval($project_array[0]);
    }

    /** Get the number of failing configure given a date range */
    public function GetNumberOfPassingConfigures($startUTCdate, $endUTCdate,
                                                 $childrenOnly = false)
    {
        if (!$this->Id) {
            echo 'Project GetNumberOfPassingConfigures(): Id not set';
            return false;
        }

        $query =
            'SELECT COUNT(*) FROM build b
            JOIN build2group b2g ON (b2g.buildid = b.id)
            JOIN buildgroup bg ON (bg.id = b2g.groupid)
            WHERE b.projectid = ' . qnum($this->Id) . "
            AND b.starttime > '$startUTCdate'
            AND b.starttime <= '$endUTCdate'
            AND b.configureerrors = 0
            AND b.configurewarnings = 0
            AND bg.includesubprojectotal = 1";
        if ($childrenOnly) {
            $query .= ' AND b.parentid > 0';
        } else {
            $query .= ' AND b.parentid IN (-1, 0)';
        }

        $project = pdo_query($query);
        if (!$project) {
            add_last_sql_error('Project GetNumberOfPassingConfigures', $this->Id);
            return false;
        }
        $project_array = pdo_fetch_array($project);
        return intval($project_array[0]);
    }

    /** Get the number of tests given a date range */
    public function GetNumberOfPassingTests($startUTCdate, $endUTCdate,
                                            $childrenOnly = false)
    {
        if (!$this->Id) {
            echo 'Project GetNumberOfPassingTests(): Id not set';
            return false;
        }

        $query = 'SELECT SUM(build.testpassed) FROM build,build2group,buildgroup WHERE build.projectid=' . qnum($this->Id) . "
              AND build2group.buildid=build.id
              AND build.testpassed>=0
              AND build2group.groupid=buildgroup.id
              AND buildgroup.includesubprojectotal=1
              AND build.starttime>'$startUTCdate'
              AND build.starttime<='$endUTCdate'";
        if ($childrenOnly) {
            $query .= ' AND build.parentid > 0';
        } else {
            $query .= ' AND build.parentid IN (-1, 0)';
        }

        $project = pdo_query($query);
        if (!$project) {
            add_last_sql_error('Project GetNumberOfPassingTests', $this->Id);
            return false;
        }
        $project_array = pdo_fetch_array($project);
        return intval($project_array[0]);
    }

    /** Get the number of tests given a date range */
    public function GetNumberOfFailingTests($startUTCdate, $endUTCdate,
                                            $childrenOnly = false)
    {
        if (!$this->Id) {
            echo 'Project GetNumberOfFailingTests(): Id not set';
            return false;
        }

        $query = 'SELECT SUM(build.testfailed) FROM build,build2group,buildgroup WHERE build.projectid=' . qnum($this->Id) . "
              AND build2group.buildid=build.id
              AND build.testfailed>=0
              AND build2group.groupid=buildgroup.id
              AND buildgroup.includesubprojectotal=1
              AND build.starttime>'$startUTCdate'
              AND build.starttime<='$endUTCdate'";
        if ($childrenOnly) {
            $query .= ' AND build.parentid > 0';
        } else {
            $query .= ' AND build.parentid IN (-1, 0)';
        }

        $project = pdo_query($query);
        if (!$project) {
            add_last_sql_error('Project GetNumberOfFailingTests', $this->Id);
            return false;
        }
        $project_array = pdo_fetch_array($project);
        return intval($project_array[0]);
    }

    /** Get the number of tests given a date range */
    public function GetNumberOfNotRunTests($startUTCdate, $endUTCdate,
                                           $childrenOnly = false)
    {
        if (!$this->Id) {
            echo 'Project GetNumberOfNotRunTests(): Id not set';
            return false;
        }

        $query = 'SELECT SUM(build.testnotrun) FROM build,build2group,buildgroup WHERE build.projectid=' . qnum($this->Id) . "
              AND build2group.buildid=build.id
              AND build.testnotrun>=0
              AND build2group.groupid=buildgroup.id
              AND buildgroup.includesubprojectotal=1
              AND build.starttime>'$startUTCdate'
              AND build.starttime<='$endUTCdate'";
        if ($childrenOnly) {
            $query .= ' AND build.parentid > 0';
        } else {
            $query .= ' AND build.parentid IN (-1, 0)';
        }

        $project = pdo_query($query);
        if (!$project) {
            add_last_sql_error('Project GetNumberOfNotRunTests', $this->Id);
            return false;
        }
        $project_array = pdo_fetch_array($project);
        return intval($project_array[0]);
    }

    /** Get the labels ids for a given project */
    public function GetLabels($days)
    {
        $todaytime = time();
        $todaytime -= 3600 * 24 * $days;
        $today = date(FMT_DATETIMESTD, $todaytime);

        $straighthjoin = '';
        $config = Config::getInstance();
        if ($config->get('CDASH_DB_TYPE') != 'pgsql') {
            $straighthjoin = 'STRAIGHT_JOIN';
        }

        $labelids = array();
        $labels = pdo_query('(SELECT labelid AS id FROM label2build,build WHERE label2build.buildid=build.id AND build.projectid=' . qnum($this->Id) . " AND build.starttime>'$today')
                          UNION
                          (SELECT labelid AS id FROM label2test,build WHERE label2test.buildid=build.id
                                  AND build.projectid=" . qnum($this->Id) . " AND build.starttime>'$today')
                          UNION
                          (SELECT " . $straighthjoin . ' labelid AS id FROM build,label2coveragefile WHERE label2coveragefile.buildid=build.id
                                 AND build.projectid=' . qnum($this->Id) . " AND build.starttime>'$today')
                          UNION
                          (SELECT " . $straighthjoin . ' labelid AS id FROM build,buildfailure,label2buildfailure WHERE label2buildfailure.buildfailureid=buildfailure.id
                                 AND buildfailure.buildid=build.id AND build.projectid=' . qnum($this->Id) . " AND build.starttime>'$today')
                          UNION
                          (SELECT " . $straighthjoin . ' labelid AS id FROM build,dynamicanalysis,label2dynamicanalysis WHERE label2dynamicanalysis.dynamicanalysisid=dynamicanalysis.id
                                 AND dynamicanalysis.buildid=build.id AND build.projectid=' . qnum($this->Id) . " AND build.starttime>'$today')
                          ");

        if (!$labels) {
            add_last_sql_error('Project GetLabels', $this->Id);
            return false;
        }

        while ($label_array = pdo_fetch_array($labels)) {
            $labelids[] = $label_array['labelid'];
        }
        return array_unique($labelids);
    }

    /** Send an email to the administrator of the project */
    public function SendEmailToAdmin($subject, $body)
    {
        if (!$this->Id) {
            echo 'Project SendEmailToAdmin(): Id not set';
            return false;
        }
        $config = Config::getInstance();
        // Check if we should send emails
        $project = pdo_query('SELECT emailadministrator,name FROM project WHERE id =' . qnum($this->Id));
        if (!$project) {
            add_last_sql_error('Project SendEmailToAdmin', $this->Id);
            return false;
        }
        $project_array = pdo_fetch_array($project);

        if ($project_array['emailadministrator'] == 0) {
            return;
        }

        // Find the site maintainers
        $UserProject = new UserProject();
        $UserProject->ProjectId = $this->Id;

        $userids = $UserProject->GetUsers(2); // administrators
        $email = '';
        foreach ($userids as $userid) {
            $User = new User;
            $User->Id = $userid;
            if ($email != '') {
                $email .= ', ';
            }
            $email .= $User->GetEmail();
        }

        if ($email != '') {
            $projectname = $project_array['name'];
            $emailtitle = 'CDash [' . $projectname . '] - Administration ';
            $emailbody = 'Object: ' . $subject . "\n";
            $emailbody .= $body . "\n";
            $serverName = $config->getServer();

            $emailbody .= "\n-CDash on " . $serverName . "\n";

            if (cdashmail("$email", $emailtitle, $emailbody)) {
                add_log('email sent to: ' . $email, 'Project::SendEmailToAdmin');
                return;
            } else {
                add_log('cannot send email to: ' . $email, 'Project::SendEmailToAdmin', LOG_ERR, $this->Id);
            }
        }
    }

    public function getDefaultCTestUpdateType()
    {
        switch ($this->CvsViewerType) {
            case 'cgit':
            case 'github':
            case 'gitorious':
            case 'gitweb':
            case 'redmine':
                return 'git';
                break;

            case 'websvn':
            case 'allura':
                return 'svn';
                break;

            case 'hgweb':
                return 'mercurial';
                break;

            default:
                return 'cvs';
                break;
        }
    }

    public function getDefaultJobTemplateScript()
    {
        $ctest_script = '# From this line down, this script may be customized' . "\n";
        $ctest_script .= '# on the Clients tab of the CDash createProject page.' . "\n";
        $ctest_script .= '#' . "\n";
        $ctest_script .= 'if(JOB_MODULE)' . "\n";
        $ctest_script .= '  set(SOURCE_NAME ${JOB_MODULE})' . "\n";
        $ctest_script .= '  if(JOB_TAG)' . "\n";
        $ctest_script .= '    set(SOURCE_NAME ${SOURCE_NAME}-${JOB_TAG})' . "\n";
        $ctest_script .= '  endif()' . "\n";
        $ctest_script .= 'else()' . "\n";
        $ctest_script .= '  set(SOURCE_NAME ${PROJECT_NAME})' . "\n";
        $ctest_script .= '  if(JOB_BUILDNAME_SUFFIX)' . "\n";
        $ctest_script .= '    set(SOURCE_NAME ${SOURCE_NAME}-${JOB_BUILDNAME_SUFFIX})' . "\n";
        $ctest_script .= '  endif()' . "\n";
        $ctest_script .= 'endif()' . "\n";
        $ctest_script .= "\n";
        $ctest_script .= 'set(CTEST_SOURCE_NAME ${SOURCE_NAME})' . "\n";
        $ctest_script .= 'set(CTEST_BINARY_NAME ${SOURCE_NAME}-bin)' . "\n";
        $ctest_script .= 'set(CTEST_DASHBOARD_ROOT "${CLIENT_BASE_DIRECTORY}")' . "\n";
        $ctest_script .= 'set(CTEST_SOURCE_DIRECTORY "${CTEST_DASHBOARD_ROOT}/${CTEST_SOURCE_NAME}")' . "\n";
        $ctest_script .= 'set(CTEST_BINARY_DIRECTORY "${CTEST_DASHBOARD_ROOT}/${CTEST_BINARY_NAME}")' . "\n";
        $ctest_script .= 'set(CTEST_CMAKE_GENERATOR "${JOB_CMAKE_GENERATOR}")' . "\n";
        $ctest_script .= 'set(CTEST_BUILD_CONFIGURATION "${JOB_BUILD_CONFIGURATION}")' . "\n";
        $ctest_script .= "\n";

        // Construct the buildname
        $ctest_script .= 'set(CTEST_SITE "${CLIENT_SITE}")' . "\n";
        $ctest_script .= 'set(CTEST_BUILD_NAME "${JOB_OS_NAME}-${JOB_OS_VERSION}-${JOB_OS_BITS}-${JOB_COMPILER_NAME}-${JOB_COMPILER_VERSION}")' . "\n";
        $ctest_script .= 'if(JOB_BUILDNAME_SUFFIX)' . "\n";
        $ctest_script .= '  set(CTEST_BUILD_NAME ${CTEST_BUILD_NAME}-${JOB_BUILDNAME_SUFFIX})' . "\n";
        $ctest_script .= 'endif()' . "\n";
        $ctest_script .= "\n";

        // Set the checkout command
        $repo_type = $this->getDefaultCTestUpdateType();

        if ($repo_type == 'cvs') {
            $ctest_script .= 'if(NOT EXISTS "${CTEST_SOURCE_DIRECTORY}")' . "\n";
            $ctest_script .= '  set(CTEST_CHECKOUT_COMMAND "cvs -d ${JOB_REPOSITORY} checkout ")' . "\n";
            $ctest_script .= '  if(JOB_TAG)' . "\n";
            $ctest_script .= '    set(CTEST_CHECKOUT_COMMAND "${CTEST_CHECKOUT_COMMAND} -r ${JOB_TAG}")' . "\n";
            $ctest_script .= '  endif()' . "\n";
            $ctest_script .= '  set(CTEST_CHECKOUT_COMMAND "${CTEST_CHECKOUT_COMMAND} -d ${SOURCE_NAME}")' . "\n";
            $ctest_script .= '  set(CTEST_CHECKOUT_COMMAND "${CTEST_CHECKOUT_COMMAND} ${JOB_MODULE}")' . "\n";
            $ctest_script .= 'endif()' . "\n";
            $ctest_script .= 'set(CTEST_UPDATE_COMMAND "cvs")' . "\n";
        }

        if ($repo_type == 'git') {
            $ctest_script .= 'if(NOT EXISTS "${CTEST_SOURCE_DIRECTORY}")' . "\n";
            $ctest_script .= '  set(CTEST_CHECKOUT_COMMAND "git clone ${JOB_REPOSITORY} ${SOURCE_NAME}")' . "\n";
            $ctest_script .= 'endif()' . "\n";
            $ctest_script .= 'set(CTEST_UPDATE_COMMAND "git")' . "\n";
        }

        if ($repo_type == 'svn') {
            $ctest_script .= 'if(NOT EXISTS "${CTEST_SOURCE_DIRECTORY}")' . "\n";
            $ctest_script .= '  set(CTEST_CHECKOUT_COMMAND "svn co ${JOB_REPOSITORY} ${SOURCE_NAME}")' . "\n";
            $ctest_script .= 'endif()' . "\n";
            $ctest_script .= 'set(CTEST_UPDATE_COMMAND "svn")' . "\n";
        }

        $ctest_script .= "\n";

        // Write the initial CMakeCache.txt
        //
        $ctest_script .= 'file(WRITE "${CTEST_BINARY_DIRECTORY}/CMakeCache.txt" "${JOB_INITIAL_CACHE}")' . "\n";
        $ctest_script .= "\n";

        $ctest_script .= 'ctest_start(${JOB_BUILDTYPE})' . "\n";
        $ctest_script .= 'ctest_update(SOURCE ${CTEST_SOURCE_DIRECTORY})' . "\n";
        $ctest_script .= 'ctest_configure(BUILD "${CTEST_BINARY_DIRECTORY}" RETURN_VALUE res)' . "\n";
        $ctest_script .= 'ctest_build(BUILD "${CTEST_BINARY_DIRECTORY}" RETURN_VALUE res)' . "\n";
        $ctest_script .= 'ctest_test(BUILD "${CTEST_BINARY_DIRECTORY}" RETURN_VALUE res)' . "\n";
        $ctest_script .= '# The following lines are used to associate a build id with this job.' . "\n";
        $ctest_script .= 'set(CTEST_DROP_SITE ${JOB_DROP_SITE})' . "\n";
        $ctest_script .= 'set(CTEST_DROP_LOCATION ${JOB_DROP_LOCATION})' . "\n";
        $ctest_script .= 'ctest_submit(RETURN_VALUE res)' . "\n";
        $ctest_script .= "\n";
        $ctest_script .= 'message("DONE")' . "\n";
        return $ctest_script;
    }

    /** Returns the total size of all uploaded files for this project */
    public function GetUploadsTotalSize()
    {
        if (!$this->Id) {
            add_log('Id not set', 'Project::GetUploadsTotalSize', LOG_ERR);
            return false;
        }
        $totalSizeQuery = pdo_query('SELECT DISTINCT uploadfile.id, uploadfile.filesize AS size
                                 FROM build, build2uploadfile, uploadfile
                                 WHERE build.projectid=' . qnum($this->Id) . ' AND
                                 build.id=build2uploadfile.buildid AND
                                 build2uploadfile.fileid=uploadfile.id');
        if (!$totalSizeQuery) {
            add_last_sql_error('Project::GetUploadsTotalSize', $this->Id);
            return false;
        }

        $totalSize = 0;
        while ($result = pdo_fetch_array($totalSizeQuery)) {
            $totalSize += $result['size'];
        }
        return $totalSize;
    }

    /**
     * Return a list of files or urls, each of which has the following key/value pairs:
     *  id       - id of the file in the uploadfile table
     *  filename - name of the file
     *  filesize - size in bytes of the file
     *  sha1sum  - sha-1 checksum of the file
     *  isurl    - True if filename is a URL
     * The files will be returned in order, with the newest first
     */
    public function GetUploadedFilesOrUrls()
    {
        if (!$this->Id) {
            add_log('Id not set', 'Project::GetUploadedFilesOrUrls', LOG_ERR);
            return false;
        }
        $query = pdo_query('SELECT uploadfile.id, uploadfile.filename, uploadfile.filesize, uploadfile.sha1sum, uploadfile.isurl
                        FROM uploadfile, build2uploadfile, build
                        WHERE build.projectid=' . qnum($this->Id) . ' AND
                        build.id=build2uploadfile.buildid AND
                        build2uploadfile.fileid=uploadfile.id ORDER BY build.starttime DESC');
        if (!$query) {
            add_last_sql_error('Project::GetUploadedFilesOrUrls', $this->Id);
            return false;
        }

        $files = array();
        while ($result = pdo_fetch_array($query)) {
            $files[] = array('id' => $result['id'],
                'filename' => $result['filename'],
                'filesize' => $result['filesize'],
                'sha1sum' => $result['sha1sum'],
                'isurl' => $result['isurl']);
        }
        return $files;
    }

    /**
     * Checks whether this project has exceeded its upload size quota.  If so,
     * Removes the files (starting with the oldest builds) until the total upload size
     * is <= the upload quota.
     */
    public function CullUploadedFiles()
    {
        if (!$this->Id) {
            add_log('Id not set', 'Project::CullUploadedFiles', LOG_ERR);
            return false;
        }
        $totalUploadSize = $this->GetUploadsTotalSize();

        if ($totalUploadSize > $this->UploadQuota) {
            require_once 'include/common.php';
            add_log('Upload quota exceeded, removing old files', 'Project::CullUploadedFiles',
                LOG_INFO, $this->Id);

            $query = pdo_query('SELECT DISTINCT build.id AS id, build.starttime
                               FROM build, build2uploadfile, uploadfile
                               WHERE build.projectid=' . qnum($this->Id) . ' AND
                               build.id=build2uploadfile.buildid AND
                               build2uploadfile.fileid=uploadfile.id
                               ORDER BY build.starttime ASC');

            while ($builds_array = pdo_fetch_array($query)) {
                // Delete the uploaded files
                $fileids = '(';
                $build2uploadfiles = pdo_query('SELECT fileid FROM build2uploadfile
                                 WHERE buildid = ' . qnum($builds_array['id']));
                while ($build2uploadfile_array = pdo_fetch_array($build2uploadfiles)) {
                    $fileid = $build2uploadfile_array['fileid'];
                    if ($fileids != '(') {
                        $fileids .= ',';
                    }
                    $fileids .= $fileid;
                    $totalUploadSize -= unlink_uploaded_file($fileid);
                    add_log("Removed file $fileid", 'Project::CullUploadedFiles', LOG_INFO, $this->Id);
                }

                $fileids .= ')';
                if (strlen($fileids) > 2) {
                    pdo_query('DELETE FROM uploadfile WHERE id IN ' . $fileids);
                    pdo_query('DELETE FROM build2uploadfile WHERE fileid IN ' . $fileids);
                }

                // Stop if we get below the quota
                if ($totalUploadSize <= $this->UploadQuota) {
                    break;
                }
            }
        }
    }

    /**
     * Return the list of subproject groups that belong to this project.
     */
    public function GetSubProjectGroups()
    {
        if (!$this->Id) {
            add_log('Id not set', 'Project::GetSubProjectGroups', LOG_ERR);
            return false;
        }

        $query = pdo_query(
            'SELECT id FROM subprojectgroup WHERE projectid=' . qnum($this->Id) . "
         AND endtime='1980-01-01 00:00:00'");
        if (!$query) {
            add_last_sql_error('Project::GetSubProjectGroups', $this->Id);
            return false;
        }

        $subProjectGroups = array();
        while ($result = pdo_fetch_array($query)) {
            $subProjectGroup = new SubProjectGroup();
            // SetId automatically loads the rest of the group's data.
            $subProjectGroup->SetId($result['id']);
            $subProjectGroups[] = $subProjectGroup;
        }
        return $subProjectGroups;
    }

    /**
     * Return a JSON representation of this object.
     */
    public function ConvertToJSON(User $user)
    {
        $config = Config::getInstance();
        $response = [];
        $clone = new \ReflectionObject($this);
        $properties = $clone->getProperties(\ReflectionProperty::IS_PUBLIC);
        foreach ($properties as $property) {
            $k = $property->getName();
            $v = $this->$k;
            $response[$k] = $v;
        }
        $response['name_encoded'] = urlencode($this->Name);

        if (strlen($this->CTestTemplateScript) === 0) {
            $response['ctesttemplatescript'] = $this->getDefaultJobTemplateScript();
        }

        $includeQuota = !$config->get('CDASH_USER_CREATE_PROJECTS') || $user->IsAdmin();

        if ($includeQuota) {
            $uploadQuotaGB = 0;

            if ($this->UploadQuota > 0) {
                $uploadQuotaGB = $this->UploadQuota / (1024 * 1024 * 1024);
            }

            $max = $config->get('CDASH_MAX_UPLOAD_QUOTA');
            $response['UploadQuota'] = min($uploadQuotaGB, $max);
            $response['MaxUploadQuota'] = $max;
        } else {
            unset($response['UploadQuota']);
        }
        return $response;
    }

    /**
     * Called once when the project is initially created.
     */
    public function InitialSetup()
    {
        if (!$this->Id) {
            return false;
        }

        // Add the default groups.
        $BuildGroup = new BuildGroup();
        $BuildGroup->SetName('Nightly');
        $BuildGroup->SetDescription('Nightly builds');
        $BuildGroup->SetSummaryEmail(0);
        $this->AddBuildGroup($BuildGroup);

        $BuildGroup = new BuildGroup();
        $BuildGroup->SetName('Continuous');
        $BuildGroup->SetDescription('Continuous builds');
        $BuildGroup->SetSummaryEmail(0);
        $this->AddBuildGroup($BuildGroup);

        $BuildGroup = new BuildGroup();
        $BuildGroup->SetName('Experimental');
        $BuildGroup->SetDescription('Experimental builds');
        // default to "No Email" for the Experimental group
        $BuildGroup->SetSummaryEmail(2);
        $this->AddBuildGroup($BuildGroup);

        // Set up overview page to initially contain just the "Nightly" group.
        $groups = $this->GetBuildGroups();
        foreach ($groups as $group) {
            if ($group->GetName() == 'Nightly') {
                $buildgroupid = $group->GetId();
                $query =
                    "INSERT INTO overview_components (projectid, buildgroupid, position, type)
                    VALUES ('$this->Id', '$buildgroupid', '1', 'build')";
                pdo_query($query);
                add_last_sql_error('CreateProject :: DefaultOverview', $this->Id);
                break;
            }
        }

        // Add administrator to the project.
        $UserProject = new UserProject();
        $UserProject->Role = 2;
        $UserProject->EmailType = 3;// receive all emails
        $UserProject->ProjectId = $this->Id;
        $UserProject->UserId = 1; // administrator
        $UserProject->Save();
    }

    public function AddBlockedBuild($buildname, $sitename, $ip)
    {
        $stmt = $this->PDO->prepare(
                'INSERT INTO blockbuild (projectid,buildname,sitename,ipaddress)
                VALUES (:projectid, :buildname, :sitename, :ip)');
        $stmt->bindParam(':projectid', $this->Id);
        $stmt->bindParam(':buildname', $buildname);
        $stmt->bindParam(':sitename', $sitename);
        $stmt->bindParam(':ip', $ip);
        pdo_execute($stmt);
        $blocked_id = pdo_insert_id('blockbuild');
        return $blocked_id;
    }

    public function RemoveBlockedBuild($id)
    {
        $stmt = $this->PDO->prepare('DELETE FROM blockbuild WHERE id=?');
        pdo_execute($stmt, [$id]);
    }

    // Return true and set error message if this project has too many builds.
    public function HasTooManyBuilds(&$message)
    {
        if (!$this->Id) {
            return false;
        }
        $config = Config::getInstance();
        if ($config->get('CDASH_BUILDS_PER_PROJECT') == 0 ||
                in_array($this->GetName(), $config->get('CDASH_UNLIMITED_PROJECTS'))) {
            return false;
        }

        if ($this->GetNumberOfBuilds() < $config->get('CDASH_BUILDS_PER_PROJECT')) {
            return false;
        }

        $message = "Maximum number of builds reached for $this->Name.  Contact {$config->get('CDASH_EMAILADMIN')} for support.";
        add_log("Too many builds for $this->Name", 'project_has_too_many_builds',
                LOG_INFO, $this->Id);
        return true;
    }

    /**
     * @returns \CDash\Collection\SubscriberCollection
     */
    public function GetSubscriberCollection()
    {
        if (!$this->SubscriberCollection) {
            $this->SubscriberCollection = $this->GetProjectSubscribers();
        }

        return $this->SubscriberCollection;
    }

    public function SetSubscriberCollection(SubscriberCollection $subscribers)
    {
        $this->SubscriberCollection = $subscribers;
    }

    public function GetProjectSubscribers()
    {
        $service = ServiceContainer::getInstance()->getContainer();
        $collection = $service->make(SubscriberCollection::class);
        $sql = '
            SELECT
              u2p.*,
              user.email email
            FROM user2project u2p
            JOIN user ON user.id = u2p.userid
            WHERE projectid = :id
        ';

        $user = $this->PDO->prepare($sql);
        $user->bindParam(':id', $this->Id, \PDO::PARAM_INT);
        $user->execute();

        foreach ($user->fetchAll(\PDO::FETCH_OBJ) as $row) {
            /** @var NotificationPreferences $preferences */
            $preferences = $service->make(
                BitmaskNotificationPreferences::class,
                ['mask' => $row->emailcategory]
            );
            $preferences->setPreferencesFromEmailTypeProperty($row->emailtype);
            $preferences->setPreferencesFromEmailSuccessProperty($row->emailsuccess);
            $preferences->setPreferenceFromMissingSiteProperty($row->emailmissingsites);
            $preferences->setEmailRedundantMessages($this->EmailRedundantFailures);

            /** @var Subscriber $subscriber */
            $subscriber = $service->make(Subscriber::class, ['preferences' => $preferences]);
            $subscriber
                ->setAddress($row->email)
                ->setUserId($row->userid);

            $collection->add($subscriber);
        }

        return $collection;
    }

    // Modify the build error/warning filters for this project if necessary.
    public function UpdateBuildFilters()
    {
        $buildErrorFilter = new BuildErrorFilter($this);
        if ($buildErrorFilter->GetErrorsFilter() != $this->ErrorsFilter ||
                $buildErrorFilter->GetWarningsFilter() != $this->WarningsFilter) {
            return $buildErrorFilter->AddOrUpdateFilters(
                    $this->WarningsFilter, $this->ErrorsFilter);
        }
        return true;
    }
}
