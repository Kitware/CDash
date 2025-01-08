<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\User;
use App\Utils\TestingDay;
use CDash\Database;
use CDash\Model\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class SiteController extends AbstractController
{
    public function siteStatistics(): View|RedirectResponse
    {
        if (config('database.default') === 'pgsql') {
            $sites = DB::select("
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
            $sites = DB::select('
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

        return $this->view('site.site-statistics')
            ->with('sites', $sites);
    }

    public function editSite(): View|RedirectResponse
    {
        $userid = Auth::id();

        $xml = begin_XML_for_XSLT();
        $xml .= '<menutitle>CDash</menutitle>';
        $xml .= '<menusubtitle>Claim sites</menusubtitle>';

        // Post
        @$claimsites = $_POST['claimsites'];
        $availablesites = $_POST['availablesites'] ?? [];
        $checkedsites = $_POST['checkedsites'] ?? [];
        if ($claimsites) {
            foreach ($availablesites as $siteid) {
                if (array_key_exists($siteid, $checkedsites)) {
                    self::add_site2user(intval($siteid), intval($userid));
                } else {
                    self::remove_site2user(intval($siteid), intval($userid));
                }
            }
            $xml .= add_XML_value('warning', 'Claimed sites updated.');
        }

        @$claimsite = $_POST['claimsite'];
        $claimsiteid = intval($_POST['claimsiteid'] ?? -1);
        if ($claimsite) {
            self::add_site2user(intval($claimsiteid), intval($userid));
        }

        @$updatesite = $_POST['updatesite'];
        @$geolocation = $_POST['geolocation'];

        $db = Database::getInstance();

        if (isset($_POST['unclaimsite']) && isset($_GET['siteid'])) {
            DB::delete('
                    DELETE FROM site2user
                    WHERE siteid=? AND userid=?
                ', [intval($_GET['siteid']), $userid]);
            return redirect('/user');
        }

        if ($updatesite || $geolocation) {
            $site_name = request()->string('site_name');
            $site_description = request()->post('site_description');
            $site_processoris64bits = request()->post('site_processoris64bits');
            $site_processorvendor = request()->post('site_processorvendor');
            $site_processorvendorid = request()->post('site_processorvendorid');
            $site_processorfamilyid = request()->post('site_processorfamilyid');
            $site_processormodelid = request()->post('site_processormodelid');
            $site_processorcachesize = request()->post('site_processorcachesize');
            $site_numberlogicalcpus = request()->post('site_numberlogicalcpus');
            $site_numberphysicalcpus = request()->post('site_numberphysicalcpus');
            $site_totalvirtualmemory = request()->post('site_totalvirtualmemory');
            $site_totalphysicalmemory = request()->post('site_totalphysicalmemory');
            $site_logicalprocessorsperphysical = request()->post('site_logicalprocessorsperphysical');
            $site_processorclockfrequency = request()->post('site_processorclockfrequency');
            $site_ip = $_POST['site_ip'];
            $site_longitude = $_POST['site_longitude'];
            $site_latitude = $_POST['site_latitude'];

            if (isset($_POST['outoforder'])) {
                $outoforder = true;
            } else {
                $outoforder = false;
            }
        }

        if ($updatesite) {
            self::update_site(
                $claimsiteid,
                $site_name,
                [
                    'processoris64bits' => $site_processoris64bits,
                    'processorvendor' => $site_processorvendor,
                    'processorvendorid' => $site_processorvendorid,
                    'processorfamilyid' => $site_processorfamilyid,
                    'processormodelid' => $site_processormodelid,
                    'processorcachesize' => $site_processorcachesize,
                    'numberlogicalcpus' => $site_numberlogicalcpus,
                    'numberphysicalcpus' => $site_numberphysicalcpus,
                    'totalvirtualmemory' => $site_totalvirtualmemory,
                    'totalphysicalmemory' => $site_totalphysicalmemory,
                    'logicalprocessorsperphysical' => $site_logicalprocessorsperphysical,
                    'processorclockfrequency' => $site_processorclockfrequency,
                    'description' => $site_description,
                ],
                $site_ip,
                $site_latitude,
                $site_longitude,
                $outoforder
            );
        }

        // If we should retrieve the geolocation
        if ($geolocation) {
            $location = get_geolocation($site_ip);
            self::update_site(
                $claimsiteid,
                $site_name,
                [
                    'processoris64bits' => $site_processoris64bits,
                    'processorvendor' => $site_processorvendor,
                    'processorvendorid' => $site_processorvendorid,
                    'processorfamilyid' => $site_processorfamilyid,
                    'processormodelid' => $site_processormodelid,
                    'processorcachesize' => $site_processorcachesize,
                    'numberlogicalcpus' => $site_numberlogicalcpus,
                    'numberphysicalcpus' => $site_numberphysicalcpus,
                    'totalvirtualmemory' => $site_totalvirtualmemory,
                    'totalphysicalmemory' => $site_totalphysicalmemory,
                    'logicalprocessorsperphysical' => $site_logicalprocessorsperphysical,
                    'processorclockfrequency' => $site_processorclockfrequency,
                    'description' => $site_description,
                ],
                $site_ip,
                $location['latitude'],
                $location['longitude'],
                $outoforder
            );
        }

        // If we have a projectid that means we should list all the sites
        $projectid = $_GET['projectid'] ?? null;
        if ($projectid !== null) {
            $projectid = (int) $projectid;
        }
        if ($projectid !== null) {
            $project_array = $db->executePreparedSingleRow('SELECT name FROM project WHERE id=?', [$projectid]);
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
                            ', [$projectid, $beginUTCTime]);

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
        $siteid = $_GET['siteid'] ?? null;
        if ($siteid !== null) {
            $xml .= '<user>';
            $xml .= '<site>';
            $site = Site::findOrFail((int) $siteid);

            $xml .= add_XML_value('id', $site->id);
            $xml .= add_XML_value('name', $site->name);
            $xml .= add_XML_value('description', stripslashes($site->mostRecentInformation->description ?? ''));
            $xml .= add_XML_value('processoris64bits', $site->mostRecentInformation?->processoris64bits);
            $xml .= add_XML_value('processorvendor', $site->mostRecentInformation?->processorvendor);
            $xml .= add_XML_value('processorvendorid', $site->mostRecentInformation?->processorvendorid);
            $xml .= add_XML_value('processorfamilyid', $site->mostRecentInformation?->processorfamilyid);
            $xml .= add_XML_value('processormodelid', $site->mostRecentInformation?->processormodelid);
            $xml .= add_XML_value('processorcachesize', $site->mostRecentInformation?->processorcachesize);
            $xml .= add_XML_value('numberlogicalcpus', $site->mostRecentInformation?->numberlogicalcpus);
            $xml .= add_XML_value('numberphysicalcpus', $site->mostRecentInformation?->numberphysicalcpus);
            $xml .= add_XML_value('totalvirtualmemory', $site->mostRecentInformation?->totalvirtualmemory);
            $xml .= add_XML_value('totalphysicalmemory', $site->mostRecentInformation?->totalphysicalmemory);
            $xml .= add_XML_value('logicalprocessorsperphysical', $site->mostRecentInformation?->logicalprocessorsperphysical);
            $xml .= add_XML_value('processorclockfrequency', $site->mostRecentInformation?->processorclockfrequency);
            $xml .= add_XML_value('ip', $site->ip);
            $xml .= add_XML_value('latitude', $site->latitude);
            $xml .= add_XML_value('longitude', $site->longitude);
            $xml .= add_XML_value('outoforder', $site->outoforder);
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
                         ', [$siteid, $userid]);
            echo pdo_error();
            if (!empty($user2site)) {
                $xml .= add_XML_value('siteclaimed', '0');
            } else {
                $xml .= add_XML_value('siteclaimed', '1');
            }

            $xml .= '</user>';
        }

        $xml .= '</cdash>';

        return $this->view('cdash', 'Edit Site')
            ->with('xsl', true)
            ->with('xsl_content', generate_XSLT($xml, base_path() . '/app/cdash/public/editSite', true));
    }

    public function viewSite(int $siteid): View
    {
        $db = Database::getInstance();

        $site = Site::findOrFail($siteid);

        $currenttime = $_GET['currenttime'] ?? null;
        if ($currenttime !== null) {
            $currenttime = (int) $currenttime;

            // Current timestamp is the beginning of the dashboard and we want the end
            $currenttimestamp = Carbon::createFromTimestamp($currenttime + 3600 * 24);
        } else {
            $currenttimestamp = Carbon::maxValue();
        }

        $siteinformation = $site->mostRecentInformation($currenttimestamp)->first();

        $xml = begin_XML_for_XSLT();

        $projectid = (int) ($_GET['project'] ?? 0);

        if ($projectid > 0) {
            $project = new Project();
            $project->Id = $projectid;
            $project->Fill();
            $xml .= '<backurl>index.php?project=' . urlencode($project->Name);
            $date = TestingDay::get($project, gmdate(FMT_DATETIME, $currenttime));
            $xml .= '&#38;date=' . $date;
            $xml .= '</backurl>';
        } else {
            $project = null;
            $xml .= '<backurl>index.php</backurl>';
        }
        $xml .= "<title>CDash - {$site->name}</title>";
        $xml .= "<menusubtitle>{$site->name}</menusubtitle>";

        $xml .= '<dashboard>';
        $xml .= '<title>CDash</title>';

        $xml .= add_XML_value('baseURL', config('app.url'));
        $apikey = config('cdash.google_map_api_key');

        $MB = 1048576;

        $total_virtual_memory = $siteinformation?->totalvirtualmemory;
        if ($total_virtual_memory !== null) {
            $total_virtual_memory = getByteValueWithExtension($total_virtual_memory * $MB) . 'iB';
        }

        $total_physical_memory = $siteinformation?->totalphysicalmemory;
        if ($total_physical_memory !== null) {
            $total_physical_memory = getByteValueWithExtension($total_physical_memory * $MB) . 'iB';
        }

        $processor_clock_frequency = $siteinformation?->processorclockfrequency;
        if ($processor_clock_frequency !== null) {
            $processor_clock_frequency = getByteValueWithExtension($processor_clock_frequency * 10 ** 6, 1000) . 'Hz';
        }

        $xml .= add_XML_value('googlemapkey', $apikey);
        $xml .= '</dashboard>';
        $xml .= '<site>';
        $xml .= add_XML_value('id', $site->id);
        $xml .= add_XML_value('name', $site->name);
        $xml .= add_XML_value('description', stripslashes($siteinformation->description ?? ''));
        $xml .= add_XML_value('processoris64bits', $siteinformation?->processoris64bits);
        $xml .= add_XML_value('processorvendor', $siteinformation?->processorvendor);
        $xml .= add_XML_value('processorvendorid', $siteinformation?->processorvendorid);
        $xml .= add_XML_value('processorfamilyid', $siteinformation?->processorfamilyid);
        $xml .= add_XML_value('processormodelid', $siteinformation?->processormodelid);
        $xml .= add_XML_value('processorcachesize', $siteinformation?->processorcachesize);
        $xml .= add_XML_value('numberlogicalcpus', $siteinformation?->numberlogicalcpus);
        $xml .= add_XML_value('numberphysicalcpus', $siteinformation?->numberphysicalcpus);
        $xml .= add_XML_value('totalvirtualmemory', $total_virtual_memory);
        $xml .= add_XML_value('totalphysicalmemory', $total_physical_memory);
        $xml .= add_XML_value('logicalprocessorsperphysical', $siteinformation?->logicalprocessorsperphysical);
        $xml .= add_XML_value('processorclockfrequency', $processor_clock_frequency);
        $xml .= add_XML_value('outoforder', $site->outoforder);
        if ($project !== null && $project->ShowIPAddresses) {
            $xml .= add_XML_value('ip', $site->ip);
            $xml .= add_XML_value('latitude', $site->latitude);
            $xml .= add_XML_value('longitude', $site->longitude);
        }
        $xml .= '</site>';

        // List the claimers of the site
        $siteclaimer = $db->executePrepared('
                           SELECT u.firstname, u.lastname, u.email
                           FROM users as u, site2user
                           WHERE
                               u.id=site2user.userid
                               AND site2user.siteid=?
                           ORDER BY firstname
                       ', [$siteid]);
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
        $projects = [];
        $site2project = $db->executePrepared('
                            SELECT projectid, max(submittime) AS maxtime
                            FROM build
                            WHERE
                                siteid=?
                                AND projectid>0
                            GROUP BY projectid
                        ', [$siteid]);

        foreach ($site2project as $project_site) {
            $projectid = $project_site['projectid'];

            $project = new Project();
            $project->Id = $projectid;
            $project->Fill();
            if (Gate::allows('view-project', $project)) {
                $xml .= '<project>';
                $xml .= add_XML_value('id', $projectid);
                $xml .= add_XML_value('submittime', $project_site['maxtime']);
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
            abort(403, 'You cannot access this page');
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
            if (Gate::allows('view-project', $project)) {
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

                $user2project = $db->executePrepared('SELECT role FROM user2project WHERE projectid=? and role>0', [$projectid]);
                if (count($user2project) > 0) {
                    $xml .= add_XML_value('sitemanager', '1');

                    $user2site = $db->executePrepared('SELECT * FROM site2user WHERE siteid=? and userid=?',
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

        return $this->view('cdash', $site->name)
            ->with('xsl', true)
            ->with('xsl_content', generate_XSLT($xml, base_path() . '/app/cdash/public/viewSite', true));
    }

    /**
     * add a user to a site
     */
    private static function add_site2user(int $siteid, int $userid): void
    {
        $db = Database::getInstance();
        $site2user = $db->executePrepared('SELECT * FROM site2user WHERE siteid=? AND userid=?', [intval($siteid), intval($userid)]);
        if (!empty($site2user)) {
            DB::insert('INSERT INTO site2user (siteid, userid) VALUES (?, ?)', [$siteid, $userid]);
        }
    }

    /**
     * remove a user from a site
     */
    private static function remove_site2user(int $siteid, int $userid): void
    {
        DB::delete('DELETE FROM site2user WHERE siteid=? AND userid=?', [$siteid, $userid]);
    }

    /**
     * Update a site
     *
     * @param array<string,mixed> $information
     */
    private static function update_site(
        int $siteid,
        string $name,
        array $information,
        $ip,
        $latitude,
        $longitude,
        bool $outoforder = false,
    ): void {
        // Update the basic information first
        $site = Site::findOrFail($siteid);
        $site->updateOrFail([
            'name' => $name,
            'ip' => $ip,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'outoforder' => $outoforder,
        ]);

        // Create a new information row if something has changed since the most recent update
        $site->mostRecentInformation()->firstOrCreate($information);
    }
}
