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
use CDash\Messaging\Notification\NotifyOn;
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

    const ACCESS_PRIVATE = 0;
    const ACCESS_PUBLIC = 1;
    const ACCESS_PROTECTED = 2;

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
    public $NightlyDateTime;
    public $NightlyTimezone;
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
    public $WebApiKey;
    public $WarningsFilter;
    public $ErrorsFilter;
    /** @var \PDO $PDO */
    private $PDO;

    /**
     * @var SubscriberCollection
     */
    private $SubscriberCollection;

    public $Filled;

    public function __construct()
    {
        $this->Initialize(); // why?
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
        $this->PDO = Database::getInstance();

        $this->Filled = false;
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

            if (!$this->UpdateBuildFilters()) {
                return false;
            }
        }
        return true;
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
        if ($this->Filled) {
            return;
        }

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
            $this->SetNightlyTime($project_array['nightlytime']);
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

        $this->Filled = true;
    }

    public function SetNightlyTime($nightly_time)
    {
        $this->NightlyTime = $nightly_time;

        // Get the timezone for the project's nightly start time.
        try {
            $this->NightlyDateTime = new \DateTime($this->NightlyTime);
            $this->NightlyTimezone = $this->NightlyDateTime->getTimezone();
        } catch (\Exception $e) {
            // Bad timezone (probably) specified, try defaulting to UTC.
            $this->NightlyTimezone = new \DateTimeZone('UTC');
            $parts = explode(' ', $nightly_time);
            $this->NightlyTime = $parts[0];
            try {
                $this->NightlyDateTime = new \DateTime($this->NightlyTime, $this->NightlyTimezone);
            } catch (\Exception $e) {
                \Log::error("Could not parse $nightly_time");
                return;
            }
        }

        // Attempt to deal with the fact that tz->getName() doesn't necessarily return
        // a "valid timezone ID".
        $timezone_name = timezone_name_from_abbr($this->NightlyTimezone->getName());
        if ($timezone_name === false) {
            $timezone_name = $this->NightlyTimezone->getName();
        }

        // Use the project's timezone by default.
        date_default_timezone_set($timezone_name);
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
        if (!config('cdash.show_last_submission')) {
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
        if (!is_array($first_build)) {
            return 0;
        }
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
        if (config('database.default') != 'pgsql') {
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
            $labelids[] = $label_array['id'];
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
        $recipients = [];
        foreach ($userids as $userid) {
            $User = new User;
            $User->Id = $userid;
            $recipients[] = $User->GetEmail();
        }

        if (!empty($recipients)) {
            $projectname = $project_array['name'];
            $emailtitle = 'CDash [' . $projectname . '] - Administration ';
            $emailbody = 'Object: ' . $subject . "\n";
            $emailbody .= $body . "\n";
            $serverName = $config->getServer();

            $emailbody .= "\n-CDash on " . $serverName . "\n";

            if (cdashmail($recipients, $emailtitle, $emailbody)) {
                add_log('email sent to: ' . implode(', ', $recipients), 'SendEmailToAdmin');
            } else {
                add_log('cannot send email to: ' . implode(', ', $recipients), 'SendEmailToAdmin', LOG_ERR, $this->Id);
            }
        }
    }

    public function getDefaultCTestUpdateType()
    {
        switch ($this->CvsViewerType) {
            case 'cgit':
            case 'github':
            case 'gitlab':
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
    public function ConvertToJSON(\App\Models\User $user)
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

        $includeQuota = !$config->get('CDASH_USER_CREATE_PROJECTS') || $user->IsAdmin();

        if ($includeQuota) {
            $uploadQuotaGB = 0;

            if ($this->UploadQuota > 0) {
                $uploadQuotaGB = $this->UploadQuota / (1024 * 1024 * 1024);
            }

            $max = config('cdash.max_upload_quota');
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

    // Delete old builds if this project has too many.
    public function CheckForTooManyBuilds()
    {
        if (!$this->Id) {
            return false;
        }
        $max_builds = config('cdash.builds_per_project');
        if ($max_builds == 0 ||
                in_array($this->GetName(), config('cdash.unlimited_projects'))) {
            return false;
        }

        $num_builds = $this->GetNumberOfBuilds();

        // The +1 here is to account for the build we're currently inserting.
        if ($num_builds < ($max_builds + 1)) {
            return false;
        }

        // Remove old builds.
        $num_to_remove = $num_builds - $max_builds;
        require_once 'include/autoremove.php';
        removeFirstBuilds($this->Id, -1, $num_to_remove, true, false);

        add_log("Too many builds for $this->Name", 'project_has_too_many_builds',
            LOG_INFO, $this->Id);
        return true;
    }

    /**
     * Returns the Project's SubscriberCollection object. This method lazily loads the
     * SubscriberCollection if the object does not exist.
     *
     * @returns \CDash\Collection\SubscriberCollection
     */
    public function GetSubscriberCollection()
    {
        if (!$this->SubscriberCollection) {
            $this->Fill();
            $this->SubscriberCollection = $this->GetProjectSubscribers();
        }

        return $this->SubscriberCollection;
    }

    /**
     * Sets the Project's SubscriberCollection property.
     *
     * @param SubscriberCollection $subscribers
     */
    public function SetSubscriberCollection(SubscriberCollection $subscribers)
    {
        $this->SubscriberCollection = $subscribers;
    }

    /**
     * Returns a SubscriberCollection; a collection of all users and their subscription preferences.
     *
     * @return SubscriberCollection
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public function GetProjectSubscribers()
    {
        $service = ServiceContainer::getInstance()->getContainer();
        $collection = $service->make(SubscriberCollection::class);
        $userTable = qid('user');
        // TODO: works, but maybe find a better query
        $sql = "
            SELECT
               u2p.*,
               u.email email,
               labelid haslabels
            FROM user2project u2p
              JOIN $userTable u ON u.id = u2p.userid
              LEFT JOIN labelemail ON labelemail.userid = u2p.userid
            WHERE u2p.projectid = :id
            ORDER BY u.email;
        ";

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
            if ($preferences->get(NotifyOn::NEVER)) {
                continue;
            }
            $preferences->set(NotifyOn::FIXED, $row->emailsuccess);
            $preferences->set(NotifyOn::SITE_MISSING, $row->emailmissingsites);
            $preferences->set(NotifyOn::REDUNDANT, $this->EmailRedundantFailures);
            $preferences->set(NotifyOn::LABELED, (bool)$row->haslabels);

            /** @var Subscriber $subscriber */
            $subscriber = $service->make(Subscriber::class, ['preferences' => $preferences]);
            $subscriber
                ->setAddress($row->email)
                ->setUserId($row->userid);

            $collection->add($subscriber);
        }

        return $collection;
    }

    /**
     * Returns a self referencing URI of the current Project.
     *
     * @return string
     */
    public function GetUrlForSelf()
    {
        $config = Config::getInstance();
        return "{$config->getBaseUrl()}/viewProject?projectid={$this->Id}";
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

    /**
     * Return the beginning and the end of the specified testing day
     * in DATETIME format.
     */
    public function ComputeTestingDayBounds($date)
    {
        list($unused, $beginning_timestamp) =
            get_dates($date, $this->NightlyTime);

        $datetime = new \DateTime();
        $datetime->setTimeStamp($beginning_timestamp);
        $datetime->add(new \DateInterval('P1D'));
        $end_timestamp = $datetime->getTimestamp();

        $beginningOfDay = gmdate(FMT_DATETIME, $beginning_timestamp);
        $endOfDay = gmdate(FMT_DATETIME, $end_timestamp);
        return [$beginningOfDay, $endOfDay];
    }
}
