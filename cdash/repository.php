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
require_once("cdash/config.php");
require_once("cdash/log.php");

function get_previous_revision($revision)
{
  // Split revision into components based on any "." separators:
  //
  $revcmps = explode(".", $revision);
  $n = count($revcmps);

  // svn style "single-component" revision number, just subtract one:
  //
  if ($n === 1)
  {
    return $revcmps[0] - 1;
  }

  // cvs style "multi-component" revision number, subtract one from last
  // component -- if result is 0, chop off last two components -- finally,
  // re-assemble $n components for previous_revision:
  //
  $revcmps[$n-1] = $revcmps[$n-1] - 1;
  if ($revcmps[$n-1] === 0)
  {
    $n = $n - 2;
  }

  if ($n < 2)
  {
    // Can't reassemble less than 2 components; use original revision
    // as previous...
    //
    $previous_revision = $revision;
  }
  else
  {
    // Reassemble components into previous_revision:
    //
    $previous_revision = $revcmps[0];
    $i = 1;
    while ($i<$n)
    {
      $previous_revision = $previous_revision . "." . $revcmps[$i];
      $i = $i + 1;
    }
  }
  return $previous_revision;
}


/** Return the ViewCVS URL */
function get_viewcvs_diff_url($projecturl, $directory, $file, $revision)
{
  // The project's viewcvs URL is expected to contain "?root=projectname"
  // Split it at the "?"
  //
  if(strlen($projecturl)==0)
    {
    return "";
    }

  $cmps = explode("?", $projecturl);

  // If $cmps[1] starts with "root=" and the $directory value starts
  // with "whatever comes after that" then remove that bit from directory:
  //
  @$npos = strpos($cmps[1], "root=");
  if ($npos !== FALSE && $npos === 0)
    {
    $rootdir = substr($cmps[1], 5);

    $npos = strpos($directory, $rootdir);
    if ($npos !== FALSE && $npos === 0)
      {
      $directory = substr($directory, strlen($rootdir));
      $npos = strpos($directory, "/");
      if ($npos !== FALSE && $npos === 0)
        {
        if (1 === strlen($directory))
          {
          $directory = "";
          }
        else
          {
          $directory = substr($directory, 1);
          }
        }
      }
    }


  if (strlen($directory)>0)
    {
    $dircmp = $directory . "/";
    }
  else
    {
    $dircmp = "";
    }


  // If we have a revision
  if($revision != '')
    {
    $prev_revision = get_previous_revision($revision);
    if (0 === strcmp($revision, $prev_revision))
      {
      $revcmp = "&rev=" . $revision . "&view=markup";
      $diff_url = $cmps[0] . $dircmp . $file . "?" . $cmps[1] . $revcmp;
      }
    else
      {
      // different : view the diff of r1 and r2:
      $revcmp = "&r1=" . $prev_revision . "&r2=" . $revision;
      $diff_url = $cmps[0] . $dircmp . $file . ".diff?" . $cmps[1] . $revcmp;
      }
    }
  else
    {
    @$diff_url = $cmps[0] . $dircmp . $file ."?".$cmps[1];
    }

  return make_cdash_url($diff_url);
}


/** Return the Trac URL */
function get_trac_diff_url($projecturl, $directory, $file, $revision)
{
  $filename = $file;
  if($directory != "")
    {
    $filename = $directory."/".$file;
    }

  if($revision != '')
    {
    $diff_url = $projecturl."/changeset/trunk/".$revision."/".$filename;
    }
  else // no revision
    {
    $diff_url = $projecturl."/browser/".$filename;
    }
  return make_cdash_url($diff_url);
}

/** Return the Mercurial URL */
function get_hgweb_diff_url($projecturl, $directory, $file, $revision)
{
  if($revision != '')
    {
    $diff_url = $projecturl."/diff/".$revision."/".($directory ? ("/".$directory) : "")."/".$file;
    }
  else
    {
    $diff_url = $projecturl."/file/tip/".($directory ? ("/".$directory) : "")."/".$file;
    }
  return make_cdash_url($diff_url);
}

/** Return the Fisheye URL */
function get_fisheye_diff_url($projecturl, $directory, $file, $revision)
{
  $diff_url = rtrim($projecturl, '/').($directory ? ("/".$directory) : "")."/".$file;

  if($revision != '')
    {
    $prev_revision = get_previous_revision($revision);
    if($prev_revision != $revision)
      {
      $diff_url .= "?r1=".$prev_revision."&r2=".$revision;
      }
    else
      {
      $diff_url .= "?r=".$revision;
      }
    }
  return make_cdash_url($diff_url);
}

