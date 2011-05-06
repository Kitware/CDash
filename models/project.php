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
include_once('models/dailyupdatefile.php');
include_once('models/buildgrouprule.php');
include_once('models/buildgroupposition.php');

/** Main project class */
class Project
{
  var $Name;
  var $Id;
  var $Description;
  var $HomeUrl;
  var $CvsUrl;
  var $DocumentationUrl;
  var $BugTrackerUrl;
  var $BugTrackerFileUrl;
  var $ImageId;
  var $Public;
  var $CoverageThreshold;
  var $TestingDataUrl;
  var $NightlyTime;
  var $GoogleTracker;
  var $EmailLowCoverage;
  var $EmailTestTimingChanged;
  var $EmailBrokenSubmission;
  var $EmailRedundantFailures;
  var $CvsViewerType;
  var $TestTimeStd;
  var $TestTimeStdThreshold;
  var $ShowTestTime;
  var $TestTimeMaxStatus;
  var $EmailMaxItems;
  var $EmailMaxChars;
  var $EmailAdministrator;
  var $ShowIPAddresses;
  var $DisplayLabels;
  var $AutoremoveTimeframe;
  var $AutoremoveMaxBuilds;
  var $UploadQuota;
  var $RobotName;
  var $RobotRegex;
  var $CTestTemplateScript;
  var $WebApiKey;

  function __construct()
    {
    $this->Initialize();
    }

  /** Initialize non defined variables */
  private function Initialize()
    {
    if(empty($this->EmailLowCoverage))
      {
      $this->EmailLowCoverage=0;
      }
    if(empty($this->EmailTestTimingChanged))
      {
      $this->EmailTestTimingChanged=0;
      }
    if(empty($this->EmailBrokenSubmission))
      {
      $this->EmailBrokenSubmission=0;
      }
    if(empty($this->EmailRedundantFailures))
      {
      $this->EmailRedundantFailures=0;
      }
    if(empty($this->EmailAdministrator))
      {
      $this->EmailAdministrator=0;
      }
    if(empty($this->ShowIPAddresses))
      {
      $this->ShowIPAddresses=0;
      }
    if(empty($this->ShowTestTime))
      {
      $this->ShowTestTime=0;
      }
    if(empty($this->DisplayLabels))
      {
      $this->DisplayLabels=0;
      }
    if(empty($this->AutoremoveTimeframe))
      {
      $this->AutoremoveTimeframe=0;
      }
    if(empty($this->AutoremoveMaxBuilds))
      {
      $this->AutoremoveMaxBuilds=300;
      }
    if(empty($this->UploadQuota))
      {
      $this->UploadQuota=0;
      }
    if(empty($this->WebApiKey))
      {
      $this->WebApiKey='';
      }
    }

  /** Add a build group */
  function AddBuildGroup($buildgroup)
    {
    $buildgroup->ProjectId = $this->Id;
    $buildgroup->Save();
    }

