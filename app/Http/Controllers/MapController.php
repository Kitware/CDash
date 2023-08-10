<?php

namespace App\Http\Controllers;

use CDash\Database;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class MapController extends AbstractProjectController
{
    public function viewMap(): View|RedirectResponse
    {
        $this->setProjectByName(htmlspecialchars($_GET['project'] ?? ''));

        @$date = $_GET['date'];
        if ($date != null) {
            $date = htmlspecialchars($date);
        }

        $xml = begin_XML_for_XSLT();
        $xml .= '<backurl>index.php?project=' . urlencode($this->project->Name) . "&#38;date=$date</backurl>";
        $xml .= '<menutitle>CDash</menutitle>';
        $xml .= '<menusubtitle>Build location</menusubtitle>';

        $xml .= '<dashboard>';
        $xml .= '<title>CDash</title>';
        $xml .= '<date>' . $date . '</date>';

        $apikey = config('cdash.google_map_api_key');

        $xml .= add_XML_value('googlemapkey', $apikey);
        $xml .= add_XML_value('projectname', $this->project->Name);
        $xml .= add_XML_value('projectname_encoded', urlencode($this->project->Name));
        $xml .= '</dashboard>';

        $db = Database::getInstance();

        list($previousdate, $currenttime, $nextdate) = get_dates($date, $this->project->NightlyTime);

        $nightlytime = strtotime($this->project->NightlyTime);

        $nightlyhour = gmdate('H', $nightlytime);
        $nightlyminute = gmdate('i', $nightlytime);
        $nightlysecond = gmdate('s', $nightlytime);

        $end_timestamp = $currenttime - 1; // minus 1 second when the nightly start time is midnight exactly

        $beginning_timestamp = gmmktime($nightlyhour, $nightlyminute, $nightlysecond, gmdate('m', $end_timestamp), gmdate('d', $end_timestamp), gmdate('Y', $end_timestamp));
        if ($end_timestamp < $beginning_timestamp) {
            $beginning_timestamp = gmmktime($nightlyhour, $nightlyminute, $nightlysecond, gmdate('m', $end_timestamp - 24 * 3600), gmdate('d', $end_timestamp - 24 * 3600), gmdate('Y', $end_timestamp - 24 * 3600));
        }

        $beginning_UTCDate = gmdate(FMT_DATETIME, $beginning_timestamp);
        $end_UTCDate = gmdate(FMT_DATETIME, $end_timestamp);

        if (config('database.default') == 'pgsql') {
            $site = $db->executePrepared('
                        SELECT
                            s.id,
                            s.name,
                            si.processorclockfrequency,
                            si.description,
                            si.numberphysicalcpus,
                            s.ip,
                            s.latitude,
                            s.longitude,
                            u.firstname,
                            u.lastname,
                            u.id AS userid
                        FROM
                            build AS b,
                            siteinformation AS si,
                            site as s
                        LEFT JOIN site2user ON (site2user.siteid=s.id)
                        LEFT JOIN ' . qid('user') . ' AS u ON (site2user.userid=u.id)
                        WHERE
                            s.id=b.siteid
                            AND b.starttime<?
                            AND b.starttime>?
                            AND si.siteid=s.id
                            AND b.projectid=?
                        GROUP BY
                            s.id,
                            s.name,
                            si.processorclockfrequency,
                            si.description,
                            si.numberphysicalcpus,
                            s.ip,
                            s.latitude,
                            s.longitude,
                            u.firstname,
                            u.lastname,
                            u.id
                    ', [$end_UTCDate, $beginning_UTCDate, $this->project->Id]);
        } else {
            $site = $db->executePrepared('
                        SELECT
                            s.id,
                            s.name,
                            si.processorclockfrequency,
                            si.description,
                            si.numberphysicalcpus,
                            s.ip,
                            s.latitude,
                            s.longitude,
                            u.firstname,
                            u.lastname,
                            u.id AS userid
                        FROM
                            build AS b,
                            siteinformation AS si,
                            site as s
                        LEFT JOIN site2user ON (site2user.siteid=s.id)
                        LEFT JOIN ' . qid('user') . ' AS u ON (site2user.userid=u.id)
                        WHERE
                            s.id=b.siteid
                            AND b.starttime<?
                            AND b.starttime>?
                            AND si.siteid=s.id
                            AND b.projectid=?
                        GROUP BY s.id
                    ', [$end_UTCDate, $beginning_UTCDate, $this->project->Id]);
        }

        echo pdo_error();

        foreach ($site as $site_array) {
            if ($site_array['processorclockfrequency'] > 0) {
                $processor_speed = getByteValueWithExtension($site_array['processorclockfrequency'] * 1024 * 1024) . 'Hz';
            } else {
                $processor_speed = '';
            }

            if ($site_array['numberphysicalcpus'] > 0) {
                $num_physical_cpus = $site_array['numberphysicalcpus'];
            } else {
                $num_physical_cpus = '';
            }

            $xml .= '<site>';
            $xml .= add_XML_value('name', $site_array['name']);
            $xml .= add_XML_value('id', $site_array['id']);
            $xml .= add_XML_value('description', $site_array['description']);
            $xml .= add_XML_value('processor_speed', $processor_speed);
            $xml .= add_XML_value('numberphysicalcpus', $num_physical_cpus);
            $xml .= add_XML_value('latitude', $site_array['latitude']);
            $xml .= add_XML_value('longitude', $site_array['longitude']);
            $xml .= add_XML_value('longitude', $site_array['longitude']);
            $xml .= add_XML_value('maintainer_name', $site_array['firstname'] . ' ' . $site_array['lastname']);
            $xml .= add_XML_value('maintainer_id', $site_array['userid']);
            $xml .= '</site>';
        }

        $xml .= '</cdash>';

        return view('cdash', [
            'xsl' => true,
            'xsl_content' => generate_XSLT($xml, base_path() . '/app/cdash/public/viewMap', true),
            'project' => $this->project,
            'title' => 'Sites Map'
        ]);
    }
}
