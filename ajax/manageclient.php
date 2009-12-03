<?php
include ("../cdash/config.php");
require_once ("../cdash/pdo.php");
include_once ("../cdash/common.php");
include ("../models/clientsite.php");
include ("../models/clientcompiler.php");
include ("../models/clientcmake.php");
include ("../models/clientlibrary.php");
include ("../models/clienttoolkit.php");
include ("../models/clienttoolkitconfigure.php");
include ("../models/clienttoolkitversion.php");

@$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

// Find all the site that have the specific OS
$osid = $_POST['osid'];
$ClientSite = new ClientSite();
$siteids = $ClientSite->GetAllByOS($osid);

if(isset($_POST['gettoolkits']))
  {
  $ClientToolkitConfigure = new ClientToolkitConfigure();
  $configureids = $ClientToolkitConfigure->GetIdByOS($osid);
  
  foreach($configureids as $configureid)
    {
    $ClientToolkitConfigure->Id = $configureid;
    $toolkitVersionId = $ClientToolkitConfigure->GetToolkitVersionId();
    
    $ClientToolkitVersion = new ClientToolkitVersion();
    $ClientToolkitVersion->Id = $toolkitVersionId;
    $toolkitid = $ClientToolkitVersion->GetToolkitId($toolkitVersionId);
    $ClientToolkit = new ClientToolkit();
    $ClientToolkit->Id = $toolkitid;
    echo '<input name="toolkitconfiguration['.$configureid.']" type="checkbox" value="'.$configureid.'">';
    echo $ClientToolkit->GetName()." (".$ClientToolkitVersion->GetName().") : ".$ClientToolkitConfigure->GetName()."<br>";
    }
    
  }
else if(empty($_POST['compiler']))
  {
  $compileridstored = array();
  echo '<select id="select_compiler" name="compiler" onchange="changeCMake();>';
  echo '<option>Select a compiler</option>';
  foreach($siteids as $siteid)
    {
    $ClientSite->Id = $siteid;
    $compilerids = $ClientSite->GetCompilerIds();
    foreach($compilerids as $compilerid)
      {
      if(!in_array($compilerid,$compileridstored))
        {
        $compileridstored[] = $compilerid;
        $ClientCompiler = new ClientCompiler();
        $ClientCompiler->Id = $compilerid;
        echo "<option value='".$compilerid."'>".$ClientCompiler->GetName()." (".$ClientCompiler->GetVersion().")"."</option>";
        }
      }
    }
  echo "</select>";
  exit();
  }
else if(empty($_POST['cmake']))
  {
  $compilerid = $_POST['compiler'];
  
  echo '<select id="select_cmake" name="cmake" onchange="changeLibrary();>';
  echo '<option>Select a CMake version</option>';
  // Find the list of sites that have the current configuration
  $cmakeids = array(); 
  foreach($siteids as $siteid)
    {
    $ClientSite->Id = $siteid;
    $compilerids = $ClientSite->GetCompilerIds();
    
    if(in_array($compilerid,$compilerids))
      {
      if(empty($cmakeids))
        {
        $cmakeids = $ClientSite->GetCMakeIds();
        }
      else
        {
        $cmakeids = array_intersect($cmakeids,$ClientSite->GetCMakeIds());
        }        
      }
    }

  foreach($cmakeids as $cmakeid)
    {
    $ClientCMake = new ClientCMake();
    $ClientCMake->Id = $cmakeid;
    echo "<option value='".$cmakeid."'>".$ClientCMake->GetVersion()."</option>";
    }
      
  echo "</select>";
  exit();
  }
else // toolkits
  {
  // List all the libraries in the system (in the future should be tied to a systemid)
  // and compilers and operating systems
  $compilerid = $_POST['compiler'];
  $cmakeid = $_POST['cmake'];
  
  $toolkitids = array(); 
  foreach($siteids as $siteid)
    {
    $ClientSite->Id = $siteid;
    $compilerids = $ClientSite->GetCompilerIds();
    $cmakeids = $ClientSite->GetCMakeIds();
    
    if(in_array($compilerid,$compilerids) && in_array($cmakeid,$cmakeids))
      {
      if(empty($libraryids))
        {
        $libraryids = $ClientSite->GetLibraryIds();
        }
      else
        {
        $libraryids = array_intersect($libraryids,$ClientSite->GeLibraryIds());
        }        
      }
    }
  
  foreach($libraryids as $libraryid)
    {
    $ClientLibrary = new ClientLibrary();
    $ClientLibrary->Id = $libraryid;
    echo '<input name="library['.$libraryid.']" type="checkbox" value="'.$libraryid.'">';
    echo $ClientLibrary->GetName()." (".$ClientLibrary->GetVersion().")<br>";
    }
  } // end toolkits
?>
