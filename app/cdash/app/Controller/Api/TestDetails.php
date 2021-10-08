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

use App\Models\BuildTest;
use App\Models\Test;
use App\Models\TestOutput;

use CDash\Database;
use CDash\Model\Build;
use CDash\Model\Project;
use CDash\Model\Site;

require_once 'include/repository.php';

class TestDetails extends BuildApi
{
    public $buildtest;
    public $testid;
    public $echoResponse;

    public function __construct(Database $db, BuildTest $buildtest)
    {
        $this->echoResponse = true;
        $this->buildtest = $buildtest;
        $this->testid = $this->buildtest->test->id;

        $build = new Build();
        $build->Id = $this->buildtest->buildid;
        $build->FillFromId($build->Id);

        parent::__construct($db, $build);
        $this->project->Fill();
    }

    public function getResponse()
    {
        // If we have a fileid we download it.
        if (isset($_GET['fileid']) && is_numeric($_GET['fileid'])) {
            $stmt = $this->db->prepare(
                    "SELECT id, value, name FROM testmeasurement
                    WHERE outputid = :outputid AND type = 'file'
                    ORDER BY id");
            $this->db->execute($stmt, [':outputid' => $this->buildtest->outputid]);
            $result_array = $stmt->fetch();
            header('Content-type: tar/gzip');
            header('Content-Disposition: attachment; filename="' . $result_array['name'] . '.tgz"');
            echo base64_decode($result_array['value']);
            flush();
            ob_flush();
            $this->echoResponse = false;
            return;
        }

        $response = begin_JSON_response();

        $site = new Site();
        $site->Id = $this->build->SiteId;
        $site->Fill();

        $this->setDate($this->build->GetDate());

        $response['title'] = "CDash : {$this->project->Name}";
        get_dashboard_JSON_by_name($this->project->Name, $this->date, $response);

        $project_response = [];
        $project_response['showtesttime'] = $this->project->ShowTestTime;
        $response['project'] = $project_response;

        $stmt = $this->db->prepare(
                'SELECT * FROM build2test b2t
                JOIN test t ON t.id = b2t.testid
                JOIN testoutput ON testoutput.id = b2t.outputid
                WHERE b2t.testid = :testid AND b2t.buildid = :buildid');
        $this->db->execute($stmt, [':testid' => $this->testid, ':buildid' => $this->build->Id]);
        $testRow = $stmt->fetch();
        $testName = $testRow['name'];
        $outputid = $testRow['outputid'];

        $menu = [];
        $menu['back'] = "/viewTest.php?buildid={$this->build->Id}";

        $previous_buildid = $this->build->GetPreviousBuildId();
        $current_buildid = $this->build->GetCurrentBuildId();
        $next_buildid = $this->build->GetNextBuildId();

        // Did the user request a specific chart?
        // If so we should make that chart appears when they click next or previous.
        $extra_url = '';
        if (array_key_exists('graph', $_GET)) {
            $extra_url = "?graph=" . $_GET['graph'];
        }

        // Previous build
        if ($previous_buildid > 0) {
            $previous_buildtestid = $this->findTest($previous_buildid);
            if ($previous_buildtestid) {
                $menu['previous'] = "/test/{$previous_buildtestid}{$extra_url}";
            }
        } else {
            $menu['previous'] = false;
        }

        // Current build
        if ($current_buildtestid = $this->findTest($current_buildid)) {
            $menu['current'] = "/test/{$current_buildtestid}{$extra_url}";
        }

        // Next build
        if ($next_buildid > 0) {
            if ($next_buildtestid = $this->findTest($next_buildid)) {
                $menu['next'] = "/test/{$next_buildtestid}{$extra_url}";
            }
        } else {
            $menu['next'] = false;
        }

        $response['menu'] = $menu;

        $summaryLink = "testSummary.php?project={$this->project->Id}&name={$testName}&date={$this->date}";

        $test_response = [];
        $test_response['id'] = $this->testid;
        $test_response['buildid'] = $this->build->Id;
        $test_response['build'] = $this->build->Name;
        $test_response['buildstarttime'] = date(FMT_DATETIMESTD, strtotime($this->build->StartTime . ' UTC'));
        $test_response['site'] = $site->Name;
        $test_response['siteid'] = $site->Id;
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
            'revisiondiff' => ''
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
                get_revision_url($this->project->Id, $status_array['revision'], $status_array['priorrevision']);
            $update_response['revisiondiff'] =
                get_revision_url($this->project->Id, $status_array['priorrevision'], ''); // no prior revision...
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
                WHERE outputid = :outputid
                ORDER BY id');
        $this->db->execute($stmt, [':outputid' => $outputid]);
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
        $stmt = $this->db->prepare(
                'SELECT name FROM measurement WHERE projectid = ? ORDER BY position');
        $this->db->execute($stmt, [$this->project->Id]);
        $extra_measurements = [];
        while ($row = $stmt->fetch()) {
            $extra_measurements[] = $row['name'];
        }

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
        return $response;
    }

    private function findTest($buildid)
    {
        $stmt = $this->db->prepare('
        SELECT id FROM build2test
        WHERE buildid = :buildid AND
              testid = :testid');
        $this->db->execute($stmt, [':buildid' => $buildid, ':testid' => $this->testid]);
        $buildtestid = $stmt->fetchColumn();
        if ($buildtestid === false) {
            return 0;
        }
        return $buildtestid;
    }

    // Remove bad characters for XML parser
    private function utf8_for_xml($string)
    {
        return preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{001b}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $string);
    }
}