/** Return the CVSTrac URL */
function get_cvstrac_diff_url($projecturl, $directory, $file, $revision)
{
  if($revision != '')
    {
    $prev_revision = get_previous_revision($revision);
    if($prev_revision != $revision)
      {
      $diff_url = $projecturl."/filediff?f=".($directory ? ($directory) : "")."/".$file;
      $diff_url .= "&v1=".$prev_revision."&v2=".$revision;
      }
    else
      {
      $diff_url = $projecturl."/fileview?f=".($directory ? ($directory) : "")."/".$file;
      $diff_url .= "&v=".$revision;
      }
    }
  else
    {
    $diff_url = $projecturl."/rlog?f=".($directory ? ($directory) : "")."/".$file;
    }

  return make_cdash_url($diff_url);
}


/** Return the ViewVC URL */
function get_viewvc_diff_url($projecturl, $directory, $file, $revision)
{
  if($revision != '')
    {
    $prev_revision = get_previous_revision($revision);
    if($prev_revision != $revision) //diff
      {
      $diff_url = $projecturl."/?action=browse&path=".($directory ? ($directory) : "")."/".$file;
      $diff_url .= "&r1=".$prev_revision."&r2=".$revision;
      }
    else //view
      {
      $diff_url = $projecturl."/?action=browse&path=".($directory ? ($directory) : "")."/".$file;
      $diff_url .= "&revision=".$revision."&view=markup";
      }
    }
  else //log
    {
    $diff_url = $projecturl."/?action=browse&path=".($directory ? ($directory) : "")."/".$file."&view=log";
    }

  return make_cdash_url($diff_url);
}

/** Return the viewVC 1-1 url */
function get_viewvc_1_1_diff_url($projecturl, $directory, $file, $revision)
{
  if($revision != '')
    {
    $prev_revision = get_previous_revision($revision);
    if($prev_revision != $revision) //diff
      {
      $diff_url = $projecturl."/".($directory ? ($directory) : "")."/".$file;
      $diff_url .= "?r1=".$prev_revision."&r2=".$revision;
      }
    else //view
      {
      $diff_url = $projecturl."/".($directory ? ($directory) : "")."/".$file;
      $diff_url .= "?revision=".$revision."&view=markup";
      }
    }
  else //log
    {
    $diff_url = $projecturl."/".($directory ? ($directory) : "")."/".$file."?view=log";
    }

  return make_cdash_url($diff_url);
}

/** Return the WebSVN URL */
function get_websvn_diff_url($projecturl, $directory, $file, $revision)
{
  $repname = "";
  $root = "";
  // find the repository name
  $pos_repname = strpos($projecturl,"repname=");
  if($pos_repname !== false)
    {
    $pos_repname_end = strpos($projecturl,"&",$pos_repname+1);
    if($pos_repname_end !== false)
      {
      $repname = substr($projecturl,$pos_repname,$pos_repname_end-$pos_repname);
      }
    else
      {
      $repname = substr($projecturl,$pos_repname);
      }
    }

  // find the root name
  $pos_root = strpos($projecturl,"path=");
  if($pos_root !== false)
    {
    $pos_root_end = strpos($projecturl,"&",$pos_root+1);
    if($pos_root_end !== false)
      {
      $root = substr($projecturl,$pos_root+5,$pos_root_end-$pos_root-5);
      }
    else
      {
      $root = substr($projecturl,$pos_root+5);
      }
    }


  // find the project url
  $pos_dotphp = strpos($projecturl,".php?");
  if($pos_dotphp !== false)
    {
    $projecturl = substr($projecturl,0,$pos_dotphp);
    $pos_slash = strrpos($projecturl,"/");
    $projecturl = substr($projecturl,0,$pos_slash);
    }

  if($revision != '')
    {
    $prev_revision = get_previous_revision($revision);
    if($prev_revision != $revision) //diff
      {
      $diff_url = $projecturl."/diff.php?".$repname."&path=".$root.($directory ? "/".($directory) : "")."/".$file;
      $diff_url .= "&rev=".$revision."&sc=1";
      }
    else //view
      {
      $diff_url = $projecturl."/filedetails.php?".$repname."&path=".$root.($directory ? "/".($directory) : "")."/".$file;
      $diff_url .= "&rev=".$revision;
      }
    }
  else //log
    {
    $diff_url = $projecturl."/log.php?".$repname."&path=".$root.($directory ? "/".($directory) : "")."/".$file;
    $diff_url .= "&rev=0&sc=0&isdir=0";
    }

  return make_cdash_url($diff_url);
}

