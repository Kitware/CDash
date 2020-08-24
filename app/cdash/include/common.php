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
use App\Models\User;
use App\Services\TestingDay;

use CDash\Config;
use CDash\Database;
use CDash\Model\AuthToken;
use CDash\ServiceContainer;
use CDash\Model\Build;
use CDash\Model\Project;
use CDash\Model\UserProject;
use CDash\Model\Site;

require_once 'include/log.php';


// Emulate the old xslt library functions
function xslt_create()
{
    return new XsltProcessor();
}

function xslt_process($xsltproc,
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
    $xsltproc->importStyleSheet($xsl);

    // Set parameters when defined
    if ($params) {
        foreach ($params as $param => $value) {
            $xsltproc->setParameter('', $param, $value);
        }
    }

    // Start the transformation
    $processed = $xsltproc->transformToXML($xml);

    // Put the result in a file when specified
    if ($xslcontainer) {
        return @file_put_contents($xslcontainer, $processed);
    }

    return $processed;
}

function xslt_free($xsltproc)
{
    unset($xsltproc);
}


/** Do the XSLT translation **/
function generate_XSLT($xml, $pageName)
{
    $config = Config::getInstance();

    $xh = xslt_create();

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

    echo $html;

    xslt_free($xh);
}

/** used to escape special XML characters */
function XMLStrFormat($str)
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

function time_difference($duration, $compact = false, $suffix = '', $displayms = false)
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

/* Return the number of seconds represented by the specified time interval
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

function xml_replace_callback($matches)
{
    $decimal_value = hexdec(bin2hex($matches[0]));
    return '&#' . $decimal_value . ';';
}

/** Add an XML tag to a string */
function add_XML_value($tag, $value)
{
    $value = preg_replace_callback('/[\x1b]/', 'xml_replace_callback', $value);
    return '<' . $tag . '>' . XMLStrFormat($value) . '</' . $tag . '>';
}

/** Report last my SQL error */
function add_last_sql_error($functionname, $projectid = 0, $buildid = 0, $resourcetype = 0, $resourceid = 0)
{
    $pdo_error = pdo_error();
    if (strlen($pdo_error) > 0) {
        add_log('SQL error: ' . $pdo_error, $functionname, LOG_ERR, $projectid, $buildid, $resourcetype, $resourceid);
        $text = "SQL error in $functionname():" . $pdo_error . '<br>';
        echo $text;
    }
}

/* Catch any PHP fatal errors */
//
// This is a registered shutdown function (see register_shutdown_function help)
// and gets called at script exit time, regardless of reason for script exit.
// i.e. -- it gets called when a script exits normally, too.
//
global $PHP_ERROR_BUILD_ID;
global $PHP_ERROR_RESOURCE_TYPE;
global $PHP_ERROR_RESOURCE_ID;

function PHPErrorHandler($projectid)
{
    if (connection_aborted()) {
        add_log('PHPErrorHandler', "connection_aborted()='" . connection_aborted() . "'", LOG_INFO, $projectid);
        add_log('PHPErrorHandler', "connection_status()='" . connection_status() . "'", LOG_INFO, $projectid);
    }

    if ($error = error_get_last()) {
        switch ($error['type']) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                if (strlen($GLOBALS['PHP_ERROR_RESOURCE_TYPE']) == 0) {
                    $GLOBALS['PHP_ERROR_RESOURCE_TYPE'] = 0;
                }
                if (strlen($GLOBALS['PHP_ERROR_BUILD_ID']) == 0) {
                    $GLOBALS['PHP_ERROR_BUILD_ID'] = 0;
                }
                if (strlen($GLOBALS['PHP_ERROR_RESOURCE_ID']) == 0) {
                    $GLOBALS['PHP_ERROR_RESOURCE_ID'] = 0;
                }

                add_log('Fatal error:' . $error['message'], $error['file'] . ' (' . $error['line'] . ')',
                    LOG_ERR, $projectid, $GLOBALS['PHP_ERROR_BUILD_ID'],
                    $GLOBALS['PHP_ERROR_RESOURCE_TYPE'], $GLOBALS['PHP_ERROR_RESOURCE_ID']);
                exit();  // stop the script
                break;
        }
    }
}

/** Set the CDash version number in the database */
function setVersion()
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

/** Return true if the user is allowed to see the page */
function checkUserPolicy($userid, $projectid, $onlyreturn = 0)
{
    if (($userid != '' && !is_numeric($userid)) || !is_numeric($projectid)) {
        return response('Insufficient data to determine access');
    }

    $service = ServiceContainer::getInstance();

    $user = User::where('id', '=', $userid)->first();
    if ($user === null) {
        // user doesn't exist. Populate an empty model anyway.
        $user = new User();
        $user->id = $userid;
    }

    // If the projectid=0 only admin can access the page
    if ($projectid == 0 && !$user->IsAdmin()) {
        if (!$onlyreturn) {
            return response('You cannot access this project');
        } else {
            return false;
        }
    } elseif (@$projectid > 0) {
        // Global admins have access to all projects.
        if ($user->IsAdmin()) {
            return true;
        }

        $project = $service->get(Project::class);
        $project->Id = $projectid;
        $project->Fill();

        // If the project is public we quit
        if ($project->Public) {
            return true;
        }

        // If the project is private and the user is not logged in we quit
        if (!$userid && !$project->Public) {
            if (!$onlyreturn) {
                return LoginController::staticShowLoginForm();
            } else {
                return false;
            }
        } elseif ($userid) {
            $userproject = new UserProject();
            $userproject->UserId = $userid;
            $userproject->ProjectId = $projectid;
            if (!$userproject->Exists()) {
                if (!$onlyreturn) {
                    return response('You cannot access this project');
                } else {
                    return false;
                }
            }
        }
    }
    return true;
}

