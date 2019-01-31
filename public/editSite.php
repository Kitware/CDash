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

include dirname(__DIR__) . '/config/config.php';
require_once 'include/pdo.php';
include 'public/login.php';
include_once 'include/common.php';
include 'include/version.php';

if (Auth::check()) {
    $userid = Auth::id();

    $xml = begin_XML_for_XSLT();
    $xml .= '<backurl>user.php</backurl>';
    $xml .= '<title>CDash - Edit Site</title>';
    $xml .= '<menutitle>CDash</menutitle>';
    $xml .= '<menusubtitle>Claim sites</menusubtitle>';

    // Post
    @$claimsites = $_POST['claimsites'];
    @$availablesites = $_POST['availablesites'];
    @$checkedsites = $_POST['checkedsites'];
    if ($claimsites) {
        foreach ($availablesites as $siteid) {
            if (@array_key_exists($siteid, $checkedsites)) {
                add_site2user($siteid, $userid);
            } else {
                remove_site2user($siteid, $userid);
            }
        }
        $xml .= add_XML_value('warning', 'Claimed sites updated.');
    }

    @$claimsite = $_POST['claimsite'];
    @$claimsiteid = $_POST['claimsiteid'];
    if ($claimsite) {
        add_site2user($claimsiteid, $userid);
    }

    @$updatesite = $_POST['updatesite'];
    @$geolocation = $_POST['geolocation'];

    if (isset($_POST['unclaimsite']) && isset($_GET['siteid'])) {
        pdo_query('DELETE FROM site2user WHERE siteid=' . qnum(pdo_real_escape_numeric($_GET['siteid'])) . ' AND userid=' . qnum($userid));
        echo "<script language=\"javascript\">window.location='user.php'</script>";
        return;
    }

    if ($updatesite || $geolocation) {
        $site_name = $_POST['site_name'];
        $site_description = $_POST['site_description'];
        $site_processoris64bits = $_POST['site_processoris64bits'];
        $site_processorvendor = $_POST['site_processorvendor'];
        $site_processorvendorid = $_POST['site_processorvendorid'];
        $site_processorfamilyid = $_POST['site_processorfamilyid'];
        $site_processormodelid = $_POST['site_processormodelid'];
        $site_processorcachesize = $_POST['site_processorcachesize'];
        $site_numberlogicalcpus = $_POST['site_numberlogicalcpus'];
        $site_numberphysicalcpus = $_POST['site_numberphysicalcpus'];
        $site_totalvirtualmemory = $_POST['site_totalvirtualmemory'];
        $site_totalphysicalmemory = $_POST['site_totalphysicalmemory'];
        $site_logicalprocessorsperphysical = $_POST['site_logicalprocessorsperphysical'];
        $site_processorclockfrequency = $_POST['site_processorclockfrequency'];
        $site_ip = $_POST['site_ip'];
        $site_longitude = $_POST['site_longitude'];
        $site_latitude = $_POST['site_latitude'];

        if (isset($_POST['outoforder'])) {
            $outoforder = 1;
        } else {
            $outoforder = 0;
        }

        if (isset($_POST['newdescription_revision'])) {
            $newdescription_revision = 1;
        } else {
            $newdescription_revision = 0;
        }
    }

    if ($updatesite) {
        update_site($claimsiteid, $site_name,
            $site_processoris64bits,
            $site_processorvendor,
            $site_processorvendorid,
            $site_processorfamilyid,
            $site_processormodelid,
            $site_processorcachesize,
            $site_numberlogicalcpus,
            $site_numberphysicalcpus,
            $site_totalvirtualmemory,
            $site_totalphysicalmemory,
            $site_logicalprocessorsperphysical,
            $site_processorclockfrequency,
            $site_description,
            $site_ip, $site_latitude,
            $site_longitude, !$newdescription_revision,
            $outoforder);
    }

    // If we should retrieve the geolocation
    if ($geolocation) {
        $location = get_geolocation($site_ip);
        update_site($claimsiteid, $site_name,
            $site_processoris64bits,
            $site_processorvendor,
            $site_processorvendorid,
            $site_processorfamilyid,
            $site_processormodelid,
            $site_processorcachesize,
            $site_numberlogicalcpus,
            $site_numberphysicalcpus,
            $site_totalvirtualmemory,
            $site_totalphysicalmemory,
            $site_logicalprocessorsperphysical,
            $site_processorclockfrequency,
            $site_description, $site_ip, $location['latitude'], $location['longitude'],
            false, $outoforder);
    }

    // If we have a projectid that means we should list all the sites
    @$projectid = $_GET['projectid'];
    if ($projectid != null) {
        $projectid = pdo_real_escape_numeric($projectid);
    }
    if (isset($projectid) && is_numeric($projectid)) {
        $project_array = pdo_fetch_array(pdo_query("SELECT name FROM project WHERE id='$projectid'"));
        $xml .= '<project>';
        $xml .= add_XML_value('id', $projectid);
        $xml .= add_XML_value('name', $project_array['name']);
        $xml .= '</project>';

        // Select sites that belong to this project
        $beginUTCTime = gmdate(FMT_DATETIME, time() - 3600 * 7 * 24); // 7 days
        $site2project = pdo_query("SELECT DISTINCT site.id,site.name FROM build,site WHERE build.projectid='$projectid'
                               AND build.starttime>'$beginUTCTime'
                               AND site.id=build.siteid ORDER BY site.name ASC"); //group by is slow

        while ($site2project_array = pdo_fetch_array($site2project)) {
            $siteid = $site2project_array['id'];
            $xml .= '<site>';
            $xml .= add_XML_value('id', $siteid);
            $xml .= add_XML_value('name', $site2project_array['name']);
            $user2site = pdo_query("SELECT * FROM site2user WHERE siteid='$siteid' and userid='$userid'");
            if (pdo_num_rows($user2site) == 0) {
                $xml .= add_XML_value('claimed', '0');
            } else {
                $xml .= add_XML_value('claimed', '1');
            }
            $xml .= '</site>';
        }
    }

    // If we have a siteid we look if the user has claimed the site or not
    @$siteid = $_GET['siteid'];
    if ($siteid != null) {
        $siteid = pdo_real_escape_numeric($siteid);
    }
    if (isset($siteid) && is_numeric($siteid)) {
        $xml .= '<user>';
        $xml .= '<site>';
        $site_array = pdo_fetch_array(pdo_query("SELECT * FROM site WHERE id='$siteid'"));

        $siteinformation_array = array();
        $siteinformation_array['description'] = 'NA';
        $siteinformation_array['processoris64bits'] = 'NA';
        $siteinformation_array['processorvendor'] = 'NA';
        $siteinformation_array['processorvendorid'] = 'NA';
        $siteinformation_array['processorfamilyid'] = 'NA';
        $siteinformation_array['processormodelid'] = 'NA';
        $siteinformation_array['processorcachesize'] = 'NA';
        $siteinformation_array['numberlogicalcpus'] = 'NA';
        $siteinformation_array['numberphysicalcpus'] = 'NA';
        $siteinformation_array['totalvirtualmemory'] = 'NA';
        $siteinformation_array['totalphysicalmemory'] = 'NA';
        $siteinformation_array['logicalprocessorsperphysical'] = 'NA';
        $siteinformation_array['processorclockfrequency'] = 'NA';

        // Get the last information about the size
        $query = pdo_query("SELECT * FROM siteinformation WHERE siteid='$siteid' ORDER BY timestamp DESC LIMIT 1");
        if (pdo_num_rows($query) > 0) {
            $siteinformation_array = pdo_fetch_array($query);
            if ($siteinformation_array['processoris64bits'] == -1) {
                $siteinformation_array['processoris64bits'] = 'NA';
            }
            if ($siteinformation_array['processorfamilyid'] == -1) {
                $siteinformation_array['processorfamilyid'] = 'NA';
            }
            if ($siteinformation_array['processormodelid'] == -1) {
                $siteinformation_array['processormodelid'] = 'NA';
            }
            if ($siteinformation_array['processorcachesize'] == -1) {
                $siteinformation_array['processorcachesize'] = 'NA';
            }
            if ($siteinformation_array['numberlogicalcpus'] == -1) {
                $siteinformation_array['numberlogicalcpus'] = 'NA';
            }
            if ($siteinformation_array['numberphysicalcpus'] == -1) {
                $siteinformation_array['numberphysicalcpus'] = 'NA';
            }
            if ($siteinformation_array['totalvirtualmemory'] == -1) {
                $siteinformation_array['totalvirtualmemory'] = 'NA';
            }
            if ($siteinformation_array['totalphysicalmemory'] == -1) {
                $siteinformation_array['totalphysicalmemory'] = 'NA';
            }
            if ($siteinformation_array['logicalprocessorsperphysical'] == -1) {
                $siteinformation_array['logicalprocessorsperphysical'] = 'NA';
            }
            if ($siteinformation_array['processorclockfrequency'] == -1) {
                $siteinformation_array['processorclockfrequency'] = 'NA';
            }
        }

        $xml .= add_XML_value('id', $siteid);
        $xml .= add_XML_value('name', $site_array['name']);
        $xml .= add_XML_value('description', stripslashes($siteinformation_array['description']));
        $xml .= add_XML_value('processoris64bits', $siteinformation_array['processoris64bits']);
        $xml .= add_XML_value('processorvendor', $siteinformation_array['processorvendor']);
        $xml .= add_XML_value('processorvendorid', $siteinformation_array['processorvendorid']);
        $xml .= add_XML_value('processorfamilyid', $siteinformation_array['processorfamilyid']);
        $xml .= add_XML_value('processormodelid', $siteinformation_array['processormodelid']);
        $xml .= add_XML_value('processorcachesize', $siteinformation_array['processorcachesize']);
        $xml .= add_XML_value('numberlogicalcpus', $siteinformation_array['numberlogicalcpus']);
        $xml .= add_XML_value('numberphysicalcpus', $siteinformation_array['numberphysicalcpus']);
        $xml .= add_XML_value('totalvirtualmemory', $siteinformation_array['totalvirtualmemory']);
        $xml .= add_XML_value('totalphysicalmemory', $siteinformation_array['totalphysicalmemory']);
        $xml .= add_XML_value('logicalprocessorsperphysical', $siteinformation_array['logicalprocessorsperphysical']);
        $xml .= add_XML_value('processorclockfrequency', $siteinformation_array['processorclockfrequency']);
        $xml .= add_XML_value('ip', $site_array['ip']);
        $xml .= add_XML_value('latitude', $site_array['latitude']);
        $xml .= add_XML_value('longitude', $site_array['longitude']);
        $xml .= add_XML_value('outoforder', $site_array['outoforder']);
        $xml .= '</site>';

        $user2site = pdo_query("SELECT su.userid FROM site2user AS su,user2project AS up
                            WHERE su.userid=up.userid AND up.role>0 AND su.siteid='$siteid' and su.userid='$userid'");
        echo pdo_error();
        if (pdo_num_rows($user2site) == 0) {
            $xml .= add_XML_value('siteclaimed', '0');
        } else {
            $xml .= add_XML_value('siteclaimed', '1');
        }

        $xml .= '</user>';
    }

    $xml .= '</cdash>';

    // Now doing the xslt transition
    generate_XSLT($xml, 'editSite');
}
