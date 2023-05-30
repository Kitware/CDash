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

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Auth\LoginController;
use App\Services\ProjectPermissions;
use App\Services\TestingDay;

use CDash\Config;
use CDash\Database;
use CDash\ServiceContainer;
use CDash\Model\Build;
use CDash\Model\Project;
use CDash\Model\UserProject;
use CDash\Model\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

require_once 'include/log.php';


function xslt_process(XSLTProcessor $xsltproc,
    $xml_arg,
    $xsl_arg,
    $xslcontainer = null,
    $args = null,
    $params = null)
{
    // Start with preparing the arguments
    $xml_arg = str_replace('arg:', '', $xml_arg);
    $xsl_arg = str_replace('arg:', '', $xsl_arg);

    // Create instances of the DomDocument class
    $xml = new DomDocument;
    $xsl = new DomDocument;

    // Load the xml document and the xsl template
    if (LIBXML_VERSION >= 20700) {
        $xmlOptions = LIBXML_PARSEHUGE;
    } else {
        $xmlOptions = 0;
    }

    $xml->loadXML($args[$xml_arg], $xmlOptions);
    $xsl->loadXML(file_get_contents($xsl_arg), $xmlOptions);

    // Load the xsl template
    $xsltproc->importStylesheet($xsl);

    // Set parameters when defined
    if ($params) {
        foreach ($params as $param => $value) {
            $xsltproc->setParameter('', $param, $value);
        }
    }

    // Start the transformation
    $processed = $xsltproc->transformToXml($xml);

    // Put the result in a file when specified
    if ($xslcontainer) {
        return @file_put_contents($xslcontainer, $processed);
    }

    return $processed;
}


/**
 * Do the XSLT translation
 */
function generate_XSLT($xml, string $pageName, bool $return_html = false): string
{
    $config = Config::getInstance();

    $xh = new XSLTProcessor();

    $arguments = array(
        '/_xml' => $xml
    );

    if (!empty($config->get('CDASH_DEBUG_XML'))) {
        $tmp = preg_replace("#<[A-Za-z0-9\-_.]{1,250}>#", "\\0\n", $xml);
        $tmp = preg_replace("#</[A-Za-z0-9\-_.]{1,250}>#", "\n\\0\n", $tmp);
        $inF = fopen($config->get('CDASH_DEBUG_XML'), 'w');
        fwrite($inF, $tmp);
        fclose($inF);
        unset($inF);
    }
    $xslpage = $pageName . '.xsl';

    $html = xslt_process($xh, 'arg:/_xml', $xslpage, null, $arguments);

    unset($xh);
    if ($return_html) {
        return $html;
    } else {
        echo $html;
        return '';
    }
}

/**
 * used to escape special XML characters
 */
function XMLStrFormat(string $str): string
{
    if (mb_detect_encoding($str, 'UTF-8', true) === false) {
        $str = utf8_encode($str);
    }
    $str = str_replace('&', '&amp;', $str);
    $str = str_replace('<', '&lt;', $str);
    $str = str_replace('>', '&gt;', $str);
    $str = str_replace("'", '&apos;', $str);
    $str = str_replace('"', '&quot;', $str);
    $str = str_replace("\r", '', $str);

    // Remove UTF-8 characters that are not valid in an XML document.
    // https://www.w3.org/TR/REC-xml/#charsets
    $str = preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', '', $str);

    return $str;
}

function time_difference($duration, bool $compact = false, string $suffix = '', bool $displayms = false): string
{
    $duration = is_numeric($duration) ? $duration : 0;

    // If it's "in the future" -- probably indicates server time syncing is not
    // working well...
    if (($duration < 0) || ($duration < 30 && $compact == false && $suffix == 'ago')) {
        if ($duration > -300) {
            // For "close" (less than 5 minutes diff)
            return 'just now';
        } else {
            // For "larger" negative diffs (more than 5 minutes)
            return 'Some time in the future';
        }
    }

    $years = floor($duration / 31557600);
    $duration -= $years * 31557600;
    $months = floor($duration / 2635200);
    $duration -= $months * 2635200;
    $days = floor($duration / 86400);
    $duration -= $days * 86400;
    $hours = floor($duration / 3600);
    $duration -= $hours * 3600;
    $mins = floor($duration / 60);
    $duration -= $mins * 60;
    if ($displayms) {
        $secs = floor($duration);
        $duration -= $secs;
        $msecs = round($duration * 1000);
    } else {
        $secs = round($duration);
        $msecs = 0;
    }

    $diff = '';
    if ($compact) {
        if ($years > 0) {
            $diff .= $years . ' year';
            if ($years > 1) {
                $diff .= 's';
            }
            $diff .= ' ';
        }
        if ($months > 0) {
            $diff .= $months . ' month';
            if ($months > 1) {
                $diff .= 's';
            }
            $diff .= ' ';
        }
        if ($days > 0) {
            $diff .= $days . ' day';
            if ($days > 1) {
                $diff .= 's';
            }
            $diff .= ' ';
        }
        if ($hours > 0) {
            $diff .= $hours . 'h ';
        }
        if ($mins > 0) {
            $diff .= $mins . 'm ';
        }
        if ($secs > 0) {
            $diff .= $secs . 's';
        }
        if ($displayms && $msecs > 0) {
            $diff .= ' ' . $msecs . 'ms';
        }
    } else {
        if ($years > 0) {
            $diff = $years . ' year';
            if ($years > 1) {
                $diff .= 's';
            }
        } elseif ($months > 0) {
            $diff = $months . ' month';
            if ($months > 1) {
                $diff .= 's';
            }
        } elseif ($days > 0) {
            $diff = $days . ' day';
            if ($days > 1) {
                $diff .= 's';
            }
        } elseif ($hours > 0) {
            $diff = $hours . ' hour';
            if ($hours > 1) {
                $diff .= 's';
            }
        } elseif ($mins > 0) {
            $diff = $mins . ' minute';
            if ($mins > 1) {
                $diff .= 's';
            }
        } elseif ($secs > 0) {
            $diff = $secs . ' second';
            if ($secs > 1) {
                $diff .= 's';
            }
        } elseif ($displayms && $msecs > 0) {
            $diff = $msecs . ' millisecond';
            if ($msecs > 1) {
                $diff .= 's';
            }
        }
    }

    if ($diff == '') {
        $diff = '0s';
    }

    $diff .= ' ' . $suffix;
    return rtrim($diff);
}

/**
 * Return the number of seconds represented by the specified time interval
 * This function is the inverse of time_difference().
 */
function get_seconds_from_interval($input)
{
    if (is_numeric($input)) {
        return $input;
    }

    // Check if strtotime understands the string.  It can handle our
    // verbose interval strings, but not our compact ones.
    $now = time();
    $time_value = strtotime($input, $now);
    if ($time_value) {
        $duration = $time_value - $now;
        return $duration;
    }

    // If not, convert the string from compact to verbose format
    // and then use strtotime again.
    $interval = preg_replace('/(\d+)h/', '$1 hours', $input);
    $interval = preg_replace('/(\d+)m/', '$1 minutes', $interval);
    $interval = preg_replace('/(\d+)s/', '$1 seconds', $interval);

    $time_value = strtotime($interval, $now);
    if ($time_value !== false) {
        $duration = $time_value - $now;
        return $duration;
    }

    add_log("Could not handle input: $input", 'get_seconds_from_interval', LOG_WARNING);
    return null;
}

function xml_replace_callback($matches): string
{
    $decimal_value = hexdec(bin2hex($matches[0]));
    return '&#' . $decimal_value . ';';
}

/**
 * Add an XML tag to a string
 */
function add_XML_value(string $tag, $value): string
{
    $value = preg_replace_callback('/[\x1b]/', 'xml_replace_callback', $value);
    return '<' . $tag . '>' . XMLStrFormat($value) . '</' . $tag . '>';
}