/** Return the SourceForge Allura URL */
function get_allura_diff_url($projecturl, $directory, $file, $revision)
{
  if($revision != '')
    {
    $prev_revision = get_previous_revision($revision);
    if($prev_revision != $revision) //diff
      {
      $diff_url = $projecturl."/".$revision."/tree/trunk/".$directory."/".$file."?diff=".$prev_revision;
      }
    else //view
      {
      $diff_url = $projecturl."/".$revision."/tree/trunk/";
      }
    }
  else //log
    {
    $diff_url = $projecturl."/".$revision;
    }

  return make_cdash_url($diff_url);
}


/** Return the Loggerhead URL */
function get_loggerhead_diff_url($projecturl, $directory, $file, $revision)
{
  if($revision != '')
    {
    $diff_url = $projecturl."/revision/".$revision.($directory ? ("/".$directory) : "")."/".$file;
    }
  else
    {
    $diff_url = $projecturl."/changes/head:/".($directory ? ($directory) : "")."/".$file;
    }

  return make_cdash_url($diff_url);
}

/** Return the GitWeb diff URL */
function get_gitweb_diff_url($projecturl, $directory, $file, $revision)
{
  if($revision != '')
    {
    $diff_url = $projecturl . ";a=commitdiff;h=" . $revision;
    }
  else if ($file != '')
    {
    $diff_url = $projecturl . ";a=blob;f=";
    if ($directory != '')
      {
      $diff_url .= $directory . "/";
      }
    $diff_url .= $file;
    }
  else
    {
    return '';
    }

  return make_cdash_url($diff_url);
}

/** Return the GitWeb2 diff URL */
function get_gitweb2_diff_url($projecturl, $directory, $file, $revision)
{
  if($revision != '')
    {
    $diff_url = $projecturl . "/commitdiff/" . $revision;
    }
  else if ($file != '')
    {
    $diff_url = $projecturl . "/blob/";
    if ($directory != '')
      {
      $diff_url .= $directory . "/";
      }
    $diff_url .= $file;
    }
  else
    {
    return '';
    }

  return make_cdash_url($diff_url);
}

/** Return the Gitorious/GitHub diff URL */
function get_gitoriousish_diff_url($projecturl, $directory, $file, $revision, $blobs, $branch='master')
{
  if ($revision != '')
    {
    $diff_url = $projecturl . "/commit/" . $revision;
    }
  else if ($file != '')
    {
    $diff_url = $projecturl . "/" . $blobs . "/" . $branch . "/";
    if ($directory != '')
      {
      $diff_url .= $directory . "/";
      }
    $diff_url .= $file;
    }
  else
    {
    return '';
    }

  return make_cdash_url($diff_url);
}

/** Return the Gitorious diff URL */
function get_gitorious_diff_url($projecturl, $directory, $file, $revision)
{
  // Gitorious uses 'blobs' or 'trees' (plural)
  return get_gitoriousish_diff_url($projecturl, $directory, $file, $revision, 'blobs');
}

/** Return the GitHub diff URL */
function get_github_diff_url($projecturl, $directory, $file, $revision)
{
  // GitHub uses 'blob' or 'tree' (singular, no s)
  return get_gitoriousish_diff_url($projecturl, $directory, $file, $revision, 'blob');
}

/** Return the cgit diff URL */
function get_cgit_diff_url($projecturl, $directory, $file, $revision)
{
  $diff_url = $projecturl . "/diff/";
  if($directory)
  {
    $diff_url .= $directory . "/";
  }
  $diff_url .= $file . "?id=" . $revision;
  return make_cdash_url($diff_url);
}

/** Return the Redmine diff URL */
function get_redmine_diff_url($projecturl, $directory, $file, $revision)
{
  $diff_url = $projecturl . "/revisions/" . $revision . "/diff/";
  if($directory)
    {
    $diff_url .= $directory . "/";
    }
  $diff_url .= $file;
  return make_cdash_url($diff_url);
}

/** Get the diff url based on the type of viewer */
function get_diff_url($projectid, $projecturl, $directory, $file, $revision='')
{
  if(!is_numeric($projectid))
    {
    return;
    }

  $project = pdo_query("SELECT cvsviewertype FROM project WHERE id='$projectid'");
  $project_array = pdo_fetch_array($project);

  $cvsviewertype = strtolower($project_array["cvsviewertype"]);
  $difffonction = 'get_'.$cvsviewertype.'_diff_url';

  if(function_exists($difffonction))
    {
    return $difffonction($projecturl, $directory, $file, $revision);
    }
  else // default is viewcvs
    {
    return get_viewcvs_diff_url($projecturl, $directory, $file, $revision);
    }
}