/** Clean the backup directory */
function clean_backup_directory()
{
    $config = Config::getInstance();
    $timeframe = (int) $config->get('CDASH_BACKUP_TIMEFRAME');
    $directory = $config->get('CDASH_BACKUP_DIRECTORY');

    if ($timeframe === 0) {
        // File are deleted upon submission, no need to do anything here.
        return;
    }

    foreach (glob("{$directory}/*") as $filename) {
        if (file_exists($filename) && is_file($filename) &&
            time() - filemtime($filename) > $timeframe * 3600
        ) {
            cdash_unlink($filename);
        }
    }
}

/** Get the build id from stamp, name and buildname */
function get_build_id($buildname, $stamp, $projectid, $sitename)
{
    if (!is_numeric($projectid)) {
        return;
    }

    $buildname = pdo_real_escape_string($buildname);
    $stamp = pdo_real_escape_string($stamp);

    $sql = "SELECT build.id AS id FROM build,site WHERE build.name='$buildname' AND build.stamp='$stamp'";
    $sql .= " AND build.projectid='$projectid'";
    $sql .= " AND build.siteid=site.id AND site.name='$sitename'";
    $sql .= ' ORDER BY build.id DESC';

    $build = pdo_query($sql);
    if (pdo_num_rows($build) > 0) {
        $build_array = pdo_fetch_array($build);
        return $build_array['id'];
    }
    return -1;
}

/** Get the project id from the project name */
function get_project_id($projectname)
{
    $service = ServiceContainer::getInstance();
    $project = $service->get(Project::class);
    $project->Name = $projectname;
    if ($project->GetIdByName()) {
        return $project->Id;
    }
    return -1;
}

/** Get the project name from the project id */
function get_project_name($projectid)
{
    if (!isset($projectid) || !is_numeric($projectid)) {
        echo 'Not a valid projectid!';
        return;
    }

    $project = pdo_query("SELECT name FROM project WHERE id='$projectid'");
    if (pdo_num_rows($project) > 0) {
        $project_array = pdo_fetch_array($project);
        return $project_array['name'];
    }
    return 'NA';
}

/** strip slashes from the post if magic quotes are on */
function stripslashes_if_gpc_magic_quotes($string)
{
    if (get_magic_quotes_gpc()) {
        return stripslashes($string);
    } else {
        return $string;
    }
}

/** Get the current URI of the dashboard */
function get_server_URI($localhost = false)
{
    /** @var Config $config */
    $config = Config::getInstance();

    // If we should consider the localhost.
    // This is used for submission but not emails, etc...
    if ($localhost && $config->get('CDASH_CURL_REQUEST_LOCALHOST')) {
        $localhost = true;
    }

    return $config->getBaseUrl($localhost);
}

/** add a user to a site */
function add_site2user($siteid, $userid)
{
    if (!is_numeric($siteid)) {
        return;
    }
    if (!is_numeric($userid)) {
        return;
    }

    $site2user = pdo_query("SELECT * FROM site2user WHERE siteid='$siteid' AND userid='$userid'");
    if (pdo_num_rows($site2user) == 0) {
        pdo_query("INSERT INTO site2user (siteid,userid) VALUES ('$siteid','$userid')");
        add_last_sql_error('add_site2user');
    }
}

/** remove a user to a site */
function remove_site2user($siteid, $userid)
{
    pdo_query("DELETE FROM site2user WHERE siteid='$siteid' AND userid='$userid'");
    add_last_sql_error('remove_site2user');
}

/** Update a site */
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
    pdo_query("UPDATE site SET name='$name',ip='$ip',latitude='$latitude',longitude='$longitude',outoforder='$outoforder' WHERE id='$siteid'");

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
    $hasvalidinfo = false;
    $newrevision2 = false;
    $query = pdo_query("SELECT * from siteinformation WHERE siteid='$siteid' ORDER BY timestamp DESC LIMIT 1");
    if (pdo_num_rows($query) == 0) {
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
        $query_array = pdo_fetch_array($query);
        // Check if the information are different from what we have in the database, then that means
        // the system has been upgraded and we need to create a new revision
        foreach ($names as $name) {
            if ($$name != 'NA' && $query_array[$name] != $$name && strlen($$name) > 0) {
                // Take care of rounding issues
                if (is_numeric($$name)) {
                    if (round($$name) != $query_array[$name]) {
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
                $sql .= " ,$name";
            }
        }

        $sql .= ") VALUES($siteid,'$now'";
        foreach ($names as $name) {
            if ($$name != 'NA' && strlen($$name) > 0) {
                $sql .= ",'" . $$name . "'";
            }
        }
        $sql .= ')';
        pdo_query($sql);
        add_last_sql_error('update_site', $sql);
    } else {
        $sql = 'UPDATE siteinformation SET ';
        $i = 0;
        foreach ($names as $name) {
            if ($$name != 'NA' && strlen($$name) > 0) {
                if ($i > 0) {
                    $sql .= ' ,';
                }
                $sql .= " $name='" . $$name . "'";
                $i++;
            }
        }

        $timestamp = $query_array['timestamp'];
        $sql .= " WHERE siteid='$siteid' AND timestamp='$timestamp'";

        pdo_query($sql);
        add_last_sql_error('update_site', $sql);
    }
}

/** Get the geolocation from IP address */
function get_geolocation($ip)
{
    $location = array();

    $lat = '';
    $long = '';

    $config = Config::getInstance();

    if ($config->get('CDASH_GEOLOCATE_IP_ADDRESSES')) {
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
            $defaultlatitude = $defaultlocation['latitude'];
            $defaultlongitude = $defaultlocation['longitude'];
            if (preg_match('#^' . strtr(preg_quote($defaultip, '#'), array('\*' => '.*', '\?' => '.')) . '$#i', $ip)) {
                $location['latitude'] = $defaultlocation['latitude'];
                $location['longitude'] = $defaultlocation['longitude'];
            }
        }
    }
    return $location;
}

/* remove all builds for a project */
function remove_project_builds($projectid)
{
    if (!is_numeric($projectid)) {
        return;
    }

    $build = pdo_query("SELECT id FROM build WHERE projectid='$projectid'");
    $buildids = array();
    while ($build_array = pdo_fetch_array($build)) {
        $buildids[] = $build_array['id'];
    }
    remove_build_chunked($buildids);
}

/** Remove all related inserts for a given build */
function remove_build($buildid)
{
    if (empty($buildid)) {
        return;
    }

    $buildids = '(';
    if (is_array($buildid)) {
        $buildids .= implode(',', $buildid);
    } else {
        if (!is_numeric($buildid)) {
            return;
        }
        $buildids .= $buildid;
    }
    $buildids .= ')';

    pdo_query('DELETE FROM build2group WHERE buildid IN ' . $buildids);
    pdo_query('DELETE FROM builderror WHERE buildid IN ' . $buildids);
    pdo_query('DELETE FROM buildemail WHERE buildid IN ' . $buildids);
    pdo_query('DELETE FROM buildfile WHERE buildid IN ' . $buildids);

    pdo_query('DELETE FROM buildinformation WHERE buildid IN ' . $buildids);
    pdo_query('DELETE FROM builderrordiff WHERE buildid IN ' . $buildids);
    pdo_query('DELETE FROM buildproperties WHERE buildid IN ' . $buildids);

    pdo_query('DELETE FROM configureerrordiff WHERE buildid IN ' . $buildids);
    pdo_query('DELETE FROM coveragesummarydiff WHERE buildid IN ' . $buildids);
    pdo_query('DELETE FROM testdiff WHERE buildid IN ' . $buildids);
    pdo_query('DELETE FROM buildtesttime WHERE buildid IN ' . $buildids);
    pdo_query('DELETE FROM summaryemail WHERE buildid IN ' . $buildids);
    pdo_query('DELETE FROM related_builds WHERE buildid IN ' . $buildids);
    pdo_query('DELETE FROM related_builds WHERE relatedid IN ' . $buildids);
    pdo_query('DELETE FROM pending_submissions WHERE buildid IN ' . $buildids);

    // Remove the buildfailureargument
    $buildfailureids = '(';
    $buildfailure = pdo_query('SELECT id FROM buildfailure WHERE buildid IN ' . $buildids);
    while ($buildfailure_array = pdo_fetch_array($buildfailure)) {
        if ($buildfailureids != '(') {
            $buildfailureids .= ',';
        }
        $buildfailureids .= $buildfailure_array['id'];
    }
    $buildfailureids .= ')';
    if (strlen($buildfailureids) > 2) {
        pdo_query('DELETE FROM buildfailure2argument WHERE buildfailureid IN ' . $buildfailureids);
        pdo_query('DELETE FROM label2buildfailure WHERE buildfailureid IN ' . $buildfailureids);
    }

    // Delete buildfailuredetails that are only used by builds that are being
    // deleted.
    $detailsids = '(';
    $buildfailuredetails = pdo_query(
        'SELECT a.detailsid, count(b.detailsid) AS c
     FROM buildfailure AS a
     LEFT JOIN buildfailure AS b
     ON (a.detailsid=b.detailsid AND b.buildid NOT IN ' . $buildids . ')
     WHERE a.buildid IN ' . $buildids . '
     GROUP BY a.detailsid HAVING count(b.detailsid)=0');
    while ($buildfailuredetails_array = pdo_fetch_array($buildfailuredetails)) {
        if ($detailsids != '(') {
            $detailsids .= ',';
        }
        $detailsids .= $buildfailuredetails_array['detailsid'];
    }
    $detailsids .= ')';
    if (strlen($detailsids) > 2) {
        pdo_query('DELETE FROM buildfailuredetails WHERE id IN ' . $detailsids);
    }

    // Remove the buildfailure.
    pdo_query('DELETE FROM buildfailure WHERE buildid IN ' . $buildids);

    // Delete the configure if not shared.
    $configureids = '(';
    $build2configure = pdo_query(
            "SELECT a.configureid, COUNT(b.configureid) AS c
            FROM build2configure AS a
            LEFT JOIN build2configure AS b
            ON (a.configureid=b.configureid AND b.buildid NOT IN $buildids)
            WHERE a.buildid IN $buildids
            GROUP BY a.configureid HAVING count(b.configureid)=0");
    while ($build2configure_array = pdo_fetch_array($build2configure)) {
        // It is safe to delete this configure because it is only used
        // by builds that are being deleted.
        if ($configureids != '(') {
            $configureids .= ',';
        }
        $configureids .= $build2configure_array['configureid'];
    }
    $configureids .= ')';
    if (strlen($configureids) > 2) {
        pdo_query("DELETE FROM configure WHERE id IN $configureids");
        pdo_query("DELETE FROM configureerror WHERE configureid IN $configureids");
    }
    pdo_query("DELETE FROM build2configure WHERE buildid IN $buildids");

    // coverage file are kept unless they are shared
    $coveragefile = pdo_query('SELECT a.fileid,count(b.fileid) AS c
                             FROM coverage AS a LEFT JOIN coverage AS b
                             ON (a.fileid=b.fileid AND b.buildid NOT IN ' . $buildids . ') WHERE a.buildid IN ' . $buildids . '
                             GROUP BY a.fileid HAVING count(b.fileid)=0');

    $fileids = '(';
    while ($coveragefile_array = pdo_fetch_array($coveragefile)) {
        if ($fileids != '(') {
            $fileids .= ',';
        }
        $fileids .= $coveragefile_array['fileid'];
    }
    $fileids .= ')';

    if (strlen($fileids) > 2) {
        pdo_query('DELETE FROM coveragefile WHERE id IN ' . $fileids);
    }

    pdo_query('DELETE FROM label2coveragefile WHERE buildid IN ' . $buildids);
    pdo_query('DELETE FROM coverage WHERE buildid IN ' . $buildids);
    pdo_query('DELETE FROM coveragefilelog WHERE buildid IN ' . $buildids);
    pdo_query('DELETE FROM coveragesummary WHERE buildid IN ' . $buildids);

    // dynamicanalysisdefect
    $dynamicanalysis = pdo_query('SELECT id FROM dynamicanalysis WHERE buildid IN ' . $buildids);
    $dynids = '(';
    while ($dynamicanalysis_array = pdo_fetch_array($dynamicanalysis)) {
        if ($dynids != '(') {
            $dynids .= ',';
        }
        $dynids .= $dynamicanalysis_array['id'];
    }
    $dynids .= ')';

    if (strlen($dynids) > 2) {
        pdo_query('DELETE FROM dynamicanalysisdefect WHERE dynamicanalysisid IN ' . $dynids);
        pdo_query('DELETE FROM label2dynamicanalysis WHERE dynamicanalysisid IN ' . $dynids);
    }
    pdo_query('DELETE FROM dynamicanalysis WHERE buildid IN ' . $buildids);
    pdo_query('DELETE FROM dynamicanalysissummary WHERE buildid IN ' . $buildids);

    // Delete the note if not shared
    $noteids = '(';

    $build2note = pdo_query('SELECT a.noteid,count(b.noteid) AS c
                           FROM build2note AS a LEFT JOIN build2note AS b
                           ON (a.noteid=b.noteid AND b.buildid NOT IN ' . $buildids . ') WHERE a.buildid IN ' . $buildids . '
                           GROUP BY a.noteid HAVING count(b.noteid)=0');
    while ($build2note_array = pdo_fetch_array($build2note)) {
        // Note is not shared we delete
        if ($noteids != '(') {
            $noteids .= ',';
        }
        $noteids .= $build2note_array['noteid'];
    }
    $noteids .= ')';
    if (strlen($noteids) > 2) {
        pdo_query('DELETE FROM note WHERE id IN ' . $noteids);
    }

    pdo_query('DELETE FROM build2note WHERE buildid IN ' . $buildids);

    // Delete the update if not shared
    $updateids = '(';
    $build2update = pdo_query('SELECT a.updateid,count(b.updateid) AS c
                           FROM build2update AS a LEFT JOIN build2update AS b
                           ON (a.updateid=b.updateid AND b.buildid NOT IN ' . $buildids . ') WHERE a.buildid IN ' . $buildids . '
                           GROUP BY a.updateid HAVING count(b.updateid)=0');
    while ($build2update_array = pdo_fetch_array($build2update)) {
        // Update is not shared we delete
        if ($updateids != '(') {
            $updateids .= ',';
        }
        $updateids .= $build2update_array['updateid'];
    }
    $updateids .= ')';
    if (strlen($updateids) > 2) {
        pdo_query('DELETE FROM buildupdate WHERE id IN ' . $updateids);
        pdo_query('DELETE FROM updatefile WHERE updateid IN ' . $updateids);
    }
    pdo_query('DELETE FROM build2update WHERE buildid IN ' . $buildids);

    // Delete tests and testoutputs that are not shared.
    // First find all the tests and testoutputs from builds that are about to be deleted.
    $b2t_result = pdo_query(
        "SELECT testid, outputid from build2test WHERE buildid IN $buildids");
    $all_testids = [];
    $all_outputids = [];
    while ($b2t_row = pdo_fetch_array($b2t_result)) {
        $all_testids[] = $b2t_row['testid'];
        $all_outputids[] = $b2t_row['outputid'];
    }
    $all_testids = array_unique($all_testids);
    $all_outputids = array_unique($all_outputids);

    if (!empty($all_testids)) {
        // Next identify tests from this list that should be preserved
        // because they are shared with builds that are not about to be deleted.
        $testids = '(' . implode(',', $all_testids) . ')';
        $save_test_result = pdo_query(
                "SELECT DISTINCT testid FROM build2test
                WHERE testid IN $testids AND buildid NOT IN $buildids");
        $tests_to_save = array();
        while ($save_test_row = pdo_fetch_array($save_test_result)) {
            $tests_to_save[] = $save_test_row['testid'];
        }

        // Use array_diff to get the list of tests that should be deleted.
        $tests_to_delete = array_diff($all_testids, $tests_to_save);
        if (!empty($tests_to_delete)) {
            $testids = '(' . implode(',', $tests_to_delete) . ')';
            pdo_query("DELETE FROM test WHERE id IN $testids");
        }
    }

    // Delete un-shared testoutput rows.
    if (!empty($all_outputids)) {
        // Next identify tests from this list that should be preserved
        // because they are shared with builds that are not about to be deleted.
        $outputids = '(' . implode(',', $all_outputids) . ')';
        $save_test_result = pdo_query(
                "SELECT DISTINCT outputid FROM build2test
                WHERE outputid IN $outputids AND buildid NOT IN $buildids");
        $testoutputs_to_save = [];
        while ($save_test_row = pdo_fetch_array($save_test_result)) {
            $testoutputs_to_save[] = $save_test_row['outputid'];
        }

        // Use array_diff to get the list of tests that should be deleted.
        $testoutputs_to_delete = array_diff($all_outputids, $testoutputs_to_save);
        if (!empty($testoutputs_to_delete)) {
            $outputids = '(' . implode(',', $testoutputs_to_delete) . ')';
            delete_rows_chunked('DELETE FROM testmeasurement WHERE outputid IN ', $testoutputs_to_delete);
            delete_rows_chunked('DELETE FROM testoutput WHERE id IN ', $testoutputs_to_delete);

            $imgids = '(';
            // Check if the images for the test are not shared
            $test2image = pdo_query('SELECT a.imgid,count(b.imgid) AS c
                    FROM test2image AS a LEFT JOIN test2image AS b
                    ON (a.imgid=b.imgid AND b.outputid NOT IN ' . $outputids . ') WHERE a.outputid IN ' . $outputids . '
                    GROUP BY a.imgid HAVING count(b.imgid)=0');
            while ($test2image_array = pdo_fetch_array($test2image)) {
                $imgid = $test2image_array['imgid'];
                if ($imgids != '(') {
                    $imgids .= ',';
                }
                $imgids .= $imgid;
            }
            $imgids .= ')';
            if (strlen($imgids) > 2) {
                pdo_query('DELETE FROM image WHERE id IN ' . $imgids);
            }
            delete_rows_chunked('DELETE FROM test2image WHERE outputid IN ', $testoutputs_to_delete);
        }
    }

    pdo_query('DELETE FROM label2test WHERE buildid IN ' . $buildids);
    pdo_query('DELETE FROM build2test WHERE buildid IN ' . $buildids);

    // Delete the uploaded files if not shared
    $fileids = '(';
    $build2uploadfiles = pdo_query('SELECT a.fileid,count(b.fileid) AS c
                           FROM build2uploadfile AS a LEFT JOIN build2uploadfile AS b
                           ON (a.fileid=b.fileid AND b.buildid NOT IN ' . $buildids . ') WHERE a.buildid IN ' . $buildids . '
                           GROUP BY a.fileid HAVING count(b.fileid)=0');
    while ($build2uploadfile_array = pdo_fetch_array($build2uploadfiles)) {
        $fileid = $build2uploadfile_array['fileid'];
        if ($fileids != '(') {
            $fileids .= ',';
        }
        $fileids .= $fileid;
        unlink_uploaded_file($fileid);
    }
    $fileids .= ')';
    if (strlen($fileids) > 2) {
        pdo_query('DELETE FROM uploadfile WHERE id IN ' . $fileids);
        pdo_query('DELETE FROM build2uploadfile WHERE fileid IN ' . $fileids);
    }

    pdo_query('DELETE FROM build2uploadfile WHERE buildid IN ' . $buildids);

    // Delete the subproject
    pdo_query('DELETE FROM subproject2build WHERE buildid IN ' . $buildids);

    // Delete the labels
    pdo_query('DELETE FROM label2build WHERE buildid IN ' . $buildids);

    // Remove any children of these builds.
    if (is_array($buildid)) {
        // In order to avoid making the list of builds to delete too large
        // we delete them in batches (one batch per parent).
        foreach ($buildid as $parentid) {
            remove_children($parentid);
        }
    } else {
        remove_children($buildid);
    }

    // Only delete the buildid at the end so that no other build can get it in the meantime
    pdo_query('DELETE FROM build WHERE id IN ' . $buildids);

    add_last_sql_error('remove_build');
}

/** Call remove_build() in batches of 100. */
function remove_build_chunked($buildid)
{
    if (!is_array($buildid)) {
        return remove_build($buildid);
    }
    foreach (array_chunk($buildid, 100) as $chunk) {
        remove_build($chunk);
    }
}

/** Chunk up DELETE queries into batches of 100. */
function delete_rows_chunked($query, $ids)
{
    foreach (array_chunk($ids, 100) as $chunk) {
        $chunk_ids = '(' . implode(',', $chunk) . ')';
        pdo_query("$query $chunk_ids");
        // Sleep for a microsecond to give other processes a chance.
        usleep(1);
    }
}

/** Remove any children of the given build. */
function remove_children($parentid)
{
    $childids = array();
    $child_result = pdo_query("SELECT id FROM build WHERE parentid=$parentid");
    while ($child_array = pdo_fetch_array($child_result)) {
        $childids[] = $child_array['id'];
    }
    if (!empty($childids)) {
        remove_build($childids);
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
function rmdirr($dir)
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

/** Get year from formatted date */
function date2year($date)
{
    return substr($date, 0, 4);
}

/** Get month from formatted date */
function date2month($date)
{
    return is_numeric(substr($date, 4, 1)) ? substr($date, 4, 2) : substr($date, 5, 2);
}

/** Get day from formatted date */
function date2day($date)
{
    return is_numeric(substr($date, 4, 1)) ? substr($date, 6, 2) : substr($date, 8, 2);
}

/** Get hour from formatted time */
function time2hour($time)
{
    return substr($time, 0, 2);
}

/** Get minute from formatted time */
function time2minute($time)
{
    return is_numeric(substr($time, 2, 1)) ? substr($time, 2, 2) : substr($time, 3, 2);
}

/** Get second from formatted time */
function time2second($time)
{
    return is_numeric(substr($time, 2, 1)) ? substr($time, 4, 2) : substr($time, 6, 2);
}

/** Get dates
 * today: the *starting* timestamp of the current dashboard
 * previousdate: the date in Y-m-d format of the previous dashboard
 * nextdate: the date in Y-m-d format of the next dashboard
 */
function get_dates($date, $nightlytime)
{
    // Convert $date parameter to expected format.
    $date = date(FMT_DATE, strtotime($date));

    $nightlytime = strtotime($nightlytime, strtotime($date));
    $nightlyhour = date('H', $nightlytime);
    $nightlyminute = date('i', $nightlytime);
    $nightlysecond = date('s', $nightlytime);

    if (!isset($date) || strlen($date) == 0) {
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

    $today = mktime($nightlyhour, $nightlyminute, $nightlysecond, date2month($date), date2day($date), date2year($date)) - 3600 * 24; // starting time

    // If the $nightlytime is in the morning it's actually the day after
    if (date(FMT_TIME, $nightlytime) < '12:00:00') {
        $date = date(FMT_DATE, strtotime($date) - 3600 * 24); // previous date
    }

    $todaydate = mktime(0, 0, 0, date2month($date), date2day($date), date2year($date));
    $previousdate = date(FMT_DATE, $todaydate - 3600 * 24);
    $nextdate = date(FMT_DATE, $todaydate + 3600 * 24);
    return array($previousdate, $today, $nextdate, $date);
}

function has_next_date($date, $currentstarttime)
{
    return (
        isset($date) &&
        strlen($date) >= 8 &&
        date(FMT_DATE, $currentstarttime) < date(FMT_DATE));
}

/** Get the logo id */
function getLogoID($projectid)
{
    if (!is_numeric($projectid)) {
        return;
    }

    //asume the caller already connected to the database
    $query = "SELECT imageid FROM project WHERE id='$projectid'";
    $result = pdo_query($query);
    if (!$result) {
        return 0;
    }

    $row = pdo_fetch_array($result);
    return $row['imageid'];
}

function get_project_properties($projectname)
{
    /** @var Database $db */
    $db = Database::getInstance();
    $sql = "SELECT * FROM project WHERE name=:name";
    /** @var PDOStatement $stmt */
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':name', $projectname);
    $db->execute($stmt);
    return $stmt ? $stmt->fetch() : [];
}

function get_project_property($projectname, $prop)
{
    $project_props = get_project_properties($projectname);
    return $project_props[$prop];
}

// make_cdash_url ensures that a url begins with a known url protocol
// identifier
//
function make_cdash_url($url)
{
    // By default, same as the input
    //
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

/** Get the previous build id dynamicanalysis*/
function get_previous_buildid_dynamicanalysis($projectid, $siteid, $buildtype, $buildname, $starttime)
{
    $previousbuild = pdo_query("SELECT build.id FROM build,dynamicanalysis
                              WHERE build.siteid='$siteid' AND build.type='$buildtype' AND build.name='$buildname'
                              AND build.projectid='$projectid' AND build.starttime<'$starttime'
                              AND dynamicanalysis.buildid=build.id
                              ORDER BY build.starttime DESC LIMIT 1");

    if (pdo_num_rows($previousbuild) > 0) {
        $previousbuild_array = pdo_fetch_array($previousbuild);
        return $previousbuild_array['id'];
    }
    return 0;
}

/** Get the next build id dynamicanalysis*/
function get_next_buildid_dynamicanalysis($projectid, $siteid, $buildtype, $buildname, $starttime)
{
    $nextbuild = pdo_query("SELECT build.id FROM build,dynamicanalysis
                          WHERE build.siteid='$siteid' AND build.type='$buildtype' AND build.name='$buildname'
                          AND build.projectid='$projectid' AND build.starttime>'$starttime'
                          AND dynamicanalysis.buildid=build.id
                          ORDER BY build.starttime ASC LIMIT 1");

    if (pdo_num_rows($nextbuild) > 0) {
        $nextbuild_array = pdo_fetch_array($nextbuild);
        return $nextbuild_array['id'];
    }
    return 0;
}

/** Get the last build id dynamicanalysis */
function get_last_buildid_dynamicanalysis($projectid, $siteid, $buildtype, $buildname, $starttime)
{
    $nextbuild = pdo_query("SELECT build.id FROM build,dynamicanalysis
                          WHERE build.siteid='$siteid' AND build.type='$buildtype' AND build.name='$buildname'
                          AND build.projectid='$projectid'
                          AND dynamicanalysis.buildid=build.id
                          ORDER BY build.starttime DESC LIMIT 1");

    if (pdo_num_rows($nextbuild) > 0) {
        $nextbuild_array = pdo_fetch_array($nextbuild);
        return $nextbuild_array['id'];
    }
    return 0;
}

function get_dashboard_date_from_project($projectname, $date)
{
    $project = pdo_query("SELECT nightlytime FROM project WHERE name='$projectname'");
    $project_array = pdo_fetch_array($project);

    $nightlytime = strtotime($project_array['nightlytime']);
    $nightlyhour = date('H', $nightlytime);
    $nightlyminute = date('i', $nightlytime);
    $nightlysecond = date('s', $nightlytime);

    if (!isset($date) || strlen($date) == 0) {
        $date = date(FMT_DATE); // the date is always the date of the server

        if (date(FMT_TIME) > date(FMT_TIME, $nightlytime)) {
            $date = date(FMT_DATE, time() + 3600 * 24); //next day
        }
    }
    return $date;
}

function get_cdash_dashboard_xml($projectname, $date)
{
    $projectid = get_project_id($projectname);
    if ($projectid == -1) {
        return;
    }

    $default = [
        'cvsurl' => 'unknown',
        'bugtrackerurl' => 'unknown',
        'documentationurl' => 'unknown',
        'googletracker' => 'unknonw',
        'name' => $projectname,
        'nightlytime' => '00:00:00',
    ];

    /** @var Database $db */
    $db = Database::getInstance();
    $config = Config::getInstance();

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
  <logoid>' . getLogoID($projectid) . '</logoid>';

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

/** */
function get_cdash_dashboard_xml_by_name($projectname, $date)
{
    return get_cdash_dashboard_xml($projectname, $date);
}

/** Quote SQL identifier */
function qid($id)
{
    $config = Config::getInstance();

    if (!$config->get('CDASH_DB_TYPE') || ($config->get('CDASH_DB_TYPE') == 'mysql')) {
        return "`$id`";
    } elseif ($config->get('CDASH_DB_TYPE') == 'pgsql') {
        return "\"$id\"";
    } else {
        return $id;
    }
}

/** Quote SQL interval specifier */
function qiv($iv)
{
    $config = Config::getInstance();

    if ($config->get('CDASH_DB_TYPE') == 'pgsql') {
        return "'$iv'";
    } else {
        return $iv;
    }
}

/** Quote SQL number */
function qnum($num)
{
    $config = Config::getInstance();
    if (!$config->get('CDASH_DB_TYPE') || ($config->get('CDASH_DB_TYPE') == 'mysql')) {
        return "'$num'";
    } elseif ($config->get('CDASH_DB_TYPE') == 'pgsql') {
        return $num != '' ? $num : '0';
    } else {
        return $num;
    }
}

/** Return the list of site maintainers for a given project */
function find_site_maintainers($projectid)
{
    $userids = array();

    // Get the registered user first
    $site2user = pdo_query("SELECT site2user.userid FROM site2user,user2project
                        WHERE site2user.userid=user2project.userid AND user2project.projectid='$projectid'");
    while ($site2user_array = pdo_fetch_array($site2user)) {
        $userids[] = $site2user_array['userid'];
    }

    // Then we list all the users that have been submitting in the past 48 hours
    $submittime_UTCDate = gmdate(FMT_DATETIME, time() - 3600 * 48);
    $site2project = pdo_query("SELECT DISTINCT  userid FROM site2user WHERE siteid IN
                            (SELECT siteid FROM build WHERE projectid=$projectid
                             AND submittime>'$submittime_UTCDate')");
    while ($site2project_array = pdo_fetch_array($site2project)) {
        $userids[] = $site2project_array['userid'];
    }
    return array_unique($userids);
}

/** Return formated time given time in minutes (that's how CTest returns the time */
function get_formated_time($minutes)
{
    $time_in_seconds = round($minutes * 60);
    $hours = floor($time_in_seconds / 3600);

    $remainingseconds = $time_in_seconds - $hours * 3600;
    $minutes = floor($remainingseconds / 60);
    $seconds = $remainingseconds - $minutes * 60;
    return $hours . ':' . str_pad($minutes, 2, '0', STR_PAD_LEFT) . ':' . str_pad($seconds, 2, '0', STR_PAD_LEFT);
}

/** Check the email category */
function check_email_category($name, $emailcategory)
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

/** Return the byte value with proper extension */
function getByteValueWithExtension($value, $base = 1024)
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

/** Given a query that returns a set of rows,
 * each of which contains a 'text' field,
 * construct a chunk of <labels><label>....
 * style xml
 */
function get_labels_xml_from_query_results($qry)
{
    $xml = '';

    $rows = pdo_all_rows_query($qry);

    if (count($rows) > 0) {
        $xml .= '<labels>';
        foreach ($rows as $row) {
            $xml .= add_XML_value('label', $row['text']);
        }
        $xml .= '</labels>';
    }
    return $xml;
}

function generate_password($length)
{
    $keychars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $max = strlen($keychars) - 1;
    $key = '';
    for ($i = 0; $i < $length; $i++) {
        // random_int is available in PHP 7 and the random_compat PHP 5.x
        // polyfill included in the Composer package.json dependencies.
        $key .= substr($keychars, random_int(0, $max), 1);
    }
    return $key;
}

function create_web_api_token($projectid)
{
    $token = generate_web_api_key();
    $expTime = gmdate(FMT_DATETIME, time() + 3600); //hard-coding 1 hour for now
    pdo_query("INSERT INTO apitoken (projectid,token,expiration_date) VALUES ($projectid,'$token','$expTime')");
    clean_outdated_api_tokens();
    return $token;
}

function clean_outdated_api_tokens()
{
    $now = gmdate(FMT_DATETIME);
    pdo_query("DELETE FROM apitoken WHERE expiration_date < '$now'");
}

/**
 * Pass this a valid token created by create_web_api_token.
 * Returns true if token is valid, false otherwise.
 * Handles SQL escaping/validation of parameters.
 */
function web_api_authenticate($projectid, $token)
{
    if (!is_numeric($projectid)) {
        return false;
    }
    $now = gmdate(FMT_DATETIME);
    $token = pdo_real_escape_string($token);
    $result = pdo_query("SELECT * FROM apitoken WHERE projectid=$projectid AND token='$token' AND expiration_date > '$now'");
    return pdo_num_rows($result) != 0;
}

// Check if user has specified a preference for color scheme.
function get_css_file()
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

function begin_XML_for_XSLT()
{
    $config = CDash\Config::getInstance();
    $css_file = 'css/cdash.css';

    $css_file = get_css_file();
    $config->set('CDASH_CSS_FILE', $css_file);

    $xml = '<?xml version="1.0" encoding="UTF-8"?><cdash>';
    $xml .= add_XML_value('cssfile', $css_file);
    $xml .= add_XML_value('version', CDash\Config::getVersion());
    $xml .= add_XML_value('_token', csrf_token());

    return $xml;
}

function redirect_to_https()
{
    $config = Config::getInstance();

    if ($config->get('CDASH_USE_HTTPS') &&
        (!isset($_SERVER['HTTPS']) || !$_SERVER['HTTPS'])) {
        // if request is not secure, redirect to secure url if available
        $url = 'https://' . $_SERVER['HTTP_HOST']
            . $_SERVER['REQUEST_URI'];

        $https_check = @fsockopen($_SERVER['HTTP_HOST']);
        if ($https_check) {
            return redirect($url);
        }
    }
}

function begin_JSON_response()
{
    $config = Config::getInstance();
    $service = ServiceContainer::getInstance();

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
    return $response;
}

// TODO: pass in project object, not just name, prevents yet another unecessary query to db.
function get_dashboard_JSON($projectname, $date, &$response)
{
    $config = Config::getInstance();
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
    $response['logoid'] = getLogoID($project->Id);
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

function get_dashboard_JSON_by_name($projectname, $date, &$response)
{
    get_dashboard_JSON($projectname, $date, $response);
}

function get_labels_JSON_from_query_results($qry, &$response)
{
    $rows = pdo_all_rows_query($qry);
    if (count($rows) > 0) {
        $labels = array();
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
function DeleteDirectory($dirName)
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

function load_view($viewName, $login=true)
{
    if ($login) {
        angular_login();
    }
    readfile("build/views/$viewName.html");
}

function angular_login()
{
    if (array_key_exists('sent', $_POST) && $_POST['sent'] === 'Login >>') {
        require_once 'include/login_functions.php';
        cdash_auth();
    }
}

/* Change data-type from string to integer or float if required.
 * If a string is detected make sure it is utf8 encoded. */
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
        if (array_key_exists('files', $value) && is_string($value['files']) &&  strlen($value['files']) == 6) {
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

/* Get the site ID for 'CDash Server'.
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
    return $server->Id;
}

/* Return the 'Aggregate Coverage' build for the day of the
 * specified build.  If it doesn't exist yet, we create it here.
 * If $build is for a subproject then we return the corresponding
 * aggregate build for that same subproject.
 */
function get_aggregate_build($build)
{
    $siteid = get_server_siteid();
    $build->ComputeTestingDayBounds();

    $subproj_table = '';
    $subproj_where = '';
    if ($build->SubProjectId) {
        $subproj_table =
            "INNER JOIN subproject2build AS sp2b ON (build.id=sp2b.buildid)";
        $subproj_where =
            "AND sp2b.subprojectid='$build->SubProjectId'";
    }

    $query =
        "SELECT id FROM build
        $subproj_table
        WHERE name='Aggregate Coverage' AND
        siteid = '$siteid' AND
        parentid < '1' AND
        projectid = '$build->ProjectId' AND
        starttime < '$build->EndOfDay' AND
        starttime >= '$build->BeginningOfDay'
        $subproj_where";
    $row = pdo_single_row_query($query);
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

function create_aggregate_build($build, $siteid=null)
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

function extract_tar_archive_tar($filename, $dirName)
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

function extract_tar($filename, $dirName)
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

/** Strip the HTTP */
function stripHTTP($url)
{
    $pos = strpos($url, 'http://');
    if ($pos !== false) {
        return substr($url, 7);
    } else {
        $pos = strpos($url, 'https://');
        if ($pos !== false) {
            return substr($url, 8);
        }
    }
    return $url;
}

/**
 * Encode structures for safe HTML output
 *
 * @param $structure
 * @return void
 */
function deepEncodeHTMLEntities(&$structure)
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
