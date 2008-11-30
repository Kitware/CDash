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
include("config.php");
require_once("pdo.php");
include('login.php');
include("version.php");

set_time_limit(0);

// Define global arrays to avoid rewriting things we don't need
global $noteid_array;
$noteid_array = array();
global $testid_array;
$testid_array = array();
global $coveragefileid_array;
$coveragefileid_array = array();

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
if(!$db)
  {
  echo pdo_error();
  }
if(pdo_select_db("$CDASH_DB_NAME",$db) === FALSE)
  {
  echo pdo_error();
  return;
  }

function add_backup_value($file,$tag,$value)
{
  fwrite($file,"<".$tag.">".$value."</".$tag.">\n");
}

// Function to backup the users
function backup_users($file)
{
  fwrite($file,"<users>\n");
  $query = pdo_query("SELECT * from user ORDER BY id");
  while($user_array = pdo_fetch_array($query))
    {
    fwrite($file,"<user id=\"".$user_array['id']."\">\n");
    add_backup_value($file,"email",$user_array['email']);
    add_backup_value($file,"password",$user_array['password']);
    add_backup_value($file,"firstname",$user_array['firstname']);
    add_backup_value($file,"lastname",$user_array['lastname']);
    add_backup_value($file,"institution",$user_array['institution']);
    add_backup_value($file,"admin",$user_array['admin']);
    
    // Add the sites
    $site = pdo_query("SELECT siteid from site2user WHERE userid='".$user_array['id']."'");
    while($site_array = pdo_fetch_array($site))
      {
      fwrite($file,"<site id=\"".$site_array['siteid']."\"/>\n");
      }
      
    // Add the projects
    $project = pdo_query("SELECT * from user2project WHERE userid='".$user_array['id']."'");
    while($project_array = pdo_fetch_array($project))
      {
      fwrite($file,"<project id=\"".$project_array['projectid']."\">\n");
      add_backup_value($file,"role",$project_array['role']);
      add_backup_value($file,"cvslogin",$project_array['cvslogin']);
      add_backup_value($file,"emailtype",$project_array['emailtype']);
      add_backup_value($file,"emailcategory",$project_array['emailcategory']);
      fwrite($file,"</project>\n");
      }
      
    fwrite($file,"</user>\n");
    }
  fwrite($file,"</users>\n");
}

// Function to backup the images
function backup_images($dirname,$file)
{
  fwrite($file,"<images>\n");
  $query = pdo_query("SELECT * from image ORDER BY id");
  while($image_array = pdo_fetch_array($query))
    {
    fwrite($file,"<image id=\"".$image_array['id']."\">\n");
    // save the images in a different 
    $filename = $dirname."/image_".$image_array['id'];
    $image = fopen($filename,"wb");
    fwrite($image,$image_array['img']);
    fclose($image);
    add_backup_value($file,"filename","image_".$image_array['id']);
    add_backup_value($file,"extension",$image_array['extension']);
    add_backup_value($file,"checksum",$image_array['checksum']);
    fwrite($file,"</image>\n");
    }
  fwrite($file,"</images>\n");
}

// Function to backup the sites
function backup_sites($file)
{
  fwrite($file,"<sites>\n");
  $query = pdo_query("SELECT * from site ORDER BY id");
  while($site_array = pdo_fetch_array($query))
    {
    fwrite($file,"<site id=\"".$site_array['id']."\">\n");
    add_backup_value($file,"name",$site_array['name']);
    add_backup_value($file,"ip",$site_array['ip']);
    add_backup_value($file,"latitude",$site_array['latitude']);
    add_backup_value($file,"longitude",$site_array['longitude']);
    
    // Add the site information
    $siteinformation = pdo_query("SELECT * from siteinformation WHERE siteid='".$site_array['id']."'");
    while($siteinformation_array = pdo_fetch_array($siteinformation))
      {
      fwrite($file,"<information>\n");
      add_backup_value($file,"timestamp",$siteinformation_array['timestamp']);
      add_backup_value($file,"processoris64bits",$siteinformation_array['processoris64bits']);
      add_backup_value($file,"processorvendor",$siteinformation_array['processorvendor']);
      add_backup_value($file,"processorvendorid",$siteinformation_array['processorvendorid']);
      add_backup_value($file,"processorfamilyid",$siteinformation_array['processorfamilyid']);
      add_backup_value($file,"processormodelid",$siteinformation_array['processormodelid']);
      add_backup_value($file,"processorcachesize",$siteinformation_array['processorcachesize']);
      add_backup_value($file,"numberlogicalcpus",$siteinformation_array['numberlogicalcpus']);
      add_backup_value($file,"numberphysicalcpus",$siteinformation_array['numberphysicalcpus']);
      add_backup_value($file,"totalvirtualmemory",$siteinformation_array['totalvirtualmemory']);
      add_backup_value($file,"totalphysicalmemory",$siteinformation_array['totalphysicalmemory']);
      add_backup_value($file,"logicalprocessorsperphysical",$siteinformation_array['logicalprocessorsperphysical']);
      add_backup_value($file,"processorclockfrequency",$siteinformation_array['processorclockfrequency']);
      add_backup_value($file,"description",$siteinformation_array['description']);
      fwrite($file,"</information>\n");
      }
    fwrite($file,"</site>\n");
    }
  fwrite($file,"</sites>\n");
}