/** Return the ViewCVS URL */
function get_viewcvs_revision_url($projecturl, $revision, $priorrevision)
{
  $revision_url = $projecturl."&rev=".$revision;
  return make_cdash_url($revision_url);
}

/** Return the Trac URL */
function get_trac_revision_url($projecturl, $revision, $priorrevision)
{
  $revision_url = $projecturl."/changeset/".$revision;
  return make_cdash_url($revision_url);
}

/** Return the Mercurial URL */
function get_hgweb_revision_url($projecturl, $revision, $priorrevision)
{
  $revision_url = $projecturl."/rev/".$revision;
  return make_cdash_url($revision_url);
}

/** Return the Fisheye URL */
function get_fisheye_revision_url($projecturl, $revision, $priorrevision)
{
  $revision_url = $projecturl."?r=".$revision;;
  return make_cdash_url($revision_url);
}

/** Return the CVSTrac URL */
function get_cvstrac_revision_url($projecturl, $revision, $priorrevision)
{
  $revision_url = ""; // not implemented
  return make_cdash_url($revision_url);
}

/** Return the ViewVC URL */
function get_viewvc_revision_url($projecturl, $revision, $priorrevision)
{
  $revision_url = $projecturl."?view=rev&revision=".$revision;
  return make_cdash_url($diff_url);
}

/** Return the viewVC 1-1 url */
function get_viewvc_1_1_revision_url($projecturl, $revision, $priorrevision)
{
  $revision_url = $projecturl."?view=rev&revision=".$revision;
  return make_cdash_url($revision_url);
}

/** Return the WebSVN URL */
function get_websvn_revision_url($projecturl, $revision, $priorrevision)
{
  $revision_url = $projecturl."?view=revision&revision=".$revision;
  return make_cdash_url($revision_url);
}

/** Return the SourceForge Allura URL */
function get_allura_revision_url($projecturl, $revision, $priorrevision)
{
  $revision_url = $projecturl."/".$revision;
  return make_cdash_url($revision_url);
}

/** Return the Loggerhead URL */
function get_loggerhead_revision_url($projecturl, $revision, $priorrevision)
{
  $revision_url = ""; // not implemented
  return make_cdash_url($revision_url);
}

/** Return the GitWeb revision URL */
function get_gitweb_revision_url($projecturl, $revision, $priorrevision)
{
  $revision_url = $projecturl . ";a=shortlog;h=" . $revision;
  return make_cdash_url($revision_url);
}

/** Return the GitWeb revision URL */
function get_gitweb2_revision_url($projecturl, $revision, $priorrevision)
{
  $revision_url = $projecturl . "/shortlog/" . $revision;
  return make_cdash_url($revision_url);
}

/** Return the Gitorious revision URL */
function get_gitorious_revision_url($projecturl, $revision, $priorrevision)
{
  $revision_url = $projecturl . "/commits/";
  if ($priorrevision)
  {
    $revision_url .= $priorrevision . "..";
  }
  $revision_url .= $revision;
  return make_cdash_url($revision_url);
}

/** Return the GitHub revision URL */
function get_github_revision_url($projecturl, $revision, $priorrevision)
{
  return get_gitorious_revision_url($projecturl, $revision, $priorrevision);
}

/** Return the cgit revision URL */
function get_cgit_revision_url($projecturl, $revision, $priorrevision)
{
  $revision_url = $projecturl . "/log/?id=" . $revision;
  return make_cdash_url($revision_url);
}

/** Return the Redmine revision URL */
function get_redmine_revision_url($projecturl, $revision, $priorrevision)
{
  $revision_url = $projecturl . "/revisions/" . $revision;
  return make_cdash_url($revision_url);
}

/** Return the global revision URL (not file based) for a repository */
function get_revision_url($projectid, $revision, $priorrevision)
{
  if(!is_numeric($projectid))
    {
    return;
    }

  $project = pdo_query("SELECT cvsviewertype,cvsurl FROM project WHERE id='$projectid'");
  $project_array = pdo_fetch_array($project);
  $projecturl = $project_array['cvsurl'];

  $cvsviewertype = strtolower($project_array["cvsviewertype"]);
  $revisionfonction = 'get_'.$cvsviewertype.'_revision_url';

  if(function_exists($revisionfonction))
    {
    return $revisionfonction($projecturl,$revision,$priorrevision);
    }
  else // default is viewcvs
    {
    return get_viewcvs_revision_url($projecturl,$revision);
    }
}

?>