/**
 * Report last my SQL error
 *
 * @deprecated 04/22/2023
 */
function add_last_sql_error($functionname, $projectid = 0, $buildid = 0, $resourcetype = 0, $resourceid = 0): void
{
    $pdo_error = pdo_error();
    if (strlen($pdo_error) > 0) {
        add_log('SQL error: ' . $pdo_error, $functionname, LOG_ERR, $projectid, $buildid, $resourcetype, $resourceid);
        $text = "SQL error in $functionname():" . $pdo_error . '<br>';
        echo $text;
    }
}

/**
 * Set the CDash version number in the database
 */
function setVersion(): void
{
    $config = Config::getInstance();
    $db = Database::getInstance();

    $major = $config->get('CDASH_VERSION_MAJOR');
    $minor = $config->get('CDASH_VERSION_MINOR');
    $patch = $config->get('CDASH_VERSION_PATCH');

    $stmt = $db->query('SELECT major FROM version');
    $version = [$major, $minor, $patch];

    if (pdo_num_rows($stmt) == 0) {
        $sql = 'INSERT INTO version (major, minor, patch) VALUES (?, ?, ?)';
    } else {
        $sql = 'UPDATE version SET major=?, minor=?, patch=?';
    }

    $stmt = $db->prepare($sql);
    $db->execute($stmt, $version);
}

/**
 * TODO: (williamjallen) This function's return type is excessively complex and makes it
 *       difficult to handle.
 *
 * Return true if the user is allowed to see the page
 */
function checkUserPolicy($projectid, $onlyreturn = 0): bool|RedirectResponse|Response
{
    if (!is_numeric($projectid)) {
        return response('Insufficient data to determine access');
    }

    // If the projectid is 0 only admin can access the page.
    if ($projectid == 0) {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->IsAdmin()) {
                return true;
            }
        }
    } else {
        $project = new Project();
        $project->Id = $projectid;
        $project->Fill();

        if (Gate::allows('view-project', $project)) {
            return true;
        }
    }

    if ($onlyreturn) {
        return false;
    }

    if (!Auth::check()) {
        session(['url.intended' => url()->current()]);
        return redirect()->route('login');
    }

    return response('You cannot access this project');
}

/**
 * Get the build id from stamp, name and buildname
 */