// Function to backup the projects
function backup_projects($file)
{
  fwrite($file,"<projects>\n");
  $query = pdo_query("SELECT * from project ORDER BY id");
  while($project_array = pdo_fetch_array($query))
    {
    fwrite($file,"<project id=\"".$project_array['id']."\">\n");
    add_backup_value($file,"name",$project_array['name']);
    add_backup_value($file,"description",$project_array['description']);
    add_backup_value($file,"homeurl",$project_array['homeurl']);
    add_backup_value($file,"cvsurl",$project_array['cvsurl']);
    add_backup_value($file,"bugtrackerurl",$project_array['bugtrackerurl']);
    add_backup_value($file,"documentationurl",$project_array['documentationurl']);
    add_backup_value($file,"imageid",$project_array['imageid']);
    add_backup_value($file,"public",$project_array['public']);
    add_backup_value($file,"coveragethreshold",$project_array['coveragethreshold']);
    add_backup_value($file,"nightlytime",$project_array['nightlytime']);
    add_backup_value($file,"googletracker",$project_array['googletracker']);
    add_backup_value($file,"emailbuildmissing",$project_array['emailbuildmissing']);
    add_backup_value($file,"emaillowcoverage",$project_array['emaillowcoverage']);
    add_backup_value($file,"emailtesttimingchanged",$project_array['emailtesttimingchanged']);
    add_backup_value($file,"emailbrokensubmission",$project_array['emailbrokensubmission']);
    add_backup_value($file,"cvsviewertype",$project_array['cvsviewertype']);
    add_backup_value($file,"testtimestd",$project_array['testtimestd']);
    add_backup_value($file,"testtimestdthreshold",$project_array['testtimestdthreshold']);
    add_backup_value($file,"showtesttime",$project_array['showtesttime']);
    add_backup_value($file,"testtimemaxstatus",$project_array['testtimemaxstatus']);
    add_backup_value($file,"emailmaxitems",$project_array['emailmaxitems']);
    add_backup_value($file,"emailmaxchars",$project_array['emailmaxchars']);

    // Add the repositories
    $repository = pdo_query("SELECT id,url from repositories,project2repositories WHERE projectid='".$project_array['id']."' AND repositoryid=id");
    while($repository_array = pdo_fetch_array($repository))
      {
      fwrite($file,"<repository id=\"".$repository_array['id']."\">".$repository_array['url']."</repository>\n");
      }
      
    // Add the groups
    $group = pdo_query("SELECT * FROM buildgroup WHERE projectid='".$project_array['id']."'");
    while($group_array = pdo_fetch_array($group))
      {
      fwrite($file,"<buildgroup id=\"".$group_array['id']."\">\n");
      add_backup_value($file,"name",$group_array['name']);
      add_backup_value($file,"projectid",$group_array['projectid']);
      add_backup_value($file,"starttime",$group_array['starttime']);
      add_backup_value($file,"endtime",$group_array['endtime']);
      add_backup_value($file,"description",$group_array['description']);
      add_backup_value($file,"summaryemail",$group_array['summaryemail']);
      
      // Add the group positions
      $groupposition = pdo_query("SELECT * FROM buildgroupposition WHERE buildgroupid='".$group_array['id']."'");
      while($groupposition_array = pdo_fetch_array($groupposition))
        {
        fwrite($file,"<position id=\"".$groupposition_array['position']."\">\n");
        add_backup_value($file,"starttime",$groupposition_array['starttime']);
        add_backup_value($file,"endtime",$groupposition_array['endtime']);
        fwrite($file,"</position>\n");
        }
      
      // Add the group rules
      $grouprule = pdo_query("SELECT * FROM build2grouprule WHERE groupid='".$group_array['id']."'");
      while($grouprule_array = pdo_fetch_array($grouprule))
        {
        fwrite($file,"<rule>\n");
        add_backup_value($file,"buildtype",$grouprule_array['buildtype']);
        add_backup_value($file,"buildname",$grouprule_array['buildname']);
        add_backup_value($file,"siteid",$grouprule_array['siteid']);
        add_backup_value($file,"expected",$grouprule_array['expected']);
        add_backup_value($file,"starttime",$grouprule_array['starttime']);
        add_backup_value($file,"endtime",$grouprule_array['endtime']);      
        fwrite($file,"</rule>\n");
        }
      fwrite($file,"</buildgroup>\n");
      }
      
    // Add the daily updates
    $dailyupdate = pdo_query("SELECT * from dailyupdate WHERE projectid='".$project_array['id']."'");
    while($dailyupdate_array = pdo_fetch_array($dailyupdate))
      {
      fwrite($file,"<dailyupdate id=\"".$dailyupdate_array['id']."\">\n");
      add_backup_value($file,"date",$dailyupdate_array['date']);
      add_backup_value($file,"command",$dailyupdate_array['command']);
      add_backup_value($file,"type",$dailyupdate_array['type']);
      add_backup_value($file,"status",$dailyupdate_array['status']);
      
      $dailyupdatefile = pdo_query("SELECT * from dailyupdatefile WHERE dailyupdateid='".$dailyupdate_array['id']."'");
      while($dailyupdatefile_array = pdo_fetch_array($dailyupdatefile))
        {
        fwrite($file,"  <dailyupdatefile>\n");
        add_backup_value($file,"filename",$dailyupdate_array['filename']);
        add_backup_value($file,"checkindate",$dailyupdate_array['checkindate']);
        add_backup_value($file,"author",$dailyupdate_array['author']);
        add_backup_value($file,"log",$dailyupdate_array['log']);
        add_backup_value($file,"revision",$dailyupdate_array['revision']);
        add_backup_value($file,"priorrevision",$dailyupdate_array['priorrevision']);
        fwrite($file,"  </dailyupdatefile>\n");
        }
      fwrite($file,"</dailyupdate>\n");
      }
    fwrite($file,"</project>\n");
    }
  fwrite($file,"</projects>\n");
}


