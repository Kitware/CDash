<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ProjectPermissions;
use App\Services\TestingDay;
use CDash\Database;
use CDash\Model\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SiteController extends AbstractController
{
    public function siteStatistics(): View|RedirectResponse
    {
        $xml = begin_XML_for_XSLT();
        $xml .= '<backurl>user.php</backurl>';
        $xml .= '<menutitle>CDash</menutitle>';
        $xml .= '<menusubtitle>Site Statistics</menusubtitle>';

        $db = Database::getInstance();

        if (config('database.default') === 'pgsql') {
            $query = $db->executePrepared("
                         SELECT
                             siteid,
                             sitename,
                             SUM(elapsed) AS busytime
                         FROM (
                             SELECT
                                 site.id AS siteid,
                                 site.name AS sitename,
                                 project.name AS projectname,
                                 build.name AS buildname,
                                 build.type,
                                 AVG(submittime - buildupdate.starttime) AS elapsed
                             FROM
                                 build,
                                 build2update,
                                 buildupdate,
                                 project,
                                 site
                             WHERE
                                 submittime > NOW() - interval '168 hours'
                                 AND build2update.buildid = build.id
                                 AND buildupdate.id = build2update.updateid
                                 AND site.id = build.siteid
                                 AND build.projectid = project.id
                             GROUP BY
                                 sitename,
                                 projectname,
                                 buildname,
                                 build.type,
                                 site.id
                             ORDER BY elapsed DESC
                         ) AS summary
                         GROUP BY
                             sitename,
                             summary.siteid
                         ORDER BY busytime DESC
                     ");
        } else {
            $query = $db->executePrepared('
                         SELECT
                             siteid,
                             sitename,
                             SEC_TO_TIME(SUM(elapsed)) AS busytime
                         FROM (
                             SELECT
                                 site.id AS siteid,
                                 site.name AS sitename,
                                 project.name AS projectname,
                                 build.name AS buildname,
                                 build.type,
                                 AVG(TIME_TO_SEC(TIMEDIFF(submittime, buildupdate.starttime))) AS elapsed
                             FROM
                                 build,
                                 build2update,
                                 buildupdate,
                                 project,
                                 site
                             WHERE
                                 submittime > TIMESTAMPADD(HOUR, -168, NOW())
                                 AND build2update.buildid = build.id
                                 AND buildupdate.id = build2update.updateid
                                 AND site.id = build.siteid
                                 AND build.projectid = project.id
                             GROUP BY
                                 sitename,
                                 projectname,
                                 buildname,
                                 build.type
                             ORDER BY elapsed DESC
                         ) AS summary
                         GROUP BY sitename
                         ORDER BY busytime DESC
                     ');
        }
        echo pdo_error();
        foreach ($query as $query_array) {
            $xml .= '<site>';
            $xml .= add_XML_value('id', $query_array['siteid']);
            $xml .= add_XML_value('name', $query_array['sitename']);
            $xml .= add_XML_value('busytime', $query_array['busytime']);
            $xml .= '</site>';
        }

        $xml .= '</cdash>';

        return view('cdash', [
            'xsl' => true,
            'xsl_content' => generate_XSLT($xml, base_path() . '/app/cdash/public/siteStatistics', true),
            'title' => 'Site Statistics'
        ]);
    }

    public function editSite(): View|RedirectResponse
    {
        $userid = Auth::id();

        $xml = begin_XML_for_XSLT();
        $xml .= '<backurl>user.php</backurl>';
        $xml .= '<menutitle>CDash</menutitle>';
        $xml .= '<menusubtitle>Claim sites</menusubtitle>';

        // Post
        @$claimsites = $_POST['claimsites'];
        @$availablesites = $_POST['availablesites'];
        @$checkedsites = $_POST['checkedsites'];
        if ($claimsites) {
            foreach ($availablesites as $siteid) {
                if (@array_key_exists($siteid, $checkedsites)) {
                    add_site2user(intval($siteid), intval($userid));
                } else {
                    remove_site2user(intval($siteid), intval($userid));
                }
            }
            $xml .= add_XML_value('warning', 'Claimed sites updated.');
        }

        @$claimsite = $_POST['claimsite'];
        @$claimsiteid = $_POST['claimsiteid'];
        if ($claimsite) {
            add_site2user(intval($claimsiteid), intval($userid));
        }

        @$updatesite = $_POST['updatesite'];
        @$geolocation = $_POST['geolocation'];

        $db = Database::getInstance();

        if (isset($_POST['unclaimsite']) && isset($_GET['siteid'])) {
            $db->executePrepared('
                    DELETE FROM site2user
                    WHERE siteid=? AND userid=?
                ', [intval($_GET['siteid']), $userid]);
            return redirect('/user.php');
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
                if (intval($user2site['c']) === 0) {
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

        return view('cdash', [
            'xsl' => true,
            'xsl_content' => generate_XSLT($xml, base_path() . '/app/cdash/public/editSite', true),
            'title' => 'Edit Site'
        ]);
    }

    public function viewSite(): View
    {
        $db = Database::getInstance();

        @$siteid = $_GET['siteid'];
        if ($siteid != null) {
            $siteid = pdo_real_escape_numeric($siteid);
        }

        // Checks
        if (!isset($siteid) || !is_numeric($siteid)) {
            return view('cdash', [
                'xsl' => true,
                'xsl_content' => 'Not a valid siteid!'
            ]);
        }

        $site_array = $db->executePreparedSingleRow("SELECT * FROM site WHERE id=?", [$siteid]);
        $sitename = $site_array['name'];

        @$currenttime = $_GET['currenttime'];
        if ($currenttime != null) {
            $currenttime = pdo_real_escape_numeric($currenttime);
        }

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

        // Current timestamp is the beginning of the dashboard and we want the end
        $currenttimestamp = gmdate(FMT_DATETIME, $currenttime + 3600 * 24);

        $query = $db->executePrepared("
                     SELECT *
                     FROM siteinformation
                     WHERE
                         siteid=?
                         AND timestamp<=?
                     ORDER BY timestamp DESC
                     LIMIT 1
                 ", [$siteid, $currenttimestamp]);

        if (count($query) > 0) {
            $siteinformation_array = $query[0];
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

        $xml = begin_XML_for_XSLT();

        @$projectid = pdo_real_escape_numeric($_GET['project']);
        if ($projectid) {
            $project = new Project();
            $project->Id = $projectid;
            $project->Fill();
            $xml .= '<backurl>index.php?project=' . urlencode($project->Name);
            $date = TestingDay::get($project, gmdate(FMT_DATETIME, $currenttime));
            $xml .= '&#38;date=' . $date;
            $xml .= '</backurl>';
        } else {
            $xml .= '<backurl>index.php</backurl>';
        }
        $xml .= "<title>CDash - $sitename</title>";
        $xml .= "<menusubtitle>$sitename</menusubtitle>";

        $xml .= '<dashboard>';
        $xml .= '<title>CDash</title>';

        $apikey = config('cdash.google_map_api_key');

        $MB = 1048576;

        $total_virtual_memory = 0;
        if (is_numeric($siteinformation_array['totalvirtualmemory'])) {
            $total_virtual_memory = $siteinformation_array['totalvirtualmemory'] * $MB;
        }

        $total_physical_memory = 0;
        if (is_numeric($siteinformation_array['totalphysicalmemory'])) {
            $total_physical_memory = $siteinformation_array['totalphysicalmemory'] * $MB;
        }

        $processor_clock_frequency = 0;
        if (is_numeric($siteinformation_array['processorclockfrequency'])) {
            $processor_clock_frequency = $siteinformation_array['processorclockfrequency'] * 10**6;
        }

        $xml .= add_XML_value('googlemapkey', $apikey);
        $xml .= '</dashboard>';
        $xml .= '<site>';
        $xml .= add_XML_value('id', $site_array['id']);
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
        $xml .= add_XML_value('totalvirtualmemory', getByteValueWithExtension($total_virtual_memory) . 'iB');
        $xml .= add_XML_value('totalphysicalmemory', getByteValueWithExtension($total_physical_memory) . 'iB');
        $xml .= add_XML_value('logicalprocessorsperphysical', $siteinformation_array['logicalprocessorsperphysical']);
        $xml .= add_XML_value('processorclockfrequency', getByteValueWithExtension($processor_clock_frequency, 1000) . 'Hz');
        $xml .= add_XML_value('outoforder', $site_array['outoforder']);
        if ($projectid && $project->ShowIPAddresses) {
            $xml .= add_XML_value('ip', $site_array['ip']);
            $xml .= add_XML_value('latitude', $site_array['latitude']);
            $xml .= add_XML_value('longitude', $site_array['longitude']);
        }
        $xml .= '</site>';

        // List the claimers of the site
        $siteclaimer = $db->executePrepared("
                           SELECT firstname, lastname, email
                           FROM user, site2user
                           WHERE
                               user.id=site2user.userid
                               AND site2user.siteid=?
                           ORDER BY firstname
                       ", [$siteid]);
        foreach ($siteclaimer as $sc) {
            $xml .= '<claimer>';
            $xml .= add_XML_value('firstname', $sc['firstname']);
            $xml .= add_XML_value('lastname', $sc['lastname']);
            if (isset($_SESSION['cdash'])) {
                $xml .= add_XML_value('email', $sc['email']);
            }
            $xml .= '</claimer>';
        }

        // Select projects that belong to this site
        $displayPage = 0;
        $projects = array();
        $site2project = $db->executePrepared('
                            SELECT projectid, max(submittime) AS maxtime
                            FROM build
                            WHERE
                                siteid=?
                                AND projectid>0
                            GROUP BY projectid
                        ', [$siteid]);

        foreach ($site2project as $site) {
            $projectid = $site['projectid'];

            $project = new Project();
            $project->Id = $projectid;
            $project->Fill();
            if (ProjectPermissions::userCanViewProject($project)) {
                $xml .= '<project>';
                $xml .= add_XML_value('id', $projectid);
                $xml .= add_XML_value('submittime', $site['maxtime']);
                $xml .= add_XML_value('name', $project->Name);
                $xml .= add_XML_value('name_encoded', urlencode($project->Name));
                $xml .= '</project>';
                $displayPage = 1; // if we have at least a valid project we display the page
                $projects[] = $projectid;
            }
        }

        // If the current site as only private projects we check that we have the right
        // to view the page
        if (!$displayPage) {
            return view('cdash', [
                'xsl' => true,
                'xsl_content' => 'You cannot access this page'
            ]);
        }

        // Compute the time for all the projects (faster than individually) average of the week
        if (config('database.default') == 'pgsql') {
            $timediff = 'EXTRACT(EPOCH FROM (build.submittime - buildupdate.starttime))';
            $timestampadd = "NOW()-INTERVAL'167 hours'";
        } else {
            $timediff = 'TIME_TO_SEC(TIMEDIFF(build.submittime, buildupdate.starttime))';
            $timestampadd = 'TIMESTAMPADD(' . qiv('HOUR') . ', -167, NOW())';
        }

        $testtime = $db->executePrepared("
                        SELECT projectid, build.name AS buildname, build.type AS buildtype, SUM({$timediff}) AS elapsed
                        FROM build, buildupdate, build2update
                        WHERE
                            build.submittime > {$timestampadd}
                            AND build2update.buildid = build.id
                            AND buildupdate.id = build2update.updateid
                            AND build.siteid = ?
                            GROUP BY projectid,buildname,buildtype
                            ORDER BY elapsed
                    ", [$siteid]);

        $xml .= '<siteload>';

        echo pdo_error();
        $totalload = 0;
        foreach ($testtime as $tt) {
            $projectid = $tt['projectid'];
            $project = new Project();
            $project->Id = $projectid;
            $project->Fill();
            if (ProjectPermissions::userCanViewProject($project)) {
                $timespent = round($tt['elapsed'] / 7.0); // average over 7 days
                $xml .= '<build>';
                $xml .= add_XML_value('name', $tt['buildname']);
                $xml .= add_XML_value('project', $project->Name);
                $xml .= add_XML_value('type', $tt['buildtype']);
                $xml .= add_XML_value('time', $timespent);
                $totalload += $timespent;
                $xml .= '</build>';
            }
        }

        // Compute the idle time
        $idletime = 24 * 3600 - $totalload;
        if ($idletime < 0) {
            $idletime = 0;
        }
        $xml .= '<idle>' . $idletime . '</idle>';
        $xml .= '</siteload>';

        if (isset($_SESSION['cdash'])) {
            $xml .= '<user>';
            $userid = Auth::id();

            // Check if the current user as a role in this project
            foreach ($projects as $projectid) {
                // TODO: (williamjallen) Optimize this loop to execute a constant number of queries

                $user2project = $db->executePrepared("SELECT role FROM user2project WHERE projectid=? and role>0", [$projectid]);
                if (count($user2project) > 0) {
                    $xml .= add_XML_value('sitemanager', '1');

                    $user2site = $db->executePrepared("SELECT * FROM site2user WHERE siteid=? and userid=?",
                        [$siteid, $userid]);
                    if (count($user2site) == 0) {
                        $xml .= add_XML_value('siteclaimed', '0');
                    } else {
                        $xml .= add_XML_value('siteclaimed', '1');
                    }
                    break;
                }
            }

            $user = User::where('id', '=', $userid)->first();
            $xml .= add_XML_value('id', $userid);
            $xml .= add_XML_value('admin', $user->admin);
            $xml .= '</user>';
        }

        $xml .= '</cdash>';

        return view('cdash', [
            'xsl' => true,
            'xsl_content' => generate_XSLT($xml, base_path() . '/app/cdash/public/viewSite', true),
            'title' => $sitename
        ]);
    }
}
