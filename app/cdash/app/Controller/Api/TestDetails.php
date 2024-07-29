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

namespace CDash\Controller\Api;

use App\Models\TestOutput;
use App\Models\Project as EloquentProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

use App\Utils\RepositoryUtils;

class TestDetails extends BuildTestApi
{
    public $buildtest;

    public function getResponse(): JsonResponse|StreamedResponse
    {
        // If we have a fileid we download it.
        if (isset($_GET['fileid']) && is_numeric($_GET['fileid'])) {
            $query = DB::select("
                SELECT
                    id,
                    value,
                    name
                FROM testmeasurement
                WHERE
                    testid = ?
                    AND type = 'file'
                ORDER BY id
            ", [$this->buildtest->id])[0];

            return response()->streamDownload(
                function () use ($query) {
                    echo base64_decode($query->value);
                },
                $query->name . '.tgz',
                [
                    'Content-Disposition' => 'attachment',
                    'Content-type' => 'tar/gzip',
                ]
            );
        }

        $response = begin_JSON_response();

        $site = $this->build->GetSite();

        $this->setDate($this->build->GetDate());

        $response['title'] = "{$this->project->Name} - Tests";
        get_dashboard_JSON($this->project->Name, $this->date, $response);

        $project_response = [];
        $project_response['showtesttime'] = $this->project->ShowTestTime;
        $response['project'] = $project_response;

        $stmt = $this->db->prepare('
            SELECT
                *,
                b2t.id as testid
            FROM build2test b2t
            JOIN testoutput ON (testoutput.id = b2t.outputid)
            WHERE b2t.id = :buildtestid
        ');
        $this->db->execute($stmt, [':buildtestid' => $this->buildtest->id]);
        $testRow = $stmt->fetch();
        $testName = $testRow['testname'];
        $outputid = $testRow['outputid'];
        $testid = $testRow['testid'];

        $menu = [];
        $menu['back'] = "viewTest.php?buildid={$this->build->Id}";

        // Did the user request a specific chart?
        // If so we should make that chart appears when they click next or previous.
        $extra_url = '';
        if (array_key_exists('graph', $_GET)) {
            $extra_url = "?graph=" . $_GET['graph'];
        }

        // Get previous/current/next results for this buildtest.
        $previous_buildtestid = $this->getRelatedBuildTest('previous');
        $current_buildtestid = $this->getRelatedBuildTest('current');
        $next_buildtestid = $this->getRelatedBuildTest('next');

        // Navigation menu entry for 'Previous'.
        if ($previous_buildtestid) {
            $menu['previous'] = "/test/{$previous_buildtestid}{$extra_url}";
        } else {
            $menu['previous'] = false;
        }

        // Current
        if ($current_buildtestid) {
            $menu['current'] = "/test/{$current_buildtestid}{$extra_url}";
        } else {
            $menu['current'] = false;
        }

        // Next
        if ($next_buildtestid) {
            $menu['next'] = "/test/{$next_buildtestid}{$extra_url}";
        } else {
            $menu['next'] = false;
        }

        $response['menu'] = $menu;

        $summaryLink = "testSummary.php?project={$this->project->Id}&name={$testName}&date={$this->date}";

        $test_response = [];
        $test_response['buildid'] = $this->build->Id;
        $test_response['build'] = $this->build->Name;
        $test_response['buildstarttime'] = date(FMT_DATETIMESTD, strtotime($this->build->StartTime . ' UTC'));
        $test_response['site'] = $site->name;
        $test_response['siteid'] = $site->id;
        $test_response['test'] = $testName;
        $test_response['time'] = time_difference($testRow['time'], true, '', true);
        $test_response['command'] = $testRow['command'];
        $test_response['details'] = $testRow['details'];
        $test_response['output'] = $this->utf8_for_xml(TestOutput::DecompressOutput($testRow['output']));

        if ($this->project->DisplayLabels) {
            $test_response['labels'] = $this->buildtest->getLabels()->keys()->implode(', ');
        } else {
            $test_response['labels'] = '';
        }

        $test_response['summaryLink'] = $summaryLink;
        switch ($testRow['status']) {
            case 'passed':
                $test_response['status'] = 'Passed';
                $test_response['statusColor'] = 'normal-text';
                break;
            case 'failed':
                $test_response['status'] = 'Failed';
                $test_response['statusColor'] = 'error-text';
                break;
            case 'notrun':
                $test_response['status'] = 'Not Run';
                $test_response['statusColor'] = 'warning-text';
                break;
        }

        // Find the repository revision.
        $update_response = [
            'revision' => '',
            'priorrevision' => '',
            'path' => '',
            'revisionurl' => '',
            'revisiondiff' => '',
        ];
        $stmt = $this->db->prepare(
            'SELECT status, revision, priorrevision, path
                FROM buildupdate bu
                JOIN build2update b2u ON (b2u.updateid = bu.id)
                WHERE b2u.buildid = :buildid');
        $this->db->execute($stmt, [':buildid' => $this->build->Id]);
        $status_array = $stmt->fetch();
        if (is_array($status_array)) {
            if (strlen($status_array['status']) > 0 && $status_array['status'] != '0') {
                $update_response['status'] = $status_array['status'];
            }
            $update_response['revision'] = $status_array['revision'];
            $update_response['priorrevision'] = $status_array['priorrevision'];
            $update_response['path'] = $status_array['path'];
            $update_response['revisionurl'] =
                RepositoryUtils::get_revision_url($this->project->Id, $status_array['revision'], $status_array['priorrevision']);
            $update_response['revisiondiff'] =
                RepositoryUtils::get_revision_url($this->project->Id, $status_array['priorrevision'], ''); // no prior revision...
        }
        $test_response['update'] = $update_response;

        $test_response['timemean'] = $testRow['timemean'];
        $test_response['timestd'] = $testRow['timestd'];

        $testtimemaxstatus = $this->project->TestTimeMaxStatus;
        if ($testRow['timestatus'] == 0) {
            $test_response['timestatus'] = 'Passed';
            $test_response['timeStatusColor'] = 'normal-text';
        } else {
            $threshold = $test_response['timemean'] +
                $this->project->TestTimeStd * $test_response['timestd'];
            $test_response['threshold'] = time_difference($threshold, true, '', true);
            if ($testRow['timestatus'] >= $testtimemaxstatus) {
                $test_response['timestatus'] = 'Failed';
                $test_response['timeStatusColor'] = 'error-text';
            } else {
                $test_response['timestatus'] = 'Warning';
                $test_response['timeStatusColor'] = 'warning-text';
            }
        }

        // Get any images associated with this test.
        $compareimages_response = [];
        $stmt = $this->db->prepare(
            "SELECT imgid, role FROM test2image
                WHERE outputid = :outputid AND
                (role = 'TestImage' OR role = 'ValidImage' OR role = 'BaselineImage' OR
                 role ='DifferenceImage2')
                ORDER BY id");
        $this->db->execute($stmt, [':outputid' => $outputid]);
        while ($row = $stmt->fetch()) {
            $image_response = [];
            $image_response['imgid'] = $row['imgid'];
            $image_response['role'] = $row['role'];
            $compareimages_response[] = $image_response;
        }
        if (!empty($compareimages_response)) {
            $test_response['compareimages'] = $compareimages_response;
        }

        $images_response = [];
        $stmt = $this->db->prepare(
            "SELECT imgid, role FROM test2image
                WHERE outputid = :outputid AND
                role != 'ValidImage' AND role != 'BaselineImage' AND
                role != 'DifferenceImage2'
                ORDER BY id");
        $this->db->execute($stmt, [':outputid' => $outputid]);
        while ($row = $stmt->fetch()) {
            $image_response = [];
            $image_response['imgid'] = $row['imgid'];
            $image_response['role'] = $row['role'];
            $images_response[] = $image_response;
        }
        if (!empty($images_response)) {
            $test_response['images'] = $images_response;
        }

        // Get any measurements associated with this test.
        $measurements_response = [];
        $stmt = $this->db->prepare(
            'SELECT name, type, value FROM testmeasurement
                WHERE testid = :testid
                ORDER BY id');
        $this->db->execute($stmt, [':testid' => $testid]);
        $fileid = 1;
        $test_response['environment'] = '';
        $preformatted_measurements = [];

        while ($row = $stmt->fetch()) {
            if ($row['name'] === 'Environment' && $row['type'] === 'text/string') {
                $test_response['environment'] = $row['value'];
                continue;
            } elseif ($row['type'] == 'text/preformatted') {
                $preformatted_measurement = ['name' => $row['name'], 'value' => $row['value']];
                $preformatted_measurements[] = $preformatted_measurement;
                continue;
            }

            $measurement_response = [];
            $measurement_response['name'] = $row['name'];
            $measurement_response['type'] = $row['type'];

            // CTest base64-encodes the type text/plain...
            $value = $row['value'];
            if ($row['type'] == 'text/plain') {
                if (substr($value, strlen($value) - 2) == '==') {
                    $value = base64_decode($value);
                }
            } elseif ($row['type'] == 'file') {
                $measurement_response['fileid'] = $fileid++;
            }
            // Add nl2br for type text/plain and text/string
            if ($row['type'] == 'text/plain' || $row['type'] == 'text/string') {
                $value = nl2br($value);
            }

            // If the type is a file we just don't pass the text (too big) to the output
            if ($row['type'] == 'file') {
                $value = '';
            }

            $measurement_response['value'] = $value;
            $measurements_response[] = $measurement_response;
        }

        // Get the list of extra test measurements that have been explicitly added to this project.
        $extra_measurements = EloquentProject::findOrFail($this->project->Id)
            ->measurements()
            ->orderBy('position')
            ->pluck('name')
            ->toArray();

        // Sort measurements: put those listed explicitly first (sorted by position)
        // then sort the rest alphabetically by name.
        $sort_measurements = function ($a, $b) use ($extra_measurements) {
            $index_a = array_search($a['name'], $extra_measurements);
            $index_b = array_search($b['name'], $extra_measurements);
            if ($index_a !== false && $index_b !== false) {
                return ($index_a < $index_b) ? -1 : 1;
            } elseif ($index_a !== false) {
                return -1;
            } elseif ($index_b !== false) {
                return 1;
            } else {
                return strcmp($a['name'], $b['name']);
            }
        };
        usort($measurements_response, $sort_measurements);
        $test_response['measurements'] = $measurements_response;
        usort($preformatted_measurements, $sort_measurements);
        $test_response['preformatted_measurements'] = $preformatted_measurements;
        $response['test'] = $test_response;
        $this->pageTimer->end($response);
        return response()->json($response);
    }

    private function getRelatedBuildTest($which_buildtest)
    {
        switch ($which_buildtest) {
            case 'previous':
                $this->testHistoryQueryOrder = 'DESC';
                $this->testHistoryQueryExtraWheres = 'AND b.starttime < :starttime';
                $this->testHistoryQueryParams[':starttime'] = $this->build->StartTime;
                break;
            case 'next':
                $this->testHistoryQueryOrder = 'ASC';
                $this->testHistoryQueryExtraWheres = 'AND b.starttime > :starttime';
                $this->testHistoryQueryParams[':starttime'] = $this->build->StartTime;
                break;
            case 'current':
            default:
                $this->testHistoryQueryOrder = 'DESC';
                $this->testHistoryQueryExtraWheres = '';
                if (array_key_exists(':starttime', $this->testHistoryQueryParams)) {
                    unset($this->testHistoryQueryParams[':starttime']);
                }
                break;
        }
        $this->testHistoryQueryLimit = 'LIMIT 1';
        $this->generateTestHistoryQuery();
        $stmt = $this->db->prepare($this->testHistoryQuery);
        $this->db->execute($stmt, $this->testHistoryQueryParams);
        $row = $stmt->fetch();
        if (is_array($row)) {
            return $row['buildtestid'];
        }
        return null;
    }

    // Remove bad characters for XML parser
    private function utf8_for_xml($string)
    {
        return preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{001b}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $string);
    }
}