// Function to backup the projects
function backup_build($dirname,$file,$build_array)
{
  global $testid_array;
  global $noteid_array;
  global $coveragefileid_array;
  
  fwrite($file,"<build id=\"".$build_array['id']."\">\n");
  add_backup_value($file,"siteid",$build_array['siteid']);
  add_backup_value($file,"projectid",$build_array['projectid']);
  add_backup_value($file,"stamp",$build_array['stamp']);
  add_backup_value($file,"name",$build_array['name']);
  add_backup_value($file,"type",$build_array['type']);
  add_backup_value($file,"generator",$build_array['generator']);
  add_backup_value($file,"starttime",$build_array['starttime']);
  add_backup_value($file,"endtime",$build_array['endtime']);
  add_backup_value($file,"submittime",$build_array['submittime']);
  add_backup_value($file,"command",$build_array['command']);
  add_backup_value($file,"log",$build_array['log']);
  
  // Add the current group
  $build2group = pdo_query("SELECT groupid from build2group WHERE buildid='".$build_array['id']."'");
  while($build2group_array = pdo_fetch_array($build2group))
    {
    add_backup_value($file,"groupid",$build2group_array['groupid']);
    }
  
  // Add the build note
  $buildnote = pdo_query("SELECT * from buildnote WHERE buildid='".$build_array['id']."'");
  while($buildnote_array = pdo_fetch_array($buildnote))
    {
    fwrite($file,"  <buildnote>\n");
    add_backup_value($file,"userid",$buildnote_array['userid']);
    add_backup_value($file,"note",$buildnote_array['note']);
    add_backup_value($file,"timestamp",$buildnote_array['timestamp']);
    add_backup_value($file,"status",$buildnote_array['status']);
    fwrite($file,"  </buildnote>\n");
    }
  
  // Deal with the notes for the build
  $build2note = pdo_query("SELECT * from build2note WHERE buildid='".$build_array['id']."'");
  while($build2note_array = pdo_fetch_array($build2note))
    {
    if(!in_array($build2note['noteid'],$noteid_array))
      {
      $noteid_array[] = $build2note['noteid'];
      fwrite($file,"  <note id=\"".$build2note['noteid']."\">\n");
      add_backup_value($file,"time",$build2note['time']);
      $note = pdo_query("SELECT * from note WHERE id='".$build2note['noteid']."'");
      while($note_array = pdo_fetch_array($note))
        {
        add_backup_value($file,"text",$note_array['text']);
        add_backup_value($file,"name",$note_array['name']);
        add_backup_value($file,"crc32",$note_array['crc32']);
        }
      fwrite($file,"  </note>\n");
      }
    }
  
  // Updates
  $buildupdate = pdo_query("SELECT * from buildupdate WHERE buildid='".$build_array['id']."'");
  while($buildupdate_array = pdo_fetch_array($buildupdate))
    {
    fwrite($file,"<update>\n");
    add_backup_value($file,"starttime",$buildupdate_array['starttime']);
    add_backup_value($file,"endtime",$buildupdate_array['endtime']);
    add_backup_value($file,"command",$buildupdate_array['command']);
    add_backup_value($file,"type",$buildupdate_array['type']);
    add_backup_value($file,"status",$buildupdate_array['status']);  
    
    $updatefile = pdo_query("SELECT * from updatefile WHERE buildid='".$build_array['id']."'");
    while($updatefile_array = pdo_fetch_array($updatefile))
      {
      fwrite($file,"<file>\n");
      add_backup_value($file,"filename",$updatefile_array['filename']);
      add_backup_value($file,"checkindate",$updatefile_array['checkindate']);
      add_backup_value($file,"author",$updatefile_array['author']);
      add_backup_value($file,"email",$updatefile_array['email']);
      add_backup_value($file,"log",$updatefile_array['log']);  
      add_backup_value($file,"revision",$updatefile_array['revision']);  
      add_backup_value($file,"priorrevision",$updatefile_array['priorrevision']);  
      fwrite($file,"</file>\n");
      }
    
    fwrite($file,"</update>\n");
    }
  
  // Configure
  $buildconfigure = pdo_query("SELECT * from configure WHERE buildid='".$build_array['id']."'");
  while($buildconfigure_array = pdo_fetch_array($buildconfigure))
    {
    fwrite($file,"<configure>\n");
    add_backup_value($file,"starttime",$buildconfigure_array['starttime']);
    add_backup_value($file,"endtime",$buildconfigure_array['endtime']);
    add_backup_value($file,"command",$buildconfigure_array['command']);
    add_backup_value($file,"log",$buildconfigure_array['log']);
    add_backup_value($file,"status",$buildconfigure_array['status']);
    $buildconfigureerror = pdo_query("SELECT * from configureerror WHERE buildid='".$build_array['id']."'");
    while($buildconfigureerror_array = pdo_fetch_array($buildconfigureerror))
      {
      fwrite($file,"<error>\n");
      add_backup_value($file,"type",$buildconfigureerror_array['type']);
      add_backup_value($file,"text",$buildconfigureerror_array['text']);
      fwrite($file,"</error>\n");
      }
    $buildconfigureerrordiff = pdo_query("SELECT * from configureerrordiff WHERE buildid='".$build_array['id']."'");
    while($buildconfigureerrordiff_array = pdo_fetch_array($buildconfigureerrordiff))
      {
      fwrite($file,"<errordiff type=\"".$buildconfigureerrordiff_array['type']."\">".$buildconfigureerrordiff_array['difference']."</errordiff>\n");
      }
    fwrite($file,"</configure>\n");
    }

  // Errors and Warnings
  $builderror = pdo_query("SELECT * from builderror WHERE buildid='".$build_array['id']."'");
  while($builderror_array = pdo_fetch_array($builderror))
    {
    fwrite($file,"<builderror>\n");
    add_backup_value($file,"type",$builderror_array['type']);
    add_backup_value($file,"logline",$builderror_array['logline']);
    add_backup_value($file,"text",$builderror_array['text']);
    add_backup_value($file,"sourcefile",$builderror_array['sourcefile']);
    add_backup_value($file,"sourceline",$builderror_array['sourceline']);
    add_backup_value($file,"precontext",$builderror_array['precontext']);
    add_backup_value($file,"postcontext",$builderror_array['postcontext']);
    add_backup_value($file,"repeatcount",$builderror_array['repeatcount']);
    fwrite($file,"</builderror>\n");
    }
    
  $builderrordiff = pdo_query("SELECT * from builderrordiff WHERE buildid='".$build_array['id']."'");
  while($builderrordiff_array = pdo_fetch_array($builderrordiff))
    {
    fwrite($file,"<builderrordiff type=\"".$builderrordiff_array['type']."\">".$builderrordiff_array['difference']."</builderrordiff>\n");
    }
  
  // Tests
  $build2test = pdo_query("SELECT * from build2test WHERE buildid='".$build_array['id']."'");
  while($build2test_array = pdo_fetch_array($build2test))
    {
    fwrite($file,"<buildtest>\n");
    add_backup_value($file,"testid",$build2test_array['testid']);
    add_backup_value($file,"status",$build2test_array['status']);
    add_backup_value($file,"time",$build2test_array['time']);
    add_backup_value($file,"timemean",$build2test_array['timemean']);
    add_backup_value($file,"timestd",$build2test_array['timestd']);
    add_backup_value($file,"timestatus",$build2test_array['timestatus']);
  
    if(!in_array($build2test_array['testid'],$testid_array))
      {
      $testid_array[] = $build2test_array['testid'];
      fwrite($file,"  <test id=\"".$build2test_array['testid']."\">\n");
      
      $test = pdo_query("SELECT * from test WHERE id='".$build2test_array['testid']."'");
      $test_array = pdo_fetch_array($test);
      add_backup_value($file,"crc32",$test_array['crc32']);
      add_backup_value($file,"name",$test_array['name']);
      add_backup_value($file,"path",$test_array['path']);
      add_backup_value($file,"command",$test_array['command']);
      add_backup_value($file,"details",htmlentities($test_array['details']));
      add_backup_value($file,"output",htmlentities($test_array['output']));
      
      // Add test images
      $test2image = pdo_query("SELECT * from test2image WHERE testid='".$build2test_array['testid']."'");
      while($test2image_array = pdo_fetch_array($test2image))
        {
        fwrite($file,"<image id=\"".$test2image_array['imgid']."\">".$test2image_array['role']."</image>\n");
        }
        
      // Add test measurement
      $testmeasurement = pdo_query("SELECT * from testmeasurement WHERE testid='".$build2test_array['testid']."'");
      while($testmeasurement_array = pdo_fetch_array($testmeasurement))
        {
        fwrite($file,"<measurement>\n");
        add_backup_value($file,"name",$testmeasurement_array['name']);
        add_backup_value($file,"type",$testmeasurement_array['type']);
        add_backup_value($file,"value",$testmeasurement_array['value']);
        fwrite($file,"</measurement>\n");
        }
        
      fwrite($file,"  </test>\n");
      }

    fwrite($file,"</buildtest>\n");
    }
        
  $testdiff = pdo_query("SELECT * from testdiff WHERE buildid='".$build_array['id']."'");
  while($testdiff_array = pdo_fetch_array($testdiff))
    {
    fwrite($file,"<testdiff type=\"".$testdiff_array['type']."\">".$testdiff_array['difference']."</testdiff>\n");
    }
 
  // Add coverage summary
  $coveragesummary = pdo_query("SELECT * FROM coveragesummary WHERE buildid='".$build_array['id']."'");
  while($coveragesummary_array = pdo_fetch_array($coveragesummary))
    {
    fwrite($file,"<coveragesummary>\n");
    add_backup_value($file,"loctested",$coveragesummary_array['loctested']);
    add_backup_value($file,"locuntested",$coveragesummary_array['locuntested']);
    fwrite($file,"</coveragesummary>\n");
    }
  
  // Add coverage summary diff
  $coveragesummarydiff = pdo_query("SELECT * FROM coveragesummarydiff WHERE buildid='".$build_array['id']."'");
  while($coveragesummarydiff_array = pdo_fetch_array($coveragesummarydiff))
    {
    fwrite($file,"<coveragesummarydiff>\n");
    add_backup_value($file,"loctested",$coveragesummarydiff_array['loctested']);
    add_backup_value($file,"locuntested",$coveragesummarydiff_array['locuntested']);
    fwrite($file,"</coveragesummarydiff>\n");
    }
    
  // Add coverage
  $coverage = pdo_query("SELECT * FROM coverage WHERE buildid='".$build_array['id']."'");
  while($coverage_array = pdo_fetch_array($coverage))
    {
    fwrite($file,"<coverage>\n");
    add_backup_value($file,"fileid",$coverage_array['fileid']);
    add_backup_value($file,"covered",$coverage_array['covered']);
    add_backup_value($file,"loctested",$coverage_array['loctested']);
    add_backup_value($file,"locuntested",$coverage_array['locuntested']);
    add_backup_value($file,"branchstested",$coverage_array['branchstested']);
    add_backup_value($file,"branchsuntested",$coverage_array['branchsuntested']);
    add_backup_value($file,"functionstested",$coverage_array['functionstested']);
    add_backup_value($file,"functionsuntested",$coverage_array['functionsuntested']);

    
    // Add the file if doesn't exists
    if(!in_array($coverage_array['fileid'],$coveragefileid_array))
      {
      $coveragefileid_array[] = $coverage_array['fileid'];
      fwrite($file,"<coveragefile id=\"".$coverage_array['fileid']."\">\n");

      $coveragefile = pdo_query("SELECT * FROM coveragefile WHERE id='".$coverage_array['fileid']."'");
      $coveragefile_array = pdo_fetch_array($coveragefile);
      
      $filename = $dirname."/coveragefile_".$coveragefile['id'];
      $file = fopen($filename,"wb");
      fwrite($file,$coveragefile['file']);
      fclose($file);
      
      add_backup_value($file,"filename",$filename);
      add_backup_value($file,"fullpath",$coverage_array['fullpath']);
      add_backup_value($file,"crc32",$coverage_array['crc32']);    
      fwrite($file,"</coveragefile>\n");
      }
      
    $coveragefilelog = pdo_query("SELECT * FROM coveragefilelog WHERE buildid='".$build_array['id']."'");
    while($coveragefilelog_array = pdo_fetch_array($coveragefilelog))
      {
      fwrite($file,"<coveragefilelog>\n");
      add_backup_value($file,"fileid",$coveragefilelog_array['fileid']);
      add_backup_value($file,"line",$coveragefilelog_array['line']);
      add_backup_value($file,"code",$coveragefilelog_array['code']);
      fwrite($file,"</coveragefilelog>\n");
      }
   
    fwrite($file,"</coverage>\n");
    }

  // Add dynamic analysis 
  $dynamicanalysis = pdo_query("SELECT * FROM dynamicanalysis WHERE buildid='".$build_array['id']."'");
  while($dynamicanalysis_array = pdo_fetch_array($dynamicanalysis))
    {
    fwrite($file,"<dynamicanalysis id=\"".$dynamicanalysis_array['id']."\">\n");
    add_backup_value($file,"status",$dynamicanalysis_array['status']);
    add_backup_value($file,"checker",$dynamicanalysis_array['checker']);
    add_backup_value($file,"name",$dynamicanalysis_array['name']);
    add_backup_value($file,"path",$dynamicanalysis_array['path']);
    add_backup_value($file,"fullcommandline",$dynamicanalysis_array['fullcommandline']);
    add_backup_value($file,"log",$dynamicanalysis_array['log']);
    
    $dynamicanalysisdefect = pdo_query("SELECT * FROM dynamicanalysisdefect WHERE dynamicanalysisid='".$dynamicanalysis_array['id']."'");
    while($dynamicanalysisdefect_array = pdo_fetch_array($dynamicanalysisdefect))
      {
      fwrite($file,"<defect type=\"".$dynamicanalysisdefect_array['type']."\">".$dynamicanalysisdefect_array['value']."</defect>\n");
      }
    fwrite($file,"</dynamicanalysis>\n");
    }

  fwrite($file,"</build>\n");
}
checkUserPolicy(@$_SESSION['cdash']['loginid'],0); // only admin

