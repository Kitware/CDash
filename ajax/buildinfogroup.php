<html>
<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $RCSfile: common.php,v $
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
include("../config.php");
include("../common.php");

$buildid = $_GET["buildid"];

$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);

// Find the project variables
$build = mysql_query("SELECT name,type,siteid,projectid,starttime FROM build WHERE id='$buildid'");
$build_array = mysql_fetch_array($build);

$buildtype = $build_array["type"];
$buildname = $build_array["name"];
$siteid = $build_array["siteid"];
$starttime = $build_array["starttime"];
$projectid = $build_array["projectid"];
$buildfailing = mysql_num_rows(mysql_query("SELECT buildid FROM builderror WHERE buildid='$buildid' AND type='0'"));
$testfailing = mysql_num_rows(mysql_query("SELECT buildid FROM test WHERE buildid='$buildid' AND status='failed'"));

if($buildfailing)
{
// Find the last build that have no error
$cleanbuild = mysql_query("SELECT starttime FROM build
                           WHERE id NOT IN 
																											      (SELECT b.id FROM build AS b, builderror AS e WHERE b.siteid='$siteid' AND b.type='$buildtype' AND b.name='$buildname' AND
																											       e.buildid=b.id AND b.projectid='$projectid' AND b.starttime<='$starttime' AND e.type='0')
																											AND siteid='$siteid' AND type='$buildtype' AND name='$buildname'
																											AND projectid='$projectid' AND starttime<='$starttime' ORDER BY starttime DESC LIMIT 1");

if(mysql_num_rows($cleanbuild)>0)
  {
  $cleanbuild_array = mysql_fetch_array($cleanbuild);														
  $datefirstbuildfailing = $cleanbuild_array["starttime"];
		}
else
  {
		// Find the first build
		$firstbuild = mysql_query("SELECT starttime FROM build
                            WHERE siteid='$siteid' AND type='$buildtype' AND name='$buildname'
																											 AND projectid='$projectid' AND starttime<='$starttime' ORDER BY starttime ASC LIMIT 1");
		$firstbuild_array = mysql_fetch_array($firstbuild);														
  $datefirstbuildfailing = $firstbuild_array["starttime"];
  }

  $buildfailingdays = round((strtotime($starttime)-strtotime($datefirstbuildfailing))/(3600*24));
} // end build failing

if($testfailing)
{
// Find the last test that have no error
$cleanbuild = mysql_query("SELECT starttime FROM build
                           WHERE id NOT IN 
																											      (SELECT b.id FROM build AS b, test AS t WHERE b.siteid='$siteid' AND b.type='$buildtype' AND b.name='$buildname' AND
																											       t.buildid=b.id AND b.projectid='$projectid' AND b.starttime<='$starttime' AND t.status='failed')
																											AND siteid='$siteid' AND type='$buildtype' AND name='$buildname'
																											AND projectid='$projectid' AND starttime<='$starttime' ORDER BY starttime DESC LIMIT 1");

echo mysql_error();

if(mysql_num_rows($cleanbuild)>0)
  {
  $cleanbuild_array = mysql_fetch_array($cleanbuild);														
  $datefirsttestfailing = $cleanbuild_array["starttime"];
		}
else
  {
		// Find the first build
		$firstbuild = mysql_query("SELECT starttime FROM build
                            WHERE siteid='$siteid' AND type='$buildtype' AND name='$buildname'
																											 AND projectid='$projectid' AND starttime<='$starttime' ORDER BY starttime ASC LIMIT 1");
		$firstbuild_array = mysql_fetch_array($firstbuild);														
  $datefirsttestfailing = $firstbuild_array["starttime"];
  }

  $testfailingdays = round((strtotime($starttime)-strtotime($datefirsttestfailing))/(3600*24));
} // end build failing					

									
?>
		<table width="100%"  border="0">
		<?php if($buildfailing)
		{
		?>
		<tr>
		<td bgcolor="#DDDDDD"><font size="2">Build has been failing since <b>
		<?php 
		echo $datefirstbuildfailing;
		if($buildfailingdays>1)
		  {
				echo " (".$buildfailingdays." days)";
		  }
		else if($buildfailingdays==1)
				{
				echo " (".$buildfailingdays." day)";
				}
		?>
		</b></font></td>
		</tr>
		<?php } // end buildfailing ?>
		
		<?php if($testfailing)
		{
		?>
		<tr>
		<td bgcolor="#DDDDDD"><font size="2">Tests have been failing since <b>
		<?php 
		echo $datefirsttestfailing;
		if($testfailingdays>1)
		  {
				echo " (".$testfailingdays." days)";
		  }
		else if($testfailingdays==1)
				{
				echo " (".$testfailingdays." day)";
				}
		?>
		</b></font></td>
		</tr>
		<?php } // end buildfailing ?>
		
		
</table>
		</form>
</html>