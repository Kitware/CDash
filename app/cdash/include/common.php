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

use App\Http\Controllers\AbstractController;
use App\Models\Site;
use App\Utils\DatabaseCleanupUtils;
use App\Utils\SubmissionUtils;
use App\Utils\TestingDay;
use CDash\Database;
use CDash\Model\Build;
use CDash\Model\Project;
use CDash\ServiceContainer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
    $xml = new DOMDocument();
    $xsl = new DOMDocument();

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
    $xh = new XSLTProcessor();

    $arguments = [
        '/_xml' => $xml,
    ];

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
        $str = mb_convert_encoding($str, 'UTF-8');
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

    Log::warning("Could not handle input: $input", [
        'function' => 'get_seconds_from_interval',
    ]);

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
function add_last_sql_error($functionname, $projectid = 0, $buildid = 0): void
{
    $pdo_error = pdo_error();
    if (strlen($pdo_error) > 0) {
        $context = [
            'function' => $functionname,
        ];

        if ($projectid > 0) {
            $context['projectid'] = $projectid;
        }

        if ($buildid > 0) {
            $context['buildid'] = $buildid;
        }

        Log::error('SQL error: ' . $pdo_error, $context);
        $text = "SQL error in $functionname():" . $pdo_error . '<br>';
        echo $text;
    }
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
 * Get the geolocation from IP address
 */
function get_geolocation($ip)
{
    $location = [];

    $lat = '';
    $long = '';

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
            $options = ['http' => ['timeout' => 5.0]];
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

    $buildids = [];
    foreach ($build as $build_array) {
        $buildids[] = (int) $build_array->id;
    }
    DatabaseCleanupUtils::removeBuildChunked($buildids);
}

/**
 * Deletes the symlink to an uploaded file.  If it is the only symlink to that content,
 * it will also delete the content itself.
 * Returns the number of bytes deleted from disk (0 for symlink, otherwise the size of the content)
 */
function unlink_uploaded_file($fileid)
{
    $pdo = Database::getInstance()->getPdo();
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
    $filename = $row['filename'];
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
        // Delete file if this is the only build referencing it.
        Storage::delete("upload/{$sha1sum}");
        return $filesize;
    } else {
        return 0;
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
            $date = date(FMT_DATE, time() + 3600 * 24); // next day
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
    return [$previousdate, $today, $nextdate, $date];
}

function has_next_date($date, $currentstarttime): bool
{
    return
        isset($date)
        && strlen($date) >= 8
        && date(FMT_DATE, $currentstarttime) < date(FMT_DATE);
}

/**
 * make_cdash_url ensures that a url begins with a known url protocol identifier
 */
function make_cdash_url(string $url): string
{
    // Unless the input does *not* start with a known protocol identifier...
    // If it does not start with http or https already, then prepend "http://"
    // to the input.
    if (!str_contains($url, 'http://') && !str_contains($url, 'https://')) {
        return 'http://' . $url;
    }
    return $url;
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

/**
 * Check if user has specified a preference for color scheme.
 */
function get_css_file(): string
{
    $classic = 'assets/css/cdash.css';
    $colorblind = 'assets/css/colorblind.css';

    if (array_key_exists('colorblind', $_COOKIE) && $_COOKIE['colorblind'] == 1) {
        return $colorblind;
    }
    return $classic;
}

function begin_XML_for_XSLT(): string
{
    $css_file = get_css_file();

    $xml = '<?xml version="1.0" encoding="UTF-8"?><cdash>';
    $xml .= add_XML_value('cssfile', $css_file);
    $xml .= add_XML_value('version', AbstractController::getCDashVersion());
    $xml .= add_XML_value('_token', csrf_token());

    return $xml;
}

function begin_JSON_response(): array
{
    $response = [];
    $response['version'] = AbstractController::getCDashVersion();

    $user_response = [];
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
    $project_array['name'] = $projectname;
    $project_array['nightlytime'] = $project->Id ? $project->NightlyTime : '00:00:00';

    if (is_null($date)) {
        $date = date(FMT_DATE);
    }
    [$previousdate, $currentstarttime, $nextdate] = get_dates($date, $project_array['nightlytime']);

    $response['datetime'] = date('l, F d Y H:i:s', time());
    $response['date'] = $date;
    $response['unixtimestamp'] = $currentstarttime;
    $response['startdate'] = date('l, F d Y H:i:s', $currentstarttime);
    $response['currentdate'] = TestingDay::get($project, gmdate(FMT_DATETIME));
    $response['vcs'] = make_cdash_url(htmlentities($project_array['cvsurl']));
    $response['bugtracker'] = make_cdash_url(htmlentities($project_array['bugtrackerurl']));
    $response['documentation'] = make_cdash_url(htmlentities($project_array['documentationurl']));
    $response['projectid'] = $project->Id;
    $response['projectname'] = $project_array['name'];
    $response['projectname_encoded'] = urlencode($project_array['name']);
    $response['public'] = $project->Public;
    $response['previousdate'] = $previousdate;
    $response['nextdate'] = $nextdate;
    $response['logoid'] = $project->ImageId ?? 0;
    $response['nightlytime'] = date('H:i T', strtotime($project_array['nightlytime']));
    if (empty($project_array['homeurl'])) {
        $response['home'] = 'index.php?project=' . urlencode($project_array['name']);
    } else {
        $response['home'] = make_cdash_url(htmlentities($project_array['homeurl']));
    }

    $userid = Auth::id();
    if ($userid) {
        $project = App\Models\Project::findOrFail((int) $project->Id);
        $response['projectrole'] = $project->users()->withPivot('role')->find((int) $userid)->pivot->role ?? 0;
        if ($response['projectrole'] > Project::SITE_MAINTAINER) {
            $response['user']['admin'] = 1;
        }
    }
    $response['user']['id'] = $userid;
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
        if (in_array($file->getBasename(), ['.', '..'])) {
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
        $value = (string) $value;
        if (function_exists('mb_detect_encoding')
            && mb_detect_encoding($value, 'UTF-8', true) === false
        ) {
            $value = mb_convert_encoding($value, 'UTF-8');
        }
    }
    return $value;
}

/**
 * Get the site ID for 'CDash Server'.
 * This is the site associated with Aggregate Coverage builds.
 */
function get_server_siteid(): int
{
    $server = Site::firstOrCreate(['name' => 'CDash Server'], [
        'name' => 'CDash Server',
        'ip' => $_SERVER['SERVER_ADDR'] ?? '',
    ]);
    return $server->id;
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
        $subproj_table = 'INNER JOIN subproject2build AS sp2b ON (build.id=sp2b.buildid)';
        $subproj_where = 'AND sp2b.subprojectid=?';
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

function create_aggregate_build($build, $siteid = null): Build
{
    if (is_null($siteid)) {
        $siteid = get_server_siteid();
    }

    $aggregate_build = new Build();
    $aggregate_build->Name = 'Aggregate Coverage';
    $aggregate_build->SiteId = $siteid;
    $date = substr($build->GetStamp(), 0, strpos($build->GetStamp(), '-'));
    $aggregate_build->SetStamp($date . '-0000-Nightly');
    $aggregate_build->ProjectId = $build->ProjectId;

    $aggregate_build->StartTime = $build->StartTime;
    $aggregate_build->EndTime = $build->EndTime;
    $aggregate_build->SubmitTime = gmdate(FMT_DATETIME);
    $aggregate_build->SetSubProject($build->GetSubProjectName());
    $aggregate_build->InsertErrors = false;
    SubmissionUtils::add_build($aggregate_build);
    return $aggregate_build;
}

/**
 * Extract a tarball within the local storage directory.
 */
function extract_tar(string $stored_filepath): string
{
    if (!Storage::exists($stored_filepath)) {
        Log::error("{$stored_filepath} does not exist", [
            'function' => 'extract_tar',
        ]);
        return '';
    }

    // Create a new directory where we can extract the tarball.
    $localTmpDirPath = 'tmp' . DIRECTORY_SEPARATOR . pathinfo($stored_filepath, PATHINFO_FILENAME);
    Storage::disk('local')->makeDirectory($localTmpDirPath);
    $dirName = Storage::disk('local')->path($localTmpDirPath);

    if (config('filesystem.default') !== 'local') {
        // Download this file to the local Storage tmp dir.
        $remote_stored_filepath = $stored_filepath;
        $stored_filepath = 'tmp/' . basename($stored_filepath);
        $fp = Storage::readStream($remote_stored_filepath);
        if ($fp === null) {
            return '';
        }
        Storage::disk('local')->put($stored_filepath, $fp);
    }

    try {
        $tar = new Archive_Tar(Storage::disk('local')->path($stored_filepath));
        $tar->setErrorHandling(PEAR_ERROR_CALLBACK, function ($pear_error) {
            throw new PEAR_Exception($pear_error->getMessage());
        });
        $tar_extract_result = $tar->extract($dirName);
        if (config('filesystem.default') !== 'local') {
            Storage::disk('local')->delete($stored_filepath);
        }
        if ($tar_extract_result === false) {
            Storage::disk('local')->deleteDirectory($localTmpDirPath);
            return '';
        }
        return $dirName;
    } catch (PEAR_Exception $e) {
        if (config('filesystem.default') !== 'local') {
            Storage::disk('local')->delete($stored_filepath);
        }
        Storage::disk('local')->deleteDirectory($localTmpDirPath);
        report($e);
        return '';
    }
}

/**
 * Encode structures for safe HTML output
 */
function deepEncodeHTMLEntities(&$structure): void
{
    $encode = fn ($string) => htmlspecialchars($string, ENT_QUOTES, 'UTF-8', false);

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
