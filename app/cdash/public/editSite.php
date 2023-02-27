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

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Auth;
use CDash\Database;

require_once 'include/pdo.php';
include_once 'include/common.php';

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

    $db = Database::getInstance();

    if (isset($_POST['unclaimsite']) && isset($_GET['siteid'])) {
        $db->executePrepared('
            DELETE FROM site2user
            WHERE siteid=? AND userid=?
        ', [intval($_GET['siteid']), $userid]);
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
        $project_array = $db->executePreparedSingleRow('SELECT name FROM project WHERE id=?', [intval($projectid)]);
        $xml .= '<project>';
        $xml .= add_XML_value('id', $projectid);
        $xml .= add_XML_value('name', $project_array['name']);
        $xml .= '</project>';

        // Select sites that belong to this project
        $beginUTCTime = gmdate(FMT_DATETIME, time() - 3600 * 7 * 24); // 7 days
        $site2project = $db->executePrepared('
                            SELECT DISTINCT site.id, site.name
                            FROM build, site
                            WHERE
                                build.projectid=?
                                AND build.starttime>?
                                AND site.id=build.siteid
                            ORDER BY site.name ASC
                        ', [intval($projectid), $beginUTCTime]);

        foreach ($site2project as $site2project_array) {
            $siteid = intval($site2project_array['id']);
            $xml .= '<site>';
            $xml .= add_XML_value('id', $siteid);
            $xml .= add_XML_value('name', $site2project_array['name']);
            $user2site = $db->executePreparedSingleRow('
                             SELECT COUNT(*) AS c
                             FROM site2user
                             WHERE siteid=? AND userid=?
                         ', [$siteid, intval($userid)]);
            if (count($user2site['c']) === 0) {
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
        $site_array = $db->executePreparedSingleRow('SELECT * FROM site WHERE id=?', [intval($siteid)]);

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
        $query = $db->executePreparedSingleRow('
                     SELECT *
                     FROM siteinformation
                     WHERE siteid=?
                     ORDER BY timestamp DESC
                     LIMIT 1
                 ', [intval($siteid)]);
        if (!empty($query)) {
            $siteinformation_array = $query;
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

        $user2site = $db->executePreparedSingleRow('
                         SELECT su.userid
                         FROM
                             site2user AS su,
                             user2project AS up
                         WHERE
                             su.userid=up.userid
                             AND up.role>0
                             AND su.siteid=?
                             AND su.userid=?
                     ', [intval($siteid), intval($userid)]);
        echo pdo_error();
        if (!empty($user2site)) {
            $xml .= add_XML_value('siteclaimed', '0');
        } else {
            $xml .= add_XML_value('siteclaimed', '1');
        }

        $xml .= '</user>';
    }

    $xml .= '</cdash>';

    // Now doing the xslt transition
    generate_XSLT($xml, 'editSite');
} else {
    LoginController::staticShowLoginForm();
}