$xml = "<cdash>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";

//get date info here
@$dayTo = $_POST["dayFrom"];
if(!isset($dayTo))
  { 
  $dayFrom = 1;
  $monthFrom = 1;
  $yearFrom = 2000;
  $dayTo = date('d');
  $yearTo = date('Y');
  $monthTo = date('m');
  }
else
  {
  $dayFrom = $_POST["dayFrom"];
  $monthFrom = $_POST["monthFrom"];
  $yearFrom = $_POST["yearFrom"];
  $dayTo = $_POST["dayTo"];
  $monthTo = $_POST["monthTo"];
  $yearTo = $_POST["yearTo"];
  } 
  
$xml = "<cdash>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";
$xml .= "<title>CDash - Import</title>";
$xml .= "<menutitle>CDash</menutitle>";
$xml .= "<menusubtitle>Export</menusubtitle>";
$xml .= "<backurl>manageBackup.php</backurl>";

$xml .= "<dayFrom>".$dayFrom."</dayFrom>";
$xml .= "<monthFrom>".$monthFrom."</monthFrom>";
$xml .= "<yearFrom>".$yearFrom."</yearFrom>";
$xml .= "<dayTo>".$dayTo."</dayTo>";
$xml .= "<monthTo>".$monthTo."</monthTo>";
$xml .= "<yearTo>".$yearTo."</yearTo>";