  /** Delete a project */
  function Delete()
    {
    if(!$this->Id)
      {
      return false;
      }
    // Remove the project groups and rules
    $buildgroup = pdo_query("SELECT * FROM buildgroup WHERE projectid=$this->Id");
    while($buildgroup_array = pdo_fetch_array($buildgroup))
      {
      $groupid = $buildgroup_array["id"];
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
    while($dailyupdate_array = pdo_fetch_array($dailyupdate))
      {
      $dailyupdateid = $dailyupdate_array['id'];
      pdo_query("DELETE FROM dailyupdatefile WHERE dailyupdateid='$dailyupdateid'");
      }

    pdo_query("DELETE FROM dailyupdate WHERE projectid=$this->Id");
    pdo_query("DELETE FROM projectrobot WHERE projectid=$this->Id");
    pdo_query("DELETE FROM projectjobscript WHERE projectid=$this->Id");

    pdo_query("DELETE FROM project WHERE id=$this->Id");
    }

  /** Return if a project exists */
  function Exists()
    {
    // If no id specify return false
    if(!$this->Id)
      {
      return false;
      }

    $query = pdo_query("SELECT count(*) FROM project WHERE id='".$this->Id."'");
    $query_array = pdo_fetch_array($query);
    if($query_array[0]>0)
      {
      return true;
      }
    return false;
    }

  // Save the project in the database
  function Save()
    {
    // Escape the values
    $Description = pdo_real_escape_string($this->Description);
    $HomeUrl = pdo_real_escape_string($this->HomeUrl);
    $CvsUrl = pdo_real_escape_string($this->CvsUrl);
    $DocumentationUrl = pdo_real_escape_string($this->DocumentationUrl);
    $BugTrackerUrl = pdo_real_escape_string($this->BugTrackerUrl);
    $BugTrackerFileUrl = pdo_real_escape_string($this->BugTrackerFileUrl);
    $TestingDataUrl = pdo_real_escape_string($this->TestingDataUrl);
    $NightlyTime = pdo_real_escape_string($this->NightlyTime);
    $GoogleTracker = pdo_real_escape_string($this->GoogleTracker);
    $RobotName = pdo_real_escape_string($this->RobotName);
    $RobotRegex = pdo_real_escape_string($this->RobotRegex);
    $Name = pdo_real_escape_string($this->Name);
    $CvsViewerType = pdo_real_escape_string($this->CvsViewerType);

    // Check if the project is already
    if($this->Exists())
      {
      // Trim the name
      $this->Name = trim($this->Name);
      $this->Initialize();
      // Update the project
      $query = "UPDATE project SET ";
      $query .= "description='".$Description."'";
      $query .= ",homeurl='".$HomeUrl."'";
      $query .= ",cvsurl='".$CvsUrl."'";
      $query .= ",documentationurl='".$DocumentationUrl."'";
      $query .= ",bugtrackerurl='".$BugTrackerUrl."'";
      $query .= ",bugtrackerfileurl='".$BugTrackerFileUrl."'";
      $query .= ",public=".qnum($this->Public);
      $query .= ",coveragethreshold=".qnum($this->CoverageThreshold);
      $query .= ",testingdataurl='".$TestingDataUrl."'";
      $query .= ",nightlytime='".$NightlyTime."'";
      $query .= ",googletracker='".$GoogleTracker."'";
      $query .= ",emaillowcoverage=".qnum($this->EmailLowCoverage);
      $query .= ",emailtesttimingchanged=".qnum($this->EmailTestTimingChanged);
      $query .= ",emailbrokensubmission=".qnum($this->EmailBrokenSubmission);
      $query .= ",emailredundantfailures=".qnum($this->EmailRedundantFailures);
      $query .= ",emailadministrator=".qnum($this->EmailAdministrator);
      $query .= ",showipaddresses=".qnum($this->ShowIPAddresses);
      $query .= ",displaylabels=".qnum($this->DisplayLabels);
      $query .= ",autoremovetimeframe=".qnum($this->AutoremoveTimeframe);
      $query .= ",autoremovemaxbuilds=".qnum($this->AutoremoveMaxBuilds);
      $query .= ",uploadquota=".qnum($this->UploadQuota);
      $query .= ",cvsviewertype='".$CvsViewerType."'";
      $query .= ",testtimestd=".qnum($this->TestTimeStd);
      $query .= ",testtimestdthreshold=".qnum($this->TestTimeStdThreshold);
      $query .= ",showtesttime=".qnum($this->ShowTestTime);
      $query .= ",testtimemaxstatus=".qnum($this->TestTimeMaxStatus);
      $query .= ",emailmaxitems=".qnum($this->EmailMaxItems);
      $query .= ",emailmaxchars=".qnum($this->EmailMaxChars);
      $query .= " WHERE id=".qnum($this->Id)."";

      if(!pdo_query($query))
        {
        add_last_sql_error("Project Update",$this->Id);
        return false;
        }

      if($this->RobotName != '')
        {
        // Check if it exists
        $robot = pdo_query("SELECT projectid FROM projectrobot WHERE projectid=".qnum($this->Id));
        if(pdo_num_rows($robot)>0)
          {
          $query = "UPDATE projectrobot SET robotname='".$RobotName."',authorregex='".$RobotRegex.
                   "' WHERE projectid=".qnum($this->Id);
          if(!pdo_query($query))
            {
            add_last_sql_error("Project Update",$this->Id);
            return false;
            }
          }
        else
          {
          $query = "INSERT INTO projectrobot(projectid,robotname,authorregex)
                   VALUES (".qnum($this->Id).",'".$RobotName."','".$RobotRegex."')";
          if(!pdo_query($query))
            {
            add_last_sql_error("Project Update",$this->Id);
            return false;
            }
          }
        }

      // Insert the ctest template
      if($this->CTestTemplateScript != '')
        {
        $CTestTemplateScript = pdo_real_escape_string($this->CTestTemplateScript);

        // Check if it exists
        $script = pdo_query("SELECT projectid FROM projectjobscript WHERE projectid=".qnum($this->Id));
        if(pdo_num_rows($script)>0)
          {
          $query = "UPDATE projectjobscript SET script='".$CTestTemplateScript."' WHERE projectid=".qnum($this->Id);
          if(!pdo_query($query))
            {
            return false;
            }
          }
        else
          {
          $query = "INSERT INTO projectjobscript(projectid,script)
                   VALUES (".qnum($this->Id).",'".$CTestTemplateScript."')";
          if(!pdo_query($query))
            {
            return false;
            }
          }
        }
      else
        {
        pdo_query("DELETE FROM projectjobscript WHERE projectid=$this->Id");
        }

      }
    else // insert the project
      {
      $id = "";
      $idvalue = "";
      if($this->Id)
        {
        $id = "id,";
        $idvalue = "'".$this->Id."',";
        }

      if(strlen($this->ImageId) == 0)
        {
        $this->ImageId = 0;
        }

      // Trim the name
      $this->Name = trim($this->Name);
      $this->Initialize();
      $query = "INSERT INTO project(".$id."name,description,homeurl,cvsurl,bugtrackerurl,bugtrackerfileurl,documentationurl,public,imageid,coveragethreshold,testingdataurl,
                                    nightlytime,googletracker,emailbrokensubmission,emailredundantfailures,
                                    emaillowcoverage,emailtesttimingchanged,cvsviewertype,
                                    testtimestd,testtimestdthreshold,testtimemaxstatus,emailmaxitems,emailmaxchars,showtesttime,emailadministrator,showipaddresses
                                    ,displaylabels,autoremovetimeframe,autoremovemaxbuilds,uploadquota,webapikey)
                 VALUES (".$idvalue."'$Name','$Description','$HomeUrl','$CvsUrl','$BugTrackerUrl','$BugTrackerFileUrl','$DocumentationUrl',
                 ".qnum($this->Public).",".qnum($this->ImageId).",".qnum($this->CoverageThreshold).",'$TestingDataUrl','$NightlyTime',
                 '$GoogleTracker',".qnum($this->EmailBrokenSubmission).",".qnum($this->EmailRedundantFailures).","
                 .qnum($this->EmailLowCoverage).",".qnum($this->EmailTestTimingChanged).",'$CvsViewerType',".qnum($this->TestTimeStd)
                 .",".qnum($this->TestTimeStdThreshold).",".qnum($this->TestTimeMaxStatus).",".qnum($this->EmailMaxItems).",".qnum($this->EmailMaxChars).","
                 .qnum($this->ShowTestTime).",".qnum($this->EmailAdministrator).",".qnum($this->ShowIPAddresses).",".qnum($this->DisplayLabels)
                 .",".qnum($this->AutoremoveTimeframe).",".qnum($this->AutoremoveMaxBuilds).",".qnum($this->UploadQuota).",'".$this->WebApiKey."')";

      if(pdo_query($query))
        {
        $this->Id = pdo_insert_id("project");
        }
      else
        {
        add_last_sql_error("Project Create");
        return false;
        }

      if($this->RobotName != '')
        {
        $query = "INSERT INTO projectrobot(projectid,robotname,authorregex)
                 VALUES (".qnum($this->Id).",'".$RobotName."','".$RobotRegex."')";
        if(!pdo_query($query))
          {
          return false;
          }
        }

      if($this->CTestTemplateScript != '')
        {
        $CTestTemplateScript = pdo_real_escape_string($this->CTestTemplateScript);

        $query = "INSERT INTO projectjobscript(projectid,script)
                 VALUES (".qnum($this->Id).",'".$$CTestTemplateScript."')";
        if(!pdo_query($query))
          {
          return false;
          }
        }
      }

    return true;
    }

  /** Get the user's role */
  function GetUserRole($userid)
    {
    if(!$this->Id || !is_numeric($this->Id))
      {
      return -1;
      }

    $role = -1;

    $user2project = pdo_query("SELECT role FROM user2project WHERE userid='$userid' AND projectid='".$this->Id."'");
    if(pdo_num_rows($user2project)>0)
      {
      $user2project_array = pdo_fetch_array($user2project);
      $role = $user2project_array["role"];
      }

    return $role;
    }

  /** Return true if the project exists */
  function ExistsByName($name)
    {
    $project = pdo_query("SELECT id FROM project WHERE name='$name'");
    if(pdo_num_rows($project)>0)
      {
      return true;
      }
    return false;
    }

  /** Get the logo id */
  function GetLogoId()
    {
    $query = pdo_query("SELECT imageid FROM project WHERE id=".$this->Id);

    if(!$query)
      {
      add_last_sql_error("Project GetLogoId",$this->Id);
      return 0;
      }

    if($query_array = pdo_fetch_array($query))
      {
      return $query_array["imageid"];
      }
    return 0;
    }

  /** Fill in all the information from the database */
  function Fill()
    {
    if(!$this->Id)
      {
      echo "Project Fill(): Id not set";
      }

    $project = pdo_query("SELECT * FROM project WHERE id=".$this->Id);
    if(!$project)
      {
      add_last_sql_error("Project Fill",$this->Id);
      return;
      }

    if($project_array = pdo_fetch_array($project))
      {
      $this->Name = $project_array['name'];
      $this->Description = $project_array['description'];
      $this->HomeUrl = $project_array['homeurl'];
      $this->CvsUrl = $project_array['cvsurl'];
      $this->DocumentationUrl = $project_array['documentationurl'];
      $this->BugTrackerUrl = $project_array['bugtrackerurl'];
      $this->BugTrackerFileUrl = $project_array['bugtrackerfileurl'];
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
      if($this->WebApiKey == '')
        {
        // If no web API key exists, we add one
        include_once('cdash/common.php');
        $newKey = generate_web_api_key();
        pdo_query("UPDATE project SET webapikey='$newKey' WHERE id=".$this->Id);
        $this->WebApiKey = $newKey;
        }
      }

    // Check if we have a robot
    $robot = pdo_query("SELECT * FROM projectrobot WHERE projectid=".$this->Id);
    if(!$robot)
      {
      add_last_sql_error("Project Fill",$this->Id);
      return;
      }

    if($robot_array = pdo_fetch_array($robot))
      {
      $this->RobotName = $robot_array['robotname'];
      $this->RobotRegex = $robot_array['authorregex'];
      }

    // Check if we have a ctest script
    $script = pdo_query("SELECT script FROM projectjobscript WHERE projectid=".$this->Id);
    if(!$script)
      {
      add_last_sql_error("Project Fill",$this->Id);
      return;
      }
    if($script_array = pdo_fetch_array($script))
      {
      $this->CTestTemplateScript = $script_array['script'];
      }
    }

  /** Add a logo */
  function AddLogo($contents,$filetype)
    {
    if(strlen($contents) == 0)
      {
      return;
      }

    $imgid = $this->GetLogoId();
    $checksum = crc32($contents);

    //check if we already have a copy of this file in the database
    $sql = "SELECT id FROM image WHERE checksum = '$checksum'";
    $result = pdo_query("$sql");
    if($row = pdo_fetch_array($result))
      {
      $imgid = $row["id"];
      // Insert into the project
      pdo_query("UPDATE project SET imageid=".qnum($imgid)." WHERE id=".$this->Id);
      add_last_sql_error("Project AddLogo",$this->Id);
      }
    else if($imgid==0)
      {
      include("cdash/config.php");
      if($CDASH_DB_TYPE == "pgsql")
        {
        $contents = pg_escape_bytea($contents);
        }
      $sql = "INSERT INTO image(img, extension, checksum) VALUES ('$contents', '$filetype', '$checksum')";
      if(pdo_query("$sql"))
        {
        $imgid = pdo_insert_id("image");

        // Insert into the project
        pdo_query("UPDATE project SET imageid=".qnum($imgid)." WHERE id=".qnum($this->Id));
        add_last_sql_error("Project AddLogo",$this->Id);
        }
      }
     else // update the current image
       {
       include("cdash/config.php");
       if($CDASH_DB_TYPE == "pgsql")
         {
         $contents = pg_escape_bytea($contents);
         }
       pdo_query("UPDATE image SET img='$contents',extension='$filetype',checksum='$checksum' WHERE id=".qnum($imgid));
       add_last_sql_error("Project AddLogo",$this->Id);
       }
    return $imgid;
    }

  /** Add CVS/SVN repositories */
  function AddRepositories($repositories, $usernames, $passwords,$branches)
    {
    // First we update/delete any registered repositories
    $currentRepository = 0;
    $repositories_query = pdo_query("SELECT repositoryid FROM project2repositories WHERE projectid=".qnum($this->Id)." ORDER BY repositoryid");
    add_last_sql_error("Project AddRepositories",$this->Id);
    while($repository_array = pdo_fetch_array($repositories_query))
      {
      $repositoryid = $repository_array["repositoryid"];
      if(!isset($repositories[$currentRepository]) || strlen($repositories[$currentRepository])==0)
        {
        $query = pdo_query("SELECT * FROM project2repositories WHERE repositoryid=".qnum($repositoryid));
        add_last_sql_error("Project AddRepositories",$this->Id);
        if(pdo_num_rows($query)==1)
          {
          pdo_query("DELETE FROM repositories WHERE id='$repositoryid'");
          add_last_sql_error("Project AddRepositories",$this->Id);
          }
        pdo_query("DELETE FROM project2repositories WHERE projectid=".qnum($this->Id)." AND repositoryid=".qnum($repositoryid));
        add_last_sql_error("Project AddRepositories",$this->Id);
        }
      else
        {
        // If the repository is not shared by any other project we update
        $count_query = pdo_query("SELECT count(*) as c FROM project2repositories WHERE repositoryid=".qnum($repositoryid));
        $count_array = pdo_fetch_array($count_query);
        if($count_array['c']==1)
          {
          pdo_query("UPDATE repositories SET url='$repositories[$currentRepository]',
                          username='$usernames[$currentRepository]',
                          password='$passwords[$currentRepository]',
                          branch='$branches[$currentRepository]'
                          WHERE id=".qnum($repositoryid));
          add_last_sql_error("Project AddRepositories",$this->Id);
          }
        else // Otherwise we remove it from the current project and add it to the queue to be created
          {
          pdo_query("DELETE FROM project2repositories WHERE projectid=".qnum($this->Id)." AND repositoryid=".qnum($repositoryid));
          add_last_sql_error("Project AddRepositories",$this->Id);
          $repositories[] = $repositories[$currentRepository];
          $usernames[] = $usernames[$currentRepository];
          $passwords[] = $passwords[$currentRepository];
          $branches[] = $branches[$currentRepository];
          }
        }
      $currentRepository++;
      }

    //  Then we add new repositories
    for($i=$currentRepository;$i<count($repositories);$i++)
      {
      $url = $repositories[$i];
      $username = $usernames[$i];
      $password = $passwords[$i];
      $branch = $branches[$i];
      if(strlen($url) == 0)
        {
        continue;
        }

      // Insert into repositories if not any
      $repositories_query = pdo_query("SELECT id FROM repositories WHERE url='$url'");
      if(pdo_num_rows($repositories_query) == 0)
        {
        pdo_query("INSERT INTO repositories (url, username, password, branch) VALUES ('$url', '$username', '$password','$branch')");
        add_last_sql_error("Project AddRepositories",$this->Id);
        $repositoryid = pdo_insert_id("repositories");
        }
      else
        {
        $repositories_array = pdo_fetch_array($repositories_query);
        $repositoryid = $repositories_array["id"];
        }
      pdo_query("INSERT INTO project2repositories (projectid,repositoryid) VALUES (".qnum($this->Id).",'$repositoryid')");
      add_last_sql_error("Project AddRepositories",$this->Id);
      } // end add repository
    } // end function   AddRepositories

   /** Get the repositories */
   function GetRepositories()
     {
     $repositories = array();
     $repository = pdo_query("SELECT url,username,password,branch from repositories,project2repositories
                               WHERE repositories.id=project2repositories.repositoryid
                               AND project2repositories.projectid=".qnum($this->Id));
     add_last_sql_error("Project GetRepositories",$this->Id);
     while($repository_array = pdo_fetch_array($repository))
       {
       $rep['url'] = $repository_array['url'];
       $rep['username'] = $repository_array['username'];
       $rep['password'] = $repository_array['password'];
       $rep['branch'] = $repository_array['branch'];
       $repositories[] = $rep;
       }
     return $repositories;
     } // end GetRepositories

  /** Get the build groups */
   function GetBuildGroups()
     {
     $buildgroups = array();
     $query = pdo_query("SELECT id,name,autoremovetimeframe FROM buildgroup
                         WHERE projectid=".qnum($this->Id));

     add_last_sql_error("Project GetBuildGroups",$this->Id);
     while($buildgroup = pdo_fetch_array($query))
       {
       $group['id'] = $buildgroup['id'];
       $group['name'] = $buildgroup['name'];
       $group['autoremovetimeframe'] = $buildgroup['autoremovetimeframe'];
       $buildgroups[] = $group;
       }
     return $buildgroups;
     } // end GetBuildGroups

  /** Get the list of block builds */
  function GetBlockedBuilds()
    {
    $sites = array();
    $site = pdo_query("SELECT id,buildname,sitename,ipaddress FROM blockbuild
                             WHERE projectid=".qnum($this->Id));
    add_last_sql_error("Project GetBlockedBuilds",$this->Id);
    while($site_array = pdo_fetch_array($site))
       {
       $sites[] = $site_array;
       }
    return $sites;
    }

  /** Get Ids of all the project registered
   *  Maybe this function should go somewhere else but for now here */
  function GetIds()
    {
    $ids = array();
    $query = pdo_query("SELECT id FROM project ORDER BY id");
    add_last_sql_error("Project GetIds",$this->Id);
    while($query_array = pdo_fetch_array($query))
      {
      $ids[] = $query_array["id"];
      }
    return $ids;
    }

  /** Get the Name of the project */
  function GetName()
    {
    if(strlen($this->Name)>0)
      {
      return $this->Name;
      }

    if(!$this->Id)
      {
      echo "Project GetName(): Id not set";
      return false;
      }

    $project = pdo_query("SELECT name FROM project WHERE id=".qnum($this->Id));
    if(!$project)
      {
      add_last_sql_error("Project GetName",$this->Id);
      return false;
      }
    $project_array = pdo_fetch_array($project);
    $this->Name = $project_array['name'];

    return $this->Name;
    }

  /** Get the coveragethreshold */
  function GetCoverageThreshold()
    {
    if(strlen($this->CoverageThreshold)>0)
      {
      return $this->CoverageThreshold;
      }

    if(!$this->Id)
      {
      echo "Project GetCoverageThreshold(): Id not set";
      return false;
      }

    $project = pdo_query("SELECT coveragethreshold FROM project WHERE id=".qnum($this->Id));
    if(!$project)
      {
      add_last_sql_error("Project GetCoverageThreshold",$this->Id);
      return false;
      }
    $project_array = pdo_fetch_array($project);
    $this->CoverageThreshold = $project_array['coveragethreshold'];

    return $this->CoverageThreshold;
    }

  /** Get the number of subproject */
  function GetNumberOfSubProjects()
    {
    if(!$this->Id)
      {
      echo "Project GetNumberOfSubprojects(): Id not set";
      return false;
      }

    $project = pdo_query("SELECT count(*) FROM subproject WHERE projectid=".qnum($this->Id));
    if(!$project)
      {
      add_last_sql_error("Project GetNumberOfSubprojects",$this->Id);
      return false;
      }
    $project_array = pdo_fetch_array($project);
    return $project_array[0];
    }

  /** Get the subproject ids*/
  function GetSubProjects($date=NULL)
    {
    if(!$this->Id)
      {
      echo "Project GetNumberOfSubprojects(): Id not set";
      return false;
      }

    // If not set, the date is now
    if($date == NULL)
      {
      $date = gmdate(FMT_DATETIME);
      }

    $project = pdo_query("SELECT id FROM subproject WHERE projectid=".qnum($this->Id)." AND
                          starttime<='".$date."' AND (endtime>'".$date."' OR endtime='1980-01-01 00:00:00')");
    if(!$project)
      {
      add_last_sql_error("Project GetSubProjects",$this->Id);
      return false;
      }

    $ids = array();
    while($project_array = pdo_fetch_array($project))
      {
      $ids[] = $project_array['id'];
      }
    return $ids;
    }

  /** Get the last submission of the subproject*/
  function GetLastSubmission()
    {
    if(!$this->Id)
      {
      echo "Project GetLastSubmission(): Id not set";
      return false;
      }

    $build = pdo_query("SELECT submittime FROM build WHERE projectid=".qnum($this->Id).
                         " ORDER BY submittime DESC LIMIT 1");

    if(!$build)
      {
      add_last_sql_error("Project GetLastSubmission",$this->Id);
      return false;
      }
    $build_array = pdo_fetch_array($build);
    return date(FMT_DATETIMESTD,strtotime($build_array['submittime']. "UTC"));
    }

  /** Get the total number of builds for a project*/
  function GetTotalNumberOfBuilds()
    {
    if(!$this->Id)
      {
      echo "Project GetTotalNumberOfBuilds(): Id not set";
      return false;
      }

    $project = pdo_query("SELECT count(*) FROM build WHERE projectid=".qnum($this->Id));

    if(!$project)
      {
      add_last_sql_error("Project GetTotalNumberOfBuilds",$this->Id);
      return false;
      }
    $project_array = pdo_fetch_array($project);
    return $project_array[0];
    }

  /** Get the number of builds given a date range */
  function GetNumberOfBuilds($startUTCdate,$endUTCdate)
    {
    if(!$this->Id)
      {
      echo "Project GetNumberOfBuilds(): Id not set";
      return false;
      }

    $project = pdo_query("SELECT count(build.id) FROM build WHERE projectid=".qnum($this->Id).
                         " AND build.starttime>'$startUTCdate'
                           AND build.starttime<='$endUTCdate'");

    if(!$project)
      {
      add_last_sql_error("Project GetNumberOfBuilds",$this->Id);
      return false;
      }
    $project_array = pdo_fetch_array($project);
    return $project_array[0];
    }

  /** Get the number of builds given per day */
  function GetBuildsDailyAverage($startUTCdate,$endUTCdate)
    {
    if(!$this->Id)
      {
      echo "Project GetNumberOfBuilds(): Id not set";
      return false;
      }
    $nbuilds=$this->GetNumberOfBuilds($startUTCdate,$endUTCdate);
    $project = pdo_query("SELECT starttime FROM build WHERE projectid=".qnum($this->Id).
                           " AND build.starttime>'$startUTCdate'
                             AND build.starttime<='$endUTCdate'
                             ORDER BY starttime ASC
                             LIMIT 1");
    $first_build=pdo_fetch_array($project);
    $first_build=$first_build['starttime'];
    $nb_days=strtotime($endUTCdate)-strtotime($first_build);
    $nb_days=intval($nb_days/86400)+1;
    if(!$project)
      {
      return 0;
      }

    return $nbuilds/$nb_days;
    }

  /** Get the number of warning builds given a date range */
  function GetNumberOfWarningBuilds($startUTCdate,$endUTCdate)
    {
    if(!$this->Id)
      {
      echo "Project GetNumberOfWarningBuilds(): Id not set";
      return false;
      }

    $project = pdo_query("SELECT count(*) FROM build,build2group,buildgroup
                          WHERE build.projectid=".qnum($this->Id).
                          " AND build.starttime>'$startUTCdate'
                           AND build.starttime<='$endUTCdate'
                           AND build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                           AND buildgroup.includesubprojectotal=1
                           AND build.buildwarnings>0");

    if(!$project)
      {
      add_last_sql_error("Project GetNumberOfWarningBuilds",$this->Id);
      return false;
      }
    $project_array = pdo_fetch_array($project);
    $count = $project_array[0];
    return $count;
    }

  /** Get the number of error builds given a date range */
  function GetNumberOfErrorBuilds($startUTCdate,$endUTCdate)
    {
    if(!$this->Id)
      {
      echo "Project GetNumberOfErrorBuilds(): Id not set";
      return false;
      }

    // build failures
    $project = pdo_query("SELECT count(*) FROM build,build2group,buildgroup
                          WHERE build.projectid=".qnum($this->Id).
                          " AND build.starttime>'$startUTCdate'
                           AND build.starttime<='$endUTCdate'
                           AND build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                           AND buildgroup.includesubprojectotal=1
                           AND build.builderrors>0");

    if(!$project)
      {
      add_last_sql_error("Project GetNumberOfErrorBuilds",$this->Id);
      return false;
      }
    $project_array = pdo_fetch_array($project);
    $count = $project_array[0];
    return $count;
    }

  /** Get the number of failing builds given a date range */
  function GetNumberOfPassingBuilds($startUTCdate,$endUTCdate)
    {
    if(!$this->Id)
      {
      echo "Project GetNumberOfPassingBuilds(): Id not set";
      return false;
      }

    $project = pdo_query("SELECT count(*) FROM build,build2group,buildgroup
                          WHERE build.projectid=".qnum($this->Id).
                          " AND build.starttime>'$startUTCdate'
                           AND build.starttime<='$endUTCdate'
                           AND build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                           AND buildgroup.includesubprojectotal=1
                           AND build.builderrors=0
                           AND build.buildwarnings=0
                           ");

    if(!$project)
      {
      add_last_sql_error("Project GetNumberOfPassingBuilds",$this->Id);
      return false;
      }
    $project_array = pdo_fetch_array($project);
    return $project_array[0];
    }

  /** Get the number of failing configure given a date range */
  function GetNumberOfWarningConfigures($startUTCdate,$endUTCdate)
    {
    if(!$this->Id)
      {
      echo "Project GetNumberOfWarningConfigures(): Id not set";
      return false;
      }

    $project = pdo_query("SELECT count(*) FROM build,configure,build2group,buildgroup
                          WHERE  configure.buildid=build.id  AND build.projectid=".qnum($this->Id).
                         " AND build.starttime>'$startUTCdate'
                           AND build.starttime<='$endUTCdate'
                           AND build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                           AND buildgroup.includesubprojectotal=1
                           AND  configure.warnings>0
                           ");
    if(!$project)
      {
      add_last_sql_error("Project GetNumberOfWarningConfigures",$this->Id);
      return false;
      }
    $project_array = pdo_fetch_array($project);
    return $project_array[0];
    }

  /** Get the number of failing configure given a date range */
  function GetNumberOfErrorConfigures($startUTCdate,$endUTCdate)
    {
    if(!$this->Id)
      {
      echo "Project GetNumberOfErrorConfigures(): Id not set";
      return false;
      }

    $project = pdo_query("SELECT count(*) FROM build,configure,buildgroup,build2group
                          WHERE  configure.buildid=build.id  AND build.projectid=".qnum($this->Id).
                         " AND build.starttime>'$startUTCdate'
                           AND build.starttime<='$endUTCdate'
                           AND configure.status='1'
                          AND build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                          AND buildgroup.includesubprojectotal=1");
    if(!$project)
      {
      add_last_sql_error("Project GetNumberOfErrorConfigures",$this->Id);
      return false;
      }
    $project_array = pdo_fetch_array($project);
    return $project_array[0];
    }

  /** Get the number of failing configure given a date range */
  function GetNumberOfPassingConfigures($startUTCdate,$endUTCdate)
    {
    if(!$this->Id)
      {
      echo "Project GetNumberOfPassingConfigures(): Id not set";
      return false;
      }

    $project = pdo_query("SELECT count(*) FROM configure,build,build2group,buildgroup WHERE build.projectid=".qnum($this->Id).
                         " AND build2group.buildid=build.id AND build2group.groupid=buildgroup.id
                           AND buildgroup.includesubprojectotal=1
                           AND configure.buildid=build.id AND build.starttime>'$startUTCdate'
                           AND build.starttime<='$endUTCdate' AND configure.status='0'");
    if(!$project)
      {
      add_last_sql_error("Project GetNumberOfPassingConfigures",$this->Id);
      return false;
      }
    $project_array = pdo_fetch_array($project);
    return $project_array[0];
    }

  /** Get the number of tests given a date range */
  function GetNumberOfPassingTests($startUTCdate,$endUTCdate)
    {
    if(!$this->Id)
      {
      echo "Project GetNumberOfPassingTests(): Id not set";
      return false;
      }

    $project = pdo_query("SELECT SUM(build.testpassed) FROM build,build2group,buildgroup WHERE build.projectid=".qnum($this->Id).
                         " AND build2group.buildid=build.id
                           AND build.testpassed>=0
                           AND build2group.groupid=buildgroup.id
                           AND buildgroup.includesubprojectotal=1
                           AND build.starttime>'$startUTCdate'
                           AND build.starttime<='$endUTCdate'");
    if(!$project)
      {
      add_last_sql_error("Project GetNumberOfPassingTests",$this->Id);
      return false;
      }
    $project_array = pdo_fetch_array($project);
    return $project_array[0];
    }

  /** Get the number of tests given a date range */
  function GetNumberOfFailingTests($startUTCdate,$endUTCdate)
    {
    if(!$this->Id)
      {
      echo "Project GetNumberOfFailingTests(): Id not set";
      return false;
      }

    $project = pdo_query("SELECT SUM(build.testfailed) FROM build,build2group,buildgroup WHERE build.projectid=".qnum($this->Id).
                         " AND build2group.buildid=build.id
                           AND build.testfailed>=0
                           AND build2group.groupid=buildgroup.id
                           AND buildgroup.includesubprojectotal=1
                           AND build.starttime>'$startUTCdate'
                           AND build.starttime<='$endUTCdate'");
    if(!$project)
      {
      add_last_sql_error("Project GetNumberOfFailingTests",$this->Id);
      return false;
      }
    $project_array = pdo_fetch_array($project);
    return $project_array[0];
    }

  /** Get the number of tests given a date range */
  function GetNumberOfNotRunTests($startUTCdate,$endUTCdate)
    {
    if(!$this->Id)
      {
      echo "Project GetNumberOfNotRunTests(): Id not set";
      return false;
      }

    $project = pdo_query("SELECT SUM(build.testnotrun) FROM build,build2group,buildgroup WHERE build.projectid=".qnum($this->Id).
                         " AND build2group.buildid=build.id
                           AND build.testnotrun>=0
                           AND build2group.groupid=buildgroup.id
                           AND buildgroup.includesubprojectotal=1
                           AND build.starttime>'$startUTCdate'
                           AND build.starttime<='$endUTCdate'");
    if(!$project)
      {
      add_last_sql_error("Project GetNumberOfNotRunTests",$this->Id);
      return false;
      }
    $project_array = pdo_fetch_array($project);
    return $project_array[0];
    }  // end GetNumberOfNotRunTests()

  /** Get the labels ids for a given project */
  function GetLabels($days)
    {
    $todaytime = time();
    $todaytime -= 3600*24*$days;
    $today = date(FMT_DATETIMESTD,$todaytime);

    $labelids = array();
    $labels = pdo_query("SELECT label.id as labelid FROM label WHERE
                         label.id IN (SELECT labelid AS id FROM label2build,build
                            WHERE label2build.buildid=build.id AND build.projectid=".qnum($this->Id)." AND build.starttime>'$today')
                         OR label.id IN (SELECT labelid AS id FROM label2test,build
                            WHERE label2test.buildid=build.id AND build.projectid=".qnum($this->Id)." AND build.starttime>'$today')
                         OR label.id IN (SELECT labelid AS id FROM label2coveragefile,build
                            WHERE label2coveragefile.buildid=build.id AND build.projectid=".qnum($this->Id)." AND build.starttime>'$today')
                         OR label.id IN (SELECT labelid AS id FROM label2buildfailure,buildfailure,build
                            WHERE label2buildfailure.buildfailureid=buildfailure.id AND buildfailure.buildid=build.id
                                  AND build.projectid=".qnum($this->Id)." AND build.starttime>'$today')
                         OR label.id IN (SELECT labelid AS id FROM label2dynamicanalysis,dynamicanalysis,build
                            WHERE label2dynamicanalysis.dynamicanalysisid=dynamicanalysis.id AND dynamicanalysis.buildid=build.id
                            AND build.projectid=".qnum($this->Id)." AND build.starttime>'$today')
                         ");
    if(!$labels)
      {
      add_last_sql_error("Project GetLabels",$this->Id);
      return false;
      }

    while($label_array = pdo_fetch_array($labels))
      {
      $labelids[] = $label_array['labelid'];
      }

    return array_unique($labelids);
    } // end GetLabels()

  /** Send an email to the administrator of the project */
  function SendEmailToAdmin($subject,$body)
    {
    if(!$this->Id)
      {
      echo "Project SendEmailToAdmin(): Id not set";
      return false;
      }

    include('cdash/config.php');

    // Check if we should send emails
    $project = pdo_query("SELECT emailadministrator,name FROM project WHERE id =".qnum($this->Id));
    if(!$project)
      {
      add_last_sql_error("Project SendEmailToAdmin",$this->Id);
      return false;
      }
    $project_array = pdo_fetch_array($project);

    if($project_array['emailadministrator'] == 0)
      {
      return;
      }

    // Find the site maintainers
    include_once('models/userproject.php');
    include_once('models/user.php');
    $UserProject = new UserProject();
    $UserProject->ProjectId = $this->Id;

    $userids = $UserProject->GetUsers(2); // administrators
    $email = "";
    foreach($userids as $userid)
      {
      $User = new User;
      $User->Id = $userid;
      if($email != "")
        {
        $email .= ", ";
        }
      $email .= $User->GetEmail();
      }

    if($email!="")
      {
      $projectname = $project_array['name'];
      $emailtitle = "CDash [".$projectname."] - Administration ";
      $emailbody = "Object: ".$subject."\n";
      $emailbody .= $body."\n";
      $serverName = $CDASH_SERVER_NAME;
      if(strlen($serverName) == 0)
        {
        $serverName = $_SERVER['SERVER_NAME'];
        }
      $emailbody .= "\n-CDash on ".$serverName."\n";

      if(mail("$email", $emailtitle, $emailbody,
       "From: CDash <".$CDASH_EMAIL_FROM.">\nReply-To: ".$CDASH_EMAIL_REPLY."\nX-Mailer: PHP/" . phpversion()."\nMIME-Version: 1.0" ))
        {
        add_log("email sent to: ".$email,"Project::SendEmailToAdmin");
        return;
        }
      else
        {
        add_log("cannot send email to: ".$email,"Project::SendEmailToAdmin",LOG_ERR,$this->Id);
        }
      } // end if email
    } // end SendEmailToAdmin


  function getDefaultCTestUpdateType()
    {
    switch ($this->CvsViewerType)
      {
      case 'cgit':
      case 'github':
      case 'gitorious':
      case 'gitweb':
        return "git";
      break;

      case 'websvn':
        return "svn";
      break;

      default:
        return "cvs";
      break;
      }
    }


  function getDefaultJobTemplateScript()
    {
    $ctest_script  = '# From this line down, this script may be customized'."\n";
    $ctest_script .= '# on the Clients tab of the CDash createProject page.'."\n";
    $ctest_script .= '#'."\n";
    $ctest_script .= 'if(JOB_MODULE)'."\n";
    $ctest_script .= '  set(SOURCE_NAME ${JOB_MODULE})'."\n";
    $ctest_script .= '  if(JOB_TAG)'."\n";
    $ctest_script .= '    set(SOURCE_NAME ${SOURCE_NAME}-${JOB_TAG})'."\n";
    $ctest_script .= '  endif()'."\n";
    $ctest_script .= 'else()'."\n";
    $ctest_script .= '  set(SOURCE_NAME ${PROJECT_NAME})'."\n";
    $ctest_script .= '  if(JOB_BUILDNAME_SUFFIX)'."\n";
    $ctest_script .= '    set(SOURCE_NAME ${SOURCE_NAME}-${JOB_BUILDNAME_SUFFIX})'."\n";
    $ctest_script .= '  endif()'."\n";
    $ctest_script .= 'endif()'."\n";
    $ctest_script .= "\n";
    $ctest_script .= 'set(CTEST_SOURCE_NAME ${SOURCE_NAME})'."\n";
    $ctest_script .= 'set(CTEST_BINARY_NAME ${SOURCE_NAME}-bin)'."\n";
    $ctest_script .= 'set(CTEST_DASHBOARD_ROOT "${CLIENT_BASE_DIRECTORY}")'."\n";
    $ctest_script .= 'set(CTEST_SOURCE_DIRECTORY "${CTEST_DASHBOARD_ROOT}/${CTEST_SOURCE_NAME}")'."\n";
    $ctest_script .= 'set(CTEST_BINARY_DIRECTORY "${CTEST_DASHBOARD_ROOT}/${CTEST_BINARY_NAME}")'."\n";
    $ctest_script .= 'set(CTEST_CMAKE_GENERATOR "${JOB_CMAKE_GENERATOR}")'."\n";
    $ctest_script .= 'set(CTEST_BUILD_CONFIGURATION "${JOB_BUILD_CONFIGURATION}")'."\n";
    $ctest_script .= "\n";

    // Construct the buildname
    $ctest_script .= 'set(CTEST_SITE "${CLIENT_SITE}")'."\n";
    $ctest_script .= 'set(CTEST_BUILD_NAME "${JOB_OS_NAME}-${JOB_OS_VERSION}-${JOB_OS_BITS}-${JOB_COMPILER_NAME}-${JOB_COMPILER_VERSION}")'."\n";
    $ctest_script .= 'if(JOB_BUILDNAME_SUFFIX)'."\n";
    $ctest_script .= '  set(CTEST_BUILD_NAME ${CTEST_BUILD_NAME}-${JOB_BUILDNAME_SUFFIX})'."\n";
    $ctest_script .= 'endif()'."\n";
    $ctest_script .= "\n";

    // Set the checkout command
    $repo_type = $this->getDefaultCTestUpdateType();

    if ($repo_type == 'cvs')
    {
      $ctest_script .= 'if(NOT EXISTS "${CTEST_SOURCE_DIRECTORY}")'."\n";
      $ctest_script .= '  set(CTEST_CHECKOUT_COMMAND "cvs -d ${JOB_REPOSITORY} checkout ")'."\n";
      $ctest_script .= '  if(JOB_TAG)'."\n";
      $ctest_script .= '    set(CTEST_CHECKOUT_COMMAND "${CTEST_CHECKOUT_COMMAND} -r ${JOB_TAG}")'."\n";
      $ctest_script .= '  endif()'."\n";
      $ctest_script .= '  set(CTEST_CHECKOUT_COMMAND "${CTEST_CHECKOUT_COMMAND} -d ${SOURCE_NAME}")'."\n";
      $ctest_script .= '  set(CTEST_CHECKOUT_COMMAND "${CTEST_CHECKOUT_COMMAND} ${JOB_MODULE}")'."\n";
      $ctest_script .= 'endif()'."\n";
      $ctest_script .= 'set(CTEST_UPDATE_COMMAND "cvs")'."\n";
    }

    if ($repo_type == 'git')
    {
      $ctest_script .= 'if(NOT EXISTS "${CTEST_SOURCE_DIRECTORY}")'."\n";
      $ctest_script .= '  set(CTEST_CHECKOUT_COMMAND "git clone ${JOB_REPOSITORY} ${SOURCE_NAME}")'."\n";
      $ctest_script .= 'endif()'."\n";
      $ctest_script .= 'set(CTEST_UPDATE_COMMAND "git")'."\n";
    }

    if ($repo_type == 'svn')
    {
      $ctest_script .= 'if(NOT EXISTS "${CTEST_SOURCE_DIRECTORY}")'."\n";
      $ctest_script .= '  set(CTEST_CHECKOUT_COMMAND "svn co ${JOB_REPOSITORY} ${SOURCE_NAME}")'."\n";
      $ctest_script .= 'endif()'."\n";
      $ctest_script .= 'set(CTEST_UPDATE_COMMAND "svn")'."\n";
    }

    $ctest_script .= "\n";

    // Write the initial CMakeCache.txt
    //
    $ctest_script .= 'file(WRITE "${CTEST_BINARY_DIRECTORY}/CMakeCache.txt" "${JOB_INITIAL_CACHE}")'."\n";
    $ctest_script .= "\n";

    $ctest_script .= 'ctest_start(${JOB_BUILDTYPE})'."\n";
    $ctest_script .= 'ctest_update(SOURCE ${CTEST_SOURCE_DIRECTORY})'."\n";
    $ctest_script .= 'ctest_configure(BUILD "${CTEST_BINARY_DIRECTORY}" RETURN_VALUE res)'."\n";
    $ctest_script .= 'ctest_build(BUILD "${CTEST_BINARY_DIRECTORY}" RETURN_VALUE res)'."\n";
    $ctest_script .= 'ctest_test(BUILD "${CTEST_BINARY_DIRECTORY}" RETURN_VALUE res)'."\n";
    $ctest_script .= '# The following lines are used to associate a build id with this job.'."\n";
    $ctest_script .= 'set(CTEST_DROP_SITE ${JOB_DROP_SITE})'."\n";
    $ctest_script .= 'set(CTEST_DROP_LOCATION ${JOB_DROP_LOCATION})'."\n";
    $ctest_script .= 'ctest_submit(RETURN_VALUE res)'."\n";
    $ctest_script .= "\n";
    $ctest_script .= 'message("DONE")'."\n";

    return $ctest_script;
    }

  /** Returns the total size of all uploaded files for this project */
  function GetUploadsTotalSize()
    {
    if(!$this->Id)
      {
      add_log('Id not set', 'Project::GetUploadsTotalSize', LOG_ERR);
      return false;
      }
    $totalSizeQuery = pdo_query("SELECT DISTINCT uploadfile.id, uploadfile.filesize AS size
                                 FROM build, build2uploadfile, uploadfile
                                 WHERE build.projectid=".qnum($this->Id)." AND
                                 build.id=build2uploadfile.buildid AND
                                 build2uploadfile.fileid=uploadfile.id");
    if(!$totalSizeQuery)
      {
      add_last_sql_error("Project::GetUploadsTotalSize", $this->Id);
      return false;
      }

    $totalSize = 0;
    while($result = pdo_fetch_array($totalSizeQuery))
      {
      $totalSize += $result['size'];
      }
    return $totalSize;
    }

  /**
   * Checks whether this project has exceeded its upload size quota.  If so,
   * Removes the files (starting with the oldest builds) until the total upload size
   * is <= the upload quota.
   */
  function CullUploadedFiles()
    {
    if(!$this->Id)
      {
      add_log('Id not set', 'Project::CullUploadedFiles', LOG_ERR);
      return false;
      }
    $totalUploadSize = $this->GetUploadsTotalSize();

    if($totalUploadSize > $this->UploadQuota)
      {
      require_once('cdash/common.php');
      add_log('Upload quota exceeded, removing old files', 'Project::CullUploadedFiles',
              LOG_INFO, $this->Id);

      $query = pdo_query("SELECT DISTINCT build.id AS id
                               FROM build, build2uploadfile, uploadfile
                               WHERE build.projectid=".qnum($this->Id)." AND
                               build.id=build2uploadfile.buildid AND
                               build2uploadfile.fileid=uploadfile.id
                               ORDER BY build.starttime ASC");

      while($builds_array = pdo_fetch_array($query))
        {
        // Delete the uploaded files if not shared
        $fileids = '(';
        $build2uploadfiles = pdo_query("SELECT a.fileid,count(b.fileid) AS c
                                 FROM build2uploadfile AS a LEFT JOIN build2uploadfile AS b
                                 ON (a.fileid=b.fileid AND b.buildid != ".qnum($builds_array['id']).")
                                 WHERE a.buildid = ".qnum($builds_array['id'])."
                                 GROUP BY a.fileid HAVING count(b.fileid)=0");
        while($build2uploadfile_array = pdo_fetch_array($build2uploadfiles))
          {
          $fileid = $build2uploadfile_array['fileid'];
          if($fileids != '(')
            {
            $fileids .= ',';
            }
          $fileids .= $fileid;
          $totalUploadSize -= unlink_uploaded_file($fileid);
          add_log("Removed file $fileid", 'Project::CullUploadedFiles', LOG_INFO, $this->Id);
          }

        $fileids .= ')';
        if(strlen($fileids)>2)
          {
          pdo_query("DELETE FROM uploadfile WHERE id IN ".$fileids);
          pdo_query("DELETE FROM build2uploadfile WHERE fileid IN ".$fileids);
          }

        // Stop if we get below the quota
        if($totalUploadSize <= $this->UploadQuota)
          {
          break;
          }
        }
      }
    }
}  // end class Project

?>