function get_build_id(string $buildname, string $stamp, int $projectid, string $sitename): int
{
    $db = Database::getInstance();
    $build = $db->executePreparedSingleRow('
                 SELECT build.id AS id
                 FROM build, site
                 WHERE
                     build.name=?
                     AND build.stamp=?
                     AND build.projectid=?
                     AND build.siteid=site.id
                     AND site.name=?
                 ORDER BY build.id DESC
             ', [$buildname, $stamp, $projectid, $sitename]);
    if (!empty($build)) {
        return intval($build['id']);
    }
    return -1;
}

/**
 * Get the project id from the project name
 */
function get_project_id($projectname): int
{
    $service = ServiceContainer::getInstance();
    $project = $service->get(Project::class);
    $project->Name = $projectname;
    if ($project->GetIdByName()) {
        return intval($project->Id);
    }
    return -1;
}

/**
 * Get the project name from the project id
 */
function get_project_name($projectid): string
{
    if (!isset($projectid) || !is_numeric($projectid)) {
        throw new InvalidArgumentException('Invalid Project ID');
    }

    $db = Database::getInstance();
    $project = $db->executePreparedSingleRow('SELECT name FROM project WHERE id=?', [intval($projectid)]);
    if (!empty($project)) {
        return $project['name'];
    }
    return 'NA';
}

/**
 * add a user to a site
 */
function add_site2user(int $siteid, int $userid): void
{
    $db = Database::getInstance();
    $site2user = $db->executePrepared('SELECT * FROM site2user WHERE siteid=? AND userid=?', [intval($siteid), intval($userid)]);
    if (!empty($site2user)) {
        $db->executePrepared('INSERT INTO site2user (siteid, userid) VALUES (?, ?)', [$siteid, $userid]);
        add_last_sql_error('add_site2user');
    }
}

/**
 * remove a user from a site
 */
function remove_site2user(int $siteid, int $userid): void
{
    $db = Database::getInstance();
    $db->executePrepared('DELETE FROM site2user WHERE siteid=? AND userid=?', [$siteid, $userid]);
    add_last_sql_error('remove_site2user');
}

/**
 * Update a site
 */
function update_site($siteid, $name,
    $processoris64bits,
    $processorvendor,
    $processorvendorid,
    $processorfamilyid,
    $processormodelid,
    $processorcachesize,
    $numberlogicalcpus,
    $numberphysicalcpus,
    $totalvirtualmemory,
    $totalphysicalmemory,
    $logicalprocessorsperphysical,
    $processorclockfrequency,
    $description, $ip, $latitude, $longitude, $nonewrevision = false,
    $outoforder = 0)
{
    require_once 'include/pdo.php';

    // Security checks
    if (!is_numeric($siteid)) {
        return;
    }

    $db = Database::getInstance();

    // TODO: (williamjallen) Refactor this to eliminate the messy usage of the $$ operator below
    $latitude = pdo_real_escape_string($latitude);
    $longitude = pdo_real_escape_string($longitude);
    $outoforder = pdo_real_escape_string($outoforder);
    $ip = pdo_real_escape_string($ip);
    $name = pdo_real_escape_string($name);
    $processoris64bits = pdo_real_escape_string($processoris64bits);
    $processorvendor = pdo_real_escape_string($processorvendor);
    $processorvendorid = pdo_real_escape_string($processorvendorid);
    $processorfamilyid = pdo_real_escape_string($processorfamilyid);
    $processormodelid = pdo_real_escape_string($processormodelid);
    $processorcachesize = pdo_real_escape_string($processorcachesize);
    $numberlogicalcpus = pdo_real_escape_string($numberlogicalcpus);
    $numberphysicalcpus = pdo_real_escape_string($numberphysicalcpus);
    $totalvirtualmemory = round(pdo_real_escape_string($totalvirtualmemory));
    $totalphysicalmemory = round(pdo_real_escape_string($totalphysicalmemory));
    $logicalprocessorsperphysical = round(pdo_real_escape_string($logicalprocessorsperphysical));
    $processorclockfrequency = round(pdo_real_escape_string($processorclockfrequency));
    $description = pdo_real_escape_string($description);

    // Update the basic information first
    $db->executePrepared('
        UPDATE site
        SET name=?, ip=? latitude=?, longitude=?, outoforder=?
        WHERE id=?
    ', [$name, $ip, $latitude, $longitude, $outoforder, $siteid]);

    add_last_sql_error('update_site');

    $names = array();
    $names[] = 'processoris64bits';
    $names[] = 'processorvendor';
    $names[] = 'processorvendorid';
    $names[] = 'processorfamilyid';
    $names[] = 'processormodelid';
    $names[] = 'processorcachesize';
    $names[] = 'numberlogicalcpus';
    $names[] = 'numberphysicalcpus';
    $names[] = 'totalvirtualmemory';
    $names[] = 'totalphysicalmemory';
    $names[] = 'logicalprocessorsperphysical';
    $names[] = 'processorclockfrequency';
    $names[] = 'description';

    // Check that we have a valid input
    $isinputvalid = 0;
    foreach ($names as $name) {
        if ($$name != 'NA' && strlen($$name) > 0) {
            $isinputvalid = 1;
            break;
        }
    }

    if (!$isinputvalid) {
        return;
    }

    // Check if we have valuable information and the siteinformation doesn't exist
    $newrevision2 = false;
    $query = $db->executePreparedSingleRow('
                 SELECT *
                 FROM siteinformation
                 WHERE siteid=?
                 ORDER BY timestamp DESC
                 LIMIT 1
             ', [$siteid]);
    if (empty($query)) {
        $noinformation = 1;
        foreach ($names as $name) {
            if ($$name != 'NA' && strlen($$name) > 0) {
                $nonewrevision = false;
                $newrevision2 = true;
                $noinformation = 0;
                break;
            }
        }
        if ($noinformation) {
            return; // we have nothing to add
        }
    } else {
        // Check if the information are different from what we have in the database, then that means
        // the system has been upgraded and we need to create a new revision
        foreach ($names as $name) {
            if ($$name != 'NA' && $query[$name] != $$name && strlen($$name) > 0) {
                // Take care of rounding issues
                if (is_numeric($$name)) {
                    if (round($$name) != $query[$name]) {
                        $newrevision2 = true;
                        break;
                    }
                } else {
                    $newrevision2 = true;
                    break;
                }
            }
        }
    }

    if ($newrevision2 && !$nonewrevision) {
        $now = gmdate(FMT_DATETIME);
        $sql = 'INSERT INTO siteinformation(siteid,timestamp';
        foreach ($names as $name) {
            if ($$name != 'NA' && strlen($$name) > 0) {
                $sql .= ", $name";
            }
        }

        $prepared_values = [$siteid, $now];
        $sql .= ') VALUES(?, ?';
        foreach ($names as $name) {
            if ($$name != 'NA' && strlen($$name) > 0) {
                $sql .= ', ?';
                $prepared_values[] = $$name;
            }
        }
        $sql .= ')';
        $db->executePrepared($sql, $prepared_values);
        add_last_sql_error('update_site', $sql);
    } else {
        $sql = 'UPDATE siteinformation SET ';
        $prepared_values = [];
        $i = 0;
        foreach ($names as $name) {
            if ($$name != 'NA' && strlen($$name) > 0) {
                if ($i > 0) {
                    $sql .= ',';
                }
                $sql .= " $name=?";
                $prepared_values[] = $$name;
                $i++;
            }
        }

        $sql .= " WHERE siteid=? AND timestamp=?";
        $prepared_values[] = $siteid;
        $prepared_values[] = $query['timestamp'];

        $db->executePrepared($sql, $prepared_values);
        add_last_sql_error('update_site', $sql);
    }
}

/**
 * Get the geolocation from IP address
 */
function get_geolocation($ip)
{
    $location = array();

    $lat = '';
    $long = '';

    $config = Config::getInstance();

    if (config('cdash.geolocate_ip_addresses')) {
        // Ask hostip.info for geolocation
        $url = 'http://api.hostip.info/get_html.php?ip=' . $ip . '&position=true';

        if (function_exists('curl_init')) {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_TIMEOUT, 5); // if we cannot get the geolocation in 5 seconds we quit
            ob_start();
            curl_exec($curl);
            $httpReply = ob_get_contents();
            ob_end_clean();
            curl_close($curl);
        } elseif (ini_get('allow_url_fopen')) {
            $options = array('http' => array('timeout' => 5.0));
            $context = stream_context_create($options);
            $httpReply = file_get_contents($url, false, $context);
        } else {
            $httpReply = '';
        }

        $pos = strpos($httpReply, 'Latitude: ');
        if ($pos !== false) {
            $pos2 = strpos($httpReply, "\n", $pos);
            $lat = substr($httpReply, $pos + 10, $pos2 - $pos - 10);
        }

        $pos = strpos($httpReply, 'Longitude: ');
        if ($pos !== false) {
            $pos2 = strpos($httpReply, "\n", $pos);
            $long = substr($httpReply, $pos + 11, $pos2 - $pos - 11);
        }
    }

    $location['latitude'] = '';
    $location['longitude'] = '';

    // Sanity check
    if (strlen($lat) > 0 && strlen($long) > 0
        && $lat[0] != ' ' && $long[0] != ' '
    ) {
        $location['latitude'] = $lat;
        $location['longitude'] = $long;
    } else {
        // Check if we have a list of default locations

        foreach ($config->get('CDASH_DEFAULT_IP_LOCATIONS') as $defaultlocation) {
            $defaultip = $defaultlocation['IP'];
            if (preg_match('#^' . strtr(preg_quote($defaultip, '#'), array('\*' => '.*', '\?' => '.')) . '$#i', $ip)) {
                $location['latitude'] = $defaultlocation['latitude'];
                $location['longitude'] = $defaultlocation['longitude'];
            }
        }
    }
    return $location;
}

/**
 * remove all builds for a project
 */
function remove_project_builds($projectid): void
{
    if (!is_numeric($projectid)) {
        return;
    }

    $build = DB::select('SELECT id FROM build WHERE projectid=?', [intval($projectid)]);

    $buildids = array();
    foreach ($build as $build_array) {
        $buildids[] = (int) $build_array->id;
    }
    remove_build_chunked($buildids);
}

/**
 * Remove all related inserts for a given build or any build in an array of builds
 */
function remove_build($buildid)
{
    // TODO: (williamjallen) much of this work could be done on the DB side automatically by setting up
    //       proper foreign-key relationships between between entities, and using the DB's cascade functionality.
    //       For complex cascades, custom SQL functions can be written.

    if (!is_array($buildid)) {
        $buildid = [$buildid];
    }

    $buildids = [];
    foreach ($buildid as $b) {
        if (!is_numeric($b)) {
            throw new InvalidArgumentException('Invalid Build ID');
        }
        $buildids[] = intval($b);
    }

    $db = Database::getInstance();
    $buildid_prepare_array = $db->createPreparedArray(count($buildids));

    DB::delete("DELETE FROM build2group WHERE buildid IN $buildid_prepare_array", $buildids);
    DB::delete("DELETE FROM builderror WHERE buildid IN $buildid_prepare_array", $buildids);
    DB::delete("DELETE FROM buildemail WHERE buildid IN $buildid_prepare_array", $buildids);
    DB::delete("DELETE FROM buildfile WHERE buildid IN $buildid_prepare_array", $buildids);

    DB::delete("DELETE FROM buildinformation WHERE buildid IN $buildid_prepare_array", $buildids);
    DB::delete("DELETE FROM builderrordiff WHERE buildid IN $buildid_prepare_array", $buildids);
    DB::delete("DELETE FROM buildproperties WHERE buildid IN $buildid_prepare_array", $buildids);

    DB::delete("DELETE FROM configureerrordiff WHERE buildid IN $buildid_prepare_array", $buildids);
    DB::delete("DELETE FROM coveragesummarydiff WHERE buildid IN $buildid_prepare_array", $buildids);
    DB::delete("DELETE FROM testdiff WHERE buildid IN $buildid_prepare_array", $buildids);
    DB::delete("DELETE FROM buildtesttime WHERE buildid IN $buildid_prepare_array", $buildids);
    DB::delete("DELETE FROM summaryemail WHERE buildid IN $buildid_prepare_array", $buildids);
    DB::delete("DELETE FROM related_builds WHERE buildid IN $buildid_prepare_array", $buildids);
    DB::delete("DELETE FROM related_builds WHERE relatedid IN $buildid_prepare_array", $buildids);
    DB::delete("DELETE FROM pending_submissions WHERE buildid IN $buildid_prepare_array", $buildids);

    // Remove the buildfailureargument
    $buildfailureids = [];
    $buildfailure = DB::select("SELECT id FROM buildfailure WHERE buildid IN $buildid_prepare_array", $buildids);
    foreach ($buildfailure as $buildfailure_array) {
        $buildfailureids[] = intval($buildfailure_array->id);
    }
    if (count($buildfailureids) > 0) {
        $buildfailure_prepare_array = $db->createPreparedArray(count($buildfailureids));
        DB::delete("DELETE FROM buildfailure2argument WHERE buildfailureid IN $buildfailure_prepare_array", $buildfailureids);
        DB::delete("DELETE FROM label2buildfailure WHERE buildfailureid IN $buildfailure_prepare_array", $buildfailureids);
    }

    // Delete buildfailuredetails that are only used by builds that are being
    // deleted.
    DB::delete("
        DELETE FROM buildfailuredetails WHERE id IN (
            SELECT a.detailsid
            FROM buildfailure AS a
            LEFT JOIN buildfailure AS b ON (
                a.detailsid=b.detailsid
                AND b.buildid NOT IN $buildid_prepare_array
            )
            WHERE a.buildid IN $buildid_prepare_array
            GROUP BY a.detailsid
            HAVING count(b.detailsid)=0
        )
    ", array_merge($buildids, $buildids));

    // Remove the buildfailure.
    DB::delete("DELETE FROM buildfailure WHERE buildid IN $buildid_prepare_array", $buildids);

    // Delete the configure if not shared.
    $build2configure = DB::select("
                           SELECT a.configureid
                           FROM build2configure AS a
                           LEFT JOIN build2configure AS b ON (
                               a.configureid=b.configureid
                               AND b.buildid NOT IN $buildid_prepare_array
                           )
                           WHERE a.buildid IN $buildid_prepare_array
                           GROUP BY a.configureid
                           HAVING count(b.configureid)=0
                       ", array_merge($buildids, $buildids));

    $configureids = [];
    foreach ($build2configure as $build2configure_array) {
        // It is safe to delete this configure because it is only used
        // by builds that are being deleted.
        $configureids[] = intval($build2configure_array->configureid);
    }
    if (count($configureids) > 0) {
        $configureids_prepare_array = $db->createPreparedArray(count($configureids));
        DB::delete("DELETE FROM configure WHERE id IN $configureids_prepare_array", $configureids);
        DB::delete("DELETE FROM configureerror WHERE configureid IN $configureids_prepare_array", $configureids);
    }
    DB::delete("DELETE FROM build2configure WHERE buildid IN $buildid_prepare_array", $buildids);

    // coverage files are kept unless they are shared
    DB::delete("
        DELETE FROM coveragefile
        WHERE id IN (
            SELECT a.fileid
            FROM coverage AS a
            LEFT JOIN coverage AS b ON (
                a.fileid=b.fileid
                AND b.buildid NOT IN $buildid_prepare_array
            )
            WHERE a.buildid IN $buildid_prepare_array
            GROUP BY a.fileid
            HAVING count(b.fileid)=0
        )
    ", array_merge($buildids, $buildids));

    DB::delete("DELETE FROM label2coveragefile WHERE buildid IN $buildid_prepare_array", $buildids);
    DB::delete("DELETE FROM coverage WHERE buildid IN $buildid_prepare_array", $buildids);
    DB::delete("DELETE FROM coveragefilelog WHERE buildid IN $buildid_prepare_array", $buildids);
    DB::delete("DELETE FROM coveragesummary WHERE buildid IN $buildid_prepare_array", $buildids);

    // dynamicanalysisdefect
    $dynamicanalysis = DB::select("
                           SELECT id
                           FROM dynamicanalysis
                           WHERE buildid IN $buildid_prepare_array
                       ", $buildids);

    $dynids = [];
    foreach ($dynamicanalysis as $dynamicanalysis_array) {
        $dynids[] = intval($dynamicanalysis_array->id);
    }

    if (count($dynids) > 0) {
        $dynids_prepare_array = $db->createPreparedArray(count($dynids));
        DB::delete("DELETE FROM dynamicanalysisdefect WHERE dynamicanalysisid IN $dynids_prepare_array", $dynids);
        DB::delete("DELETE FROM label2dynamicanalysis WHERE dynamicanalysisid IN $dynids_prepare_array", $dynids);
    }
    DB::delete("DELETE FROM dynamicanalysis WHERE buildid IN $buildid_prepare_array", $buildids);
    DB::delete("DELETE FROM dynamicanalysissummary WHERE buildid IN $buildid_prepare_array", $buildids);

    // Delete the note if not shared
    DB::delete("
        DELETE FROM note WHERE id IN (
            SELECT a.noteid
            FROM build2note AS a
            LEFT JOIN build2note AS b ON (
                a.noteid=b.noteid
                AND b.buildid NOT IN $buildid_prepare_array
            )
            WHERE a.buildid IN $buildid_prepare_array
            GROUP BY a.noteid
            HAVING count(b.noteid)=0
        )
    ", array_merge($buildids, $buildids));

    DB::delete("DELETE FROM build2note WHERE buildid IN $buildid_prepare_array", $buildids);

    // Delete the update if not shared
    $build2update = DB::select("
                        SELECT a.updateid
                        FROM build2update AS a
                        LEFT JOIN build2update AS b ON (
                            a.updateid=b.updateid
                            AND b.buildid NOT IN $buildid_prepare_array
                        )
                        WHERE a.buildid IN $buildid_prepare_array
                        GROUP BY a.updateid
                        HAVING count(b.updateid)=0
                    ", array_merge($buildids, $buildids));

    $updateids = [];
    foreach ($build2update as $build2update_array) {
        // Update is not shared we delete
        $updateids[] = intval($build2update_array->updateid);
    }

    if (count($updateids) > 0) {
        $updateids_prepare_array = $db->createPreparedArray(count($updateids));
        DB::delete("DELETE FROM buildupdate WHERE id IN $updateids_prepare_array", $updateids);
        DB::delete("DELETE FROM updatefile WHERE updateid IN $updateids_prepare_array", $updateids);
    }
    DB::delete("DELETE FROM build2update WHERE buildid IN $buildid_prepare_array", $buildids);

    // Delete tests and testoutputs that are not shared.
    // First find all the tests and testoutputs from builds that are about to be deleted.
    $b2t_result = DB::select("
                      SELECT testid, outputid
                      FROM build2test
                      WHERE buildid IN $buildid_prepare_array
                  ", $buildids);

    $all_testids = [];
    $all_outputids = [];
    foreach ($b2t_result as $b2t_row) {
        $all_testids[] = intval($b2t_row->testid);
        $all_outputids[] = intval($b2t_row->outputid);
    }
    $all_testids = array_unique($all_testids);
    $all_outputids = array_unique($all_outputids);

    if (!empty($all_testids)) {
        // Next identify tests from this list that should be preserved
        // because they are shared with builds that are not about to be deleted.
        DB::delete("
            DELETE FROM test
            WHERE
                id IN (
                    SELECT testid
                    FROM build2test
                    WHERE buildid IN $buildid_prepare_array
                )
                AND id NOT IN (
                    SELECT testid
                    FROM build2test
                    WHERE
                        buildid NOT IN $buildid_prepare_array
                )
        ", array_merge($buildids, $buildids));
    }

    // Delete un-shared testoutput rows.
    if (!empty($all_outputids)) {
        // Next identify tests from this list that should be preserved
        // because they are shared with builds that are not about to be deleted.
        $all_outputids_prepare_array = $db->createPreparedArray(count($all_outputids));
        $save_test_result = DB::select("
                                SELECT DISTINCT outputid
                                FROM build2test
                                WHERE
                                    outputid IN $all_outputids_prepare_array
                                    AND buildid NOT IN $buildid_prepare_array
                            ", array_merge($all_outputids, $buildids));
        $testoutputs_to_save = [];
        foreach ($save_test_result as $save_test_row) {
            $testoutputs_to_save[] = intval($save_test_row->outputid);
        }

        // Use array_diff to get the list of tests that should be deleted.
        $testoutputs_to_delete = array_diff($all_outputids, $testoutputs_to_save);
        if (!empty($testoutputs_to_delete)) {
            delete_rows_chunked('DELETE FROM testmeasurement WHERE outputid IN ', $testoutputs_to_delete);
            delete_rows_chunked('DELETE FROM testoutput WHERE id IN ', $testoutputs_to_delete);

            $testoutputs_to_delete_prepare_array = $db->createPreparedArray(count($testoutputs_to_delete));
            // Check if the images for the test are not shared
            $test2image = DB::select("
                              SELECT a.imgid
                              FROM test2image AS a
                              LEFT JOIN test2image AS b ON (
                                  a.imgid=b.imgid
                                  AND b.outputid NOT IN $testoutputs_to_delete_prepare_array
                              )
                              WHERE a.outputid IN $testoutputs_to_delete_prepare_array
                              GROUP BY a.imgid
                              HAVING count(b.imgid)=0
                          ", array_merge($testoutputs_to_delete, $testoutputs_to_delete));

            $imgids = [];
            foreach ($test2image as $test2image_array) {
                $imgids[] = intval($test2image_array->imgid);
            }

            if (count($imgids) > 0) {
                $imgids_prepare_array = $db->createPreparedArray(count($imgids));
                DB::delete("DELETE FROM image WHERE id IN $imgids_prepare_array", $imgids);
            }
            delete_rows_chunked('DELETE FROM test2image WHERE outputid IN ', $testoutputs_to_delete);
        }
    }

    DB::delete("DELETE FROM label2test WHERE buildid IN $buildid_prepare_array", $buildids);
    DB::delete("DELETE FROM build2test WHERE buildid IN $buildid_prepare_array", $buildids);

    // Delete the uploaded files if not shared
    $build2uploadfiles = DB::select("
                             SELECT a.fileid
                             FROM build2uploadfile AS a
                             LEFT JOIN build2uploadfile AS b ON (
                                 a.fileid=b.fileid
                                 AND b.buildid NOT IN $buildid_prepare_array
                             )
                             WHERE a.buildid IN $buildid_prepare_array
                             GROUP BY a.fileid
                             HAVING count(b.fileid)=0
                         ", array_merge($buildids, $buildids));

    $fileids = [];
    foreach ($build2uploadfiles as $build2uploadfile_array) {
        $fileid = intval($build2uploadfile_array->fileid);
        $fileids[] = $fileid;
        unlink_uploaded_file($fileid);
    }

    if (count($fileids) > 0) {
        $fileids_prepare_array = $db->createPreparedArray(count($fileids));
        DB::delete("DELETE FROM uploadfile WHERE id IN $fileids_prepare_array", $fileids);
        DB::delete("DELETE FROM build2uploadfile WHERE fileid IN $fileids_prepare_array", $fileids);
    }
    DB::delete("DELETE FROM build2uploadfile WHERE buildid IN $buildid_prepare_array", $buildids);

    // Delete the subproject
    DB::delete("DELETE FROM subproject2build WHERE buildid IN $buildid_prepare_array", $buildids);

    // Delete the labels
    DB::delete("DELETE FROM label2build WHERE buildid IN $buildid_prepare_array", $buildids);

    // Remove any children of these builds.
    // In order to avoid making the list of builds to delete too large
    // we delete them in batches (one batch per parent).
    foreach ($buildids as $parentid) {
        $child_result = DB::select('SELECT id FROM build WHERE parentid=?', [intval($parentid)]);

        $childids = [];
        foreach ($child_result as $child_array) {
            $childids[] = intval($child_array->id);
        }
        if (!empty($childids)) {
            remove_build($childids);
        }
    }

    // Only delete the buildid at the end so that no other build can get it in the meantime
    DB::delete("DELETE FROM build WHERE id IN $buildid_prepare_array", $buildids);

    add_last_sql_error('remove_build');
}

/**
 * Call remove_build() in batches of 100.
 */
function remove_build_chunked($buildid): void
{
    if (!is_array($buildid)) {
        remove_build($buildid);
    }
    foreach (array_chunk($buildid, 100) as $chunk) {
        remove_build($chunk);
    }
}

/**
 * Chunk up DELETE queries into batches of 100.
 */
function delete_rows_chunked(string $query, array $ids): void
{
    foreach (array_chunk($ids, 100) as $chunk) {
        $chunk_prepared_array = Database::getInstance()->createPreparedArray(count($chunk));
        DB::delete("$query $chunk_prepared_array", $chunk);
        // Sleep for a microsecond to give other processes a chance.
        usleep(1);
    }
}

/**
 * Deletes the symlink to an uploaded file.  If it is the only symlink to that content,
 * it will also delete the content itself.
 * Returns the number of bytes deleted from disk (0 for symlink, otherwise the size of the content)
 */
function unlink_uploaded_file($fileid)
{
    $config = Config::getInstance();
    $pdo = get_link_identifier()->getPdo();
    $stmt = $pdo->prepare(
        'SELECT sha1sum, filename, filesize FROM uploadfile
        WHERE id = ? AND isurl = 0');

    if (!pdo_execute($stmt, [$fileid])) {
        return 0;
    }
    $row = $stmt->fetch();
    if (!$row) {
        return 0;
    }

    $sha1sum = $row['sha1sum'];
    $symlinkname = $row['filename'];
    $filesize = $row['filesize'];

    $shareCount = 0;
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM uploadfile
        WHERE sha1sum = :sha1sum AND id != :fileid');
    $stmt->bindParam(':sha1sum', $sha1sum);
    $stmt->bindParam(':fileid', $fileid);
    if (pdo_execute($stmt)) {
        $shareCount = $stmt->fetchColumn();
    }

    if ($shareCount == 0) {
        //If only one name maps to this content

        // Delete the content and symlink
        rmdirr($config->get('CDASH_UPLOAD_DIRECTORY') . '/' . $sha1sum);
        return $filesize;
    } else {
        // Just delete the symlink, keep the content around
        cdash_unlink($config->get('CDASH_UPLOAD_DIRECTORY') . '/' . $sha1sum . '/' . $symlinkname);
        return 0;
    }
}

/**
 * Recursive version of rmdir()
 */
function rmdirr($dir): void
{
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != '.' && $object != '..') {
                if (is_dir($dir . '/' . $object)) {
                    rmdirr($dir . '/' . $object);
                } else {
                    cdash_unlink($dir . '/' . $object);
                }
            }
        }
        reset($objects);
        rmdir($dir);
    }
}

/**
 * Get year from formatted date
 */
function date2year(string $date): string
{
    return substr($date, 0, 4);
}

/**
 * Get month from formatted date
 */
function date2month(string $date): string
{
    return is_numeric(substr($date, 4, 1)) ? substr($date, 4, 2) : substr($date, 5, 2);
}

/**
 * Get day from formatted date
 */
function date2day(string $date): string
{
    return is_numeric(substr($date, 4, 1)) ? substr($date, 6, 2) : substr($date, 8, 2);
}

/**
 * Get hour from formatted time
 */
function time2hour(string $time): string
{
    return substr($time, 0, 2);
}

/**
 * Get minute from formatted time
 */
function time2minute(string $time): string
{
    return is_numeric(substr($time, 2, 1)) ? substr($time, 2, 2) : substr($time, 3, 2);
}

/**
 * Get second from formatted time
 */
function time2second(string $time): string
{
    return is_numeric(substr($time, 2, 1)) ? substr($time, 4, 2) : substr($time, 6, 2);
}

/** Get dates
 * today: the *starting* timestamp of the current dashboard
 * previousdate: the date in Y-m-d format of the previous dashboard
 * nextdate: the date in Y-m-d format of the next dashboard
 */
function get_dates($date, $nightlytime): array
{
    // Convert $date parameter to expected format.
    $date = date(FMT_DATE, strtotime($date));

    $nightlytime = strtotime($nightlytime, strtotime($date));
    $nightlyhour = intval(date('H', $nightlytime));
    $nightlyminute = intval(date('i', $nightlytime));
    $nightlysecond = intval(date('s', $nightlytime));

    if (strlen($date) === 0) {
        $date = date(FMT_DATE); // the date is always the date of the server

        if (date(FMT_TIME) > date(FMT_TIME, $nightlytime)) {
            $date = date(FMT_DATE, time() + 3600 * 24); //next day
        }
    } else {
        // If the $nightlytime is in the morning it's actually the day after
        if (date(FMT_TIME, $nightlytime) < '12:00:00') {
            $date = date(FMT_DATE, strtotime($date) + 3600 * 24); // previous date
        }
    }

    $today = mktime($nightlyhour, $nightlyminute, $nightlysecond, intval(date2month($date)), intval(date2day($date)), intval(date2year($date))) - 3600 * 24; // starting time

    // If the $nightlytime is in the morning it's actually the day after
    if (date(FMT_TIME, $nightlytime) < '12:00:00') {
        $date = date(FMT_DATE, strtotime($date) - 3600 * 24); // previous date
    }

    $todaydate = mktime(0, 0, 0, intval(date2month($date)), intval(date2day($date)), intval(date2year($date)));
    $previousdate = date(FMT_DATE, $todaydate - 3600 * 24);
    $nextdate = date(FMT_DATE, $todaydate + 3600 * 24);
    return array($previousdate, $today, $nextdate, $date);
}

function has_next_date($date, $currentstarttime): bool
{
    return (
        isset($date) &&
        strlen($date) >= 8 &&
        date(FMT_DATE, $currentstarttime) < date(FMT_DATE));
}

/**
 * Get the logo id
 */
function getLogoID(int $projectid): int
{
    // assume the caller already connected to the database
    $db = Database::getInstance();
    $result = $db->executePreparedSingleRow('SELECT imageid FROM project WHERE id=?', [$projectid]);
    if (empty($result)) {
        return 0;
    }

    return intval($result['imageid']);
}

/**
 * make_cdash_url ensures that a url begins with a known url protocol identifier
 */
function make_cdash_url(string $url): string
{
    // By default, same as the input
    $cdash_url = $url;

    // Unless the input does *not* start with a known protocol identifier...
    // If it does not start with http or https already, then prepend "http://"
    // to the input.
    //
    $npos = strpos($url, 'http://');
    if ($npos === false) {
        $npos2 = strpos($url, 'https://');
        if ($npos2 === false) {
            $cdash_url = 'http://' . $url;
        }
    }
    return $cdash_url;
}

/**
 * Get the previous build id dynamicanalysis
 */
function get_previous_buildid_dynamicanalysis(int $projectid, int $siteid, string $buildtype, string $buildname, string $starttime): int
{
    $db = Database::getInstance();
    $previousbuild = $db->executePreparedSingleRow('
                         SELECT build.id
                         FROM build, dynamicanalysis
                         WHERE
                             build.siteid=?
                             AND build.type=?
                             AND build.name=?
                             AND build.projectid=?
                             AND build.starttime<?
                             AND dynamicanalysis.buildid=build.id
                         ORDER BY build.starttime DESC
                         LIMIT 1
                     ', [$siteid, $buildtype, $buildname, $projectid, $starttime]);

    if (!empty($previousbuild)) {
        return intval($previousbuild['id']);
    }
    return 0;
}

/**
 * Get the next build id dynamicanalysis
 */
function get_next_buildid_dynamicanalysis(int $projectid, int $siteid, string $buildtype, string $buildname, string $starttime): int
{
    $db = Database::getInstance();
    $nextbuild = $db->executePreparedSingleRow('
                     SELECT build.id
                     FROM build, dynamicanalysis
                     WHERE
                         build.siteid=?
                         AND build.type=?
                         AND build.name=?
                         AND build.projectid=?
                         AND build.starttime>?
                         AND dynamicanalysis.buildid=build.id
                     ORDER BY build.starttime ASC
                     LIMIT 1
                 ', [$siteid, $buildtype, $buildname, $projectid, $starttime]);

    if (!empty($nextbuild)) {
        return intval($nextbuild['id']);
    }
    return 0;
}

/**
 * Get the last build id dynamicanalysis
 */
function get_last_buildid_dynamicanalysis(int $projectid, int $siteid, string $buildtype, string $buildname): int
{
    $db = Database::getInstance();
    $nextbuild = $db->executePreparedSingleRow('
                     SELECT build.id
                     FROM build, dynamicanalysis
                     WHERE
                         build.siteid=?
                         AND build.type=?
                         AND build.name=?
                         AND build.projectid=?
                         AND dynamicanalysis.buildid=build.id
                     ORDER BY build.starttime DESC
                     LIMIT 1
                 ', [$siteid, $buildtype, $buildname, $projectid]);

    if (!empty($nextbuild)) {
        return $nextbuild['id'];
    }
    return 0;
}

function get_cdash_dashboard_xml_by_name(string $projectname, $date): string
{
    $projectid = get_project_id($projectname);
    if ($projectid === -1) {
        return '';
    }

    $default = [
        'cvsurl' => 'unknown',
        'bugtrackerurl' => 'unknown',
        'documentationurl' => 'unknown',
        'googletracker' => 'unknonw',
        'name' => $projectname,
        'nightlytime' => '00:00:00',
    ];

    $db = Database::getInstance();

    $sql = "SELECT * FROM project WHERE id=:id";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':id', $projectid);
    $db->execute($stmt);
    $result = $stmt ? $stmt->fetch() : [];

    $project_array = array_merge($default, $result);

    list($previousdate, $currentstarttime, $nextdate) = get_dates($date, $project_array['nightlytime']);

    $xml = '<dashboard>
  <datetime>' . date('l, F d Y H:i:s', time()) . '</datetime>
  <date>' . $date . '</date>
  <unixtimestamp>' . $currentstarttime . '</unixtimestamp>
  <startdate>' . date('l, F d Y H:i:s', $currentstarttime) . '</startdate>
  <svn>' . make_cdash_url(htmlentities($project_array['cvsurl'])) . '</svn>
  <bugtracker>' . make_cdash_url(htmlentities($project_array['bugtrackerurl'])) . '</bugtracker>
  <googletracker>' . htmlentities($project_array['googletracker']) . '</googletracker>
  <documentation>' . make_cdash_url(htmlentities($project_array['documentationurl'])) . '</documentation>
  <projectid>' . $projectid . '</projectid>
  <projectname>' . $project_array['name'] . '</projectname>
  <projectname_encoded>' . urlencode($project_array['name']) . '</projectname_encoded>
  <projectpublic>' . $project_array['public'] . '</projectpublic>
  <previousdate>' . $previousdate . '</previousdate>
  <nextdate>' . $nextdate . '</nextdate>
  <logoid>' . getLogoID(intval($projectid)) . '</logoid>';

    if (empty($project_array['homeurl'])) {
        $xml .= '<home>index.php?project=' . urlencode($project_array['name']) . '</home>';
    } else {
        $xml .= '<home>' . make_cdash_url(htmlentities($project_array['homeurl'])) . '</home>';
    }

    $xml .= '</dashboard>';

    if (Auth::check()) {
        $user = Auth::user();
        $xml .= '<user>';
        $xml .= add_XML_value('id', $user->id);

        // Is the user super administrator?
        $xml .= add_XML_value('admin', $user->admin);

        // Is the user administrator of the project

        $userproject = new UserProject();
        $userproject->UserId = $user->id;
        $userproject->ProjectId = $projectid;
        $userproject->FillFromUserId();
        $xml .= add_XML_value('projectrole', $userproject->Role);

        $xml .= '</user>';
    }
    return $xml;
}

/**
 * Quote SQL identifier
 */
function qid($id)
{
    if (!config('database.default') || (config('database.default') == 'mysql')) {
        return "`$id`";
    } elseif (config('database.default') == 'pgsql') {
        return "\"$id\"";
    } else {
        return $id;
    }
}

/**
 * Quote SQL interval specifier
 */
function qiv($iv)
{
    if (config('database.default') == 'pgsql') {
        return "'$iv'";
    } else {
        return $iv;
    }
}

/**
 * Quote SQL number
 */
function qnum($num)
{
    if (!config('database.default') || (config('database.default') == 'mysql')) {
        return "'$num'";
    } elseif (config('database.default') == 'pgsql') {
        return $num != '' ? $num : '0';
    } else {
        return $num;
    }
}

/**
 * Return the list of site maintainers for a given project
 */
function find_site_maintainers(int $projectid): array
{
    $db = Database::getInstance();

    // Get the registered user first
    $site2user = $db->executePrepared('
                     SELECT site2user.userid
                     FROM site2user, user2project
                     WHERE
                         site2user.userid=user2project.userid
                         AND user2project.projectid=?
                     ', [$projectid]);

    $userids = [];
    foreach ($site2user as $site2user_array) {
        $userids[] = intval($site2user_array['userid']);
    }

    // Then we list all the users that have been submitting in the past 48 hours
    $submittime_UTCDate = gmdate(FMT_DATETIME, time() - 3600 * 48);

    $site2project = $db->executePrepared('
                        SELECT DISTINCT userid
                        FROM site2user
                        WHERE siteid IN (
                            SELECT siteid
                            FROM build
                            WHERE
                                projectid=?
                                 AND submittime>?
                        )', [$projectid, $submittime_UTCDate]);
    foreach ($site2project as $site2project_array) {
        $userids[] = intval($site2project_array['userid']);
    }
    return array_unique($userids);
}

/**
 * Check the email category
 */
function check_email_category(string $name, int $emailcategory): bool
{
    if ($emailcategory >= 64) {
        if ($name == 'dynamicanalysis') {
            return true;
        }
        $emailcategory -= 64;
    }

    if ($emailcategory >= 32) {
        if ($name == 'test') {
            return true;
        }
        $emailcategory -= 32;
    }

    if ($emailcategory >= 16) {
        if ($name == 'error') {
            return true;
        }
        $emailcategory -= 16;
    }

    if ($emailcategory >= 8) {
        if ($name == 'warning') {
            return true;
        }
        $emailcategory -= 8;
    }

    if ($emailcategory >= 4) {
        if ($name == 'configure') {
            return true;
        }
        $emailcategory -= 4;
    }

    if ($emailcategory >= 2) {
        if ($name == 'update') {
            return true;
        }
    }
    return false;
}

/**
 * Return the byte value with proper extension
 */
function getByteValueWithExtension($value, $base = 1024): string
{
    $valueext = '';
    if ($value > $base) {
        $value /= $base;
        $value = $value;
        $valueext = 'K';
    }
    if ($value > $base) {
        $value /= $base;
        $value = $value;
        $valueext = 'M';
    }
    if ($value > $base) {
        $value /= $base;
        $value = $value;
        $valueext = 'G';
    }
    return round($value, 2) . $valueext;
}

function generate_password(int $length): string
{
    $keychars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $max = strlen($keychars) - 1;
    $key = '';
    for ($i = 0; $i < $length; $i++) {
        $key .= substr($keychars, random_int(0, $max), 1);
    }
    return $key;
}

/**
 * Check if user has specified a preference for color scheme.
 */
function get_css_file(): string
{
    $classic = 'css/cdash.css';
    $colorblind = 'css/colorblind.css';

    // Return cache-busting filenames if available.
    $css_files = glob(Config::getInstance()->get('CDASH_ROOT_DIR') . '/public/build/css/*.css');
    foreach ($css_files as $css_file) {
        $css_file = basename($css_file);
        if (strpos($css_file, 'cdash_') !== false) {
            $classic = "build/css/{$css_file}";
        } elseif (strpos($css_file, 'colorblind_') !== false) {
            $colorblind = "build/css/{$css_file}";
        }
    }

    if (array_key_exists('colorblind', $_COOKIE) && $_COOKIE['colorblind'] == 1) {
        return $colorblind;
    }
    return $classic;
}

function begin_XML_for_XSLT(): string
{
    $config = CDash\Config::getInstance();

    $css_file = get_css_file();
    $config->set('CDASH_CSS_FILE', $css_file);

    $xml = '<?xml version="1.0" encoding="UTF-8"?><cdash>';
    $xml .= add_XML_value('cssfile', $css_file);
    $xml .= add_XML_value('version', CDash\Config::getVersion());
    $xml .= add_XML_value('_token', csrf_token());

    return $xml;
}

function begin_JSON_response(): array
{
    $response = array();
    $response['version'] = CDash\Config::getVersion();

    $user_response = array();
    $userid = Auth::id();
    if ($userid) {
        $user = Auth::user();
        $user_response['admin'] = $user->admin;
    }
    $user_response['id'] = $userid;
    $response['user'] = $user_response;
    $response['querytestfilters'] = '&filtercount=1&showfilters=1&field1=status&compare1=62&value1=Passed';
    return $response;
}

/**
 * TODO: pass in project object, not just name, prevents yet another unnecessary query to db.
 */
function get_dashboard_JSON($projectname, $date, &$response)
{
    $service = ServiceContainer::getInstance();

    /** @var Project $project */
    $project = $service->create(Project::class);
    $project->FindByName($projectname);

    $project_array = [];
    $project_array['cvsurl'] = $project->Id ? $project->CvsUrl : 'unknown';
    $project_array['bugtrackerurl'] = $project->Id ? $project->BugTrackerUrl : 'unknown';
    $project_array['documentationurl'] = $project->Id ? $project->DocumentationUrl : 'unknown';
    $project_array['homeurl'] = $project->Id ? $project->HomeUrl : 'unknown';
    $project_array['googletracker'] = $project->Id ? $project->GoogleTracker : 'unknown';
    $project_array['name'] = $projectname;
    $project_array['nightlytime'] =  $project->Id ? $project->NightlyTime : '00:00:00';

    if (is_null($date)) {
        $date = date(FMT_DATE);
    }
    list($previousdate, $currentstarttime, $nextdate) = get_dates($date, $project_array['nightlytime']);

    $response['datetime'] = date('l, F d Y H:i:s', time());
    $response['date'] = $date;
    $response['unixtimestamp'] = $currentstarttime;
    $response['startdate'] = date('l, F d Y H:i:s', $currentstarttime);
    $response['currentdate'] = TestingDay::get($project, gmdate(FMT_DATETIME));
    $response['vcs'] = make_cdash_url(htmlentities($project_array['cvsurl']));
    $response['bugtracker'] = make_cdash_url(htmlentities($project_array['bugtrackerurl']));
    $response['googletracker'] = htmlentities($project_array['googletracker']);
    $response['documentation'] = make_cdash_url(htmlentities($project_array['documentationurl']));
    $response['projectid'] = $project->Id;
    $response['projectname'] = $project_array['name'];
    $response['projectname_encoded'] = urlencode($project_array['name']);
    $response['public'] = $project->Public;
    $response['previousdate'] = $previousdate;
    $response['nextdate'] = $nextdate;
    $response['logoid'] = getLogoID(intval($project->Id));
    $response['nightlytime'] = date('H:i T', strtotime($project_array['nightlytime']));
    if (empty($project_array['homeurl'])) {
        $response['home'] = 'index.php?project=' . urlencode($project_array['name']);
    } else {
        $response['home'] = make_cdash_url(htmlentities($project_array['homeurl']));
    }

    $userid = Auth::id();
    if ($userid) {
        /** @var UserProject $user_project */
        $user_project = $service->create(UserProject::class);
        $user_project->UserId = $userid;
        $user_project->ProjectId = $project->Id;
        $user_project->FillFromUserId();

        $response['projectrole'] = $user_project->Role;
        if ($response['projectrole'] > Project::SITE_MAINTAINER) {
            $response['user']['admin'] = 1;
        }
    }
    $response['user']['id'] = $userid;
}

/**
 * TODO: (williamjallen) Eliminate one of these functions. There is no reason
 *       to have both get_dashboard_JSON_by_name() and get_dashboard_JSON().
 */
function get_dashboard_JSON_by_name($projectname, $date, &$response)
{
    get_dashboard_JSON($projectname, $date, $response);
}

function get_labels_JSON_from_query_results(string $query, ?array $query_params, array &$response): void
{
    $db = Database::getInstance();
    $rows = $db->executePrepared($query, $query_params);
    if (is_array($rows) && count($rows) > 0) {
        $labels = [];
        foreach ($rows as $row) {
            $labels[] = $row['text'];
        }
        $response['labels'] = $labels;
    }
}

function compute_percentcoverage($loctested, $locuntested)
{
    $loc = $loctested + $locuntested;

    if ($loc > 0) {
        $percentcoverage = round($loctested / $loc * 100, 2);
    } else {
        $percentcoverage = 100;
    }
    return $percentcoverage;
}

/**
 * PHP won't let you delete a non-empty directory, so we first have to
 * search through it and delete each file & subdirectory that we find.
 **/
function DeleteDirectory(string $dirName): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dirName),
        RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) {
        if (in_array($file->getBasename(), array('.', '..'))) {
            continue;
        }
        if ($file->isDir()) {
            rmdir($file->getPathname());
        }
        if ($file->isFile() || $file->isLink()) {
            unlink($file->getPathname());
        }
    }
    rmdir($dirName);
}

function load_view($viewName)
{
    readfile("build/views/$viewName.html");
}

/**
 * Change data-type from string to integer or float if required.
 * If a string is detected make sure it is utf8 encoded.
 */
function cast_data_for_JSON($value)
{
    if (is_array($value)) {
        // Brutal hack to preserve hashes that happen to be all numbers
        // and start with a leading zero. These are expected to be indexed under
        // 'revision', 'priorrevision', or 'files'.
        $values_to_preserve = [];
        $keys_to_check = ['revision', 'priorrevision'];
        foreach ($keys_to_check as $key) {
            if (array_key_exists($key, $value)) {
                $values_to_preserve[$key] = $value[$key];
            }
        }
        if (array_key_exists('files', $value) && is_string($value['files']) && strlen($value['files']) === 6) {
            $values_to_preserve['files'] = $value['files'];
        }

        $value = array_map('cast_data_for_JSON', $value);

        foreach ($values_to_preserve as $k => $v) {
            $value[$k] = $v;
        }

        return $value;
    }
    // Do not support E notation for numbers (ie 6.02e23).
    // This can cause checksums (such as git commits) to be converted to 0.
    if (is_numeric($value) && stripos($value, 'e') === false) {
        if (is_nan($value) || is_infinite($value)) {
            // Special handling for values that are not supported by JSON.
            return 0;
        }
        // Otherwise return the numeric value of this string.
        return $value + 0;
    }
    if (is_string($value)) {
        $value = (string)$value;
        if (function_exists('mb_detect_encoding') &&
            mb_detect_encoding($value, 'UTF-8', true) === false
        ) {
            $value = utf8_encode($value);
        }
    }
    return $value;
}

/**
 * Get the site ID for 'CDash Server'.
 * This is the site associated with Aggregate Coverage builds.
 */
function get_server_siteid()
{
    $server = new Site();
    $server->Name = 'CDash Server';
    if (!$server->Exists()) {
        // Create it if it doesn't exist.
        // SERVER_ADDR is not guaranteed to exist on every web server
        $server_ip = @$_SERVER['SERVER_ADDR'];
        $server->Ip = $server_ip;
        $server->Insert();
    }
    return intval($server->Id);
}

/**
 * Return the 'Aggregate Coverage' build for the day of the
 * specified build.  If it doesn't exist yet, we create it here.
 * If $build is for a subproject then we return the corresponding
 * aggregate build for that same subproject.
 */
function get_aggregate_build(Build $build): Build
{
    $siteid = get_server_siteid();
    $build->ComputeTestingDayBounds();

    $subproj_table = '';
    $subproj_where = '';
    $subproj_where_params = [];
    if ($build->SubProjectId) {
        $subproj_table = "INNER JOIN subproject2build AS sp2b ON (build.id=sp2b.buildid)";
        $subproj_where = "AND sp2b.subprojectid=?";
        $subproj_where_params[] = intval($build->SubProjectId);
    }

    $db = Database::getInstance();

    $row = $db->executePreparedSingleRow("
               SELECT id
               FROM build
               $subproj_table
               WHERE
                   name='Aggregate Coverage'
                   AND siteid = ?
                   AND parentid < '1'
                   AND projectid = ?
                   AND starttime < ?
                   AND starttime >= ?
               $subproj_where
           ", array_merge([
               intval($siteid),
               intval($build->ProjectId),
               $build->EndOfDay,
               $build->BeginningOfDay],
        $subproj_where_params
    ));
    if (!$row || !array_key_exists('id', $row)) {
        // The aggregate build does not exist yet.
        // Create it here.
        $aggregate_build = create_aggregate_build($build, $siteid);
    } else {
        $aggregate_build = new Build();
        $aggregate_build->Id = $row['id'];
        $aggregate_build->FillFromId($row['id']);
    }
    return $aggregate_build;
}

function create_aggregate_build($build, $siteid=null): Build
{
    require_once 'include/ctestparserutils.php';

    if (is_null($siteid)) {
        $siteid = get_server_siteid();
    }

    $aggregate_build = new Build();
    $aggregate_build->Name = 'Aggregate Coverage';
    $aggregate_build->SiteId = $siteid;
    $date = substr($build->GetStamp(), 0, strpos($build->GetStamp(), '-'));
    $aggregate_build->SetStamp($date."-0000-Nightly");
    $aggregate_build->ProjectId = $build->ProjectId;

    $aggregate_build->StartTime = $build->StartTime;
    $aggregate_build->EndTime = $build->EndTime;
    $aggregate_build->SubmitTime = gmdate(FMT_DATETIME);
    $aggregate_build->SetSubProject($build->GetSubProjectName());
    $aggregate_build->InsertErrors = false;
    add_build($aggregate_build);
    return $aggregate_build;
}

function extract_tar_archive_tar($filename, $dirName): bool
{
    try {
        $tar = new Archive_Tar($filename);
        $tar->setErrorHandling(PEAR_ERROR_CALLBACK, function ($pear_error) {
            throw new PEAR_Exception($pear_error->getMessage());
        });
        return $tar->extract($dirName);
    } catch (PEAR_Exception $e) {
        add_log($e->getMessage(), 'extract_tar_archive_tar', LOG_ERR);
        return false;
    }
}

function extract_tar(string $filename, string $dirName): bool|null
{
    if (class_exists('PharData')) {
        try {
            $phar = new PharData($filename);
            $phar->extractTo($dirName);
        } catch (Exception $e) {
            add_log($e->getMessage(), 'extract_tar', LOG_ERR);
            return false;
        }

        return true;
    } else {
        return extract_tar_archive_tar($filename, $dirName);
    }
}

/**
 * Encode structures for safe HTML output
 */
function deepEncodeHTMLEntities(&$structure): void
{
    $encode = function ($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8', false);
    };

    if (is_object($structure)) {
        $properties = get_object_vars($structure);
        foreach ($properties as $key => &$prop) {
            if (is_object($prop) || is_array($prop)) {
                deepEncodeHTMLEntities($prop);
                $structure->{$key} = $prop;
                continue;
            }
            $structure->{$key} = $encode($prop);
        }
    } elseif (is_array($structure)) {
        foreach ($structure as $key => &$value) {
            if (is_object($value) || is_array($value)) {
                deepEncodeHTMLEntities($value);
                continue;
            }
            $value = $encode($value);
        }
    } elseif (is_string($structure)) {
        $structure = $encode($structure);
    }
}