$xml .= "</cdash>";
@$submit = $_POST["Submit"];

if(isset($submit))
  {
  @mkdir($CDASH_BACKUP_DIRECTORY."/database");
  
  $current=0;
  
  $filenamebase = $CDASH_BACKUP_DIRECTORY."/database/backup_";
  $filename = $filenamebase.str_pad($current,3,"0",STR_PAD_LEFT).".xml";
  
  // We always export the users and the project
  $h = fopen($filename,"wb");
  if(!$h)
    {
    echo "Cannot write backup file";
    return;
    }
  
  fwrite($h,'<?xml version="1.0" encoding="utf-8"?>');
  fwrite($h,'<cdash>');
  
  // Write these always
  backup_projects($h);
  backup_sites($h);
  backup_images(dirname($filename),$h);
  backup_users($h);
  
  $starttime =  gmdate(FMT_DATETIME,mktime(0,0,0,$monthFrom,$dayFrom,$yearFrom));
  $endtime = gmdate(FMT_DATETIME,mktime(0,0,0,$monthTo,$dayTo,$yearTo));
  
  // Write the builds
  fwrite($h,"<builds>\n");
  
  $currenttime = 0;
  
  $build = pdo_query("SELECT * from build WHERE starttime>'$starttime' AND starttime<'$endtime' ORDER BY id");
  while($build_array = pdo_fetch_array($build))
    {
    if($currenttime == 0)
      {
      $currenttime = strtotime($build_array['starttime']);
      }
      
    // Split per week
    if(strtotime($build_array['starttime']) > $currenttime+3600*7*24)
      {
      fwrite($h,"</builds>\n");
      fwrite($h,'</cdash>');
      fclose($h);
      $current++;
      $filename = $filenamebase.str_pad($current,3,"0",STR_PAD_LEFT).".xml";
      $h = fopen($filename,"wb");
      if(!$h)
        {
        echo "Cannot write backup file";
        return;
        }
      
      fwrite($h,'<?xml version="1.0" encoding="utf-8"?>');
      fwrite($h,'<cdash>');
      fwrite($h,"<builds>\n");
      $currenttime = strtotime($build_array['starttime']);
      }
    
    backup_build(dirname($filename),$h,$build_array);
    }
  fwrite($h,"</builds>\n");
  
  fwrite($h,'</cdash>');
  fclose($h);
  echo "DONE";
  }
  
generate_XSLT($xml,"backup");
?>
