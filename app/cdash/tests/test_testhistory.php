<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';



use CDash\Model\Project;

class TestHistoryTestCase extends KWWebTestCase
{
    protected $project;

    public function __construct()
    {
        parent::__construct();
        $this->project = null;
        $this->deleteLog($this->logfilename);
    }

    private function generateXML($mm, $timestamp, $include_sporadic, $flaky_passed)
    {
        if ($flaky_passed) {
            $flaky_status = 'passed';
            $flaky_time = 1;
        } else {
            $flaky_status = 'failed';
            $flaky_time = 0;
        }

        $retval = <<<EOT
            <?xml version="1.0" encoding="UTF-8"?>
            <Site BuildName="TestHistory"
              BuildStamp="20151116-19{$mm}-Experimental"
              Name="localhost"
              Generator="ctest"
              >
              <Testing>
                <StartTestTime>{$timestamp}</StartTestTime>
                <TestList>
                  <Test>./passes</Test>
                  <Test>./fails</Test>
                  <Test>./flaky</Test>
                  <Test>./notrun</Test>

            EOT;
        if ($include_sporadic) {
            $retval .= <<<EOT
                      <Test>./sporadic</Test>

                EOT;
        }
        $retval .= <<<EOT
                </TestList>
                <Test Status="passed">
                  <Name>passes</Name>
                  <Path>.</Path>
                  <FullName>./passes</FullName>
                  <FullCommandLine>/bin/true</FullCommandLine>
                  <Results>
                    <NamedMeasurement type="numeric/double" name="Execution Time">
                      <Value>0</Value>
                    </NamedMeasurement>
                    <NamedMeasurement type="text/string" name="Completion Status">
                      <Value>Completed</Value>
                    </NamedMeasurement>
                    <NamedMeasurement type="text/string" name="Command Line">
                      <Value>/bin/true</Value>
                    </NamedMeasurement>
                    <Measurement>
                      <Value></Value>
                    </Measurement>
                  </Results>
                </Test>
                <Test Status="failed">
                  <Name>fails</Name>
                  <Path>.</Path>
                  <FullName>./fails</FullName>
                  <FullCommandLine>/bin/false</FullCommandLine>
                  <Results>
                    <NamedMeasurement type="text/string" name="Exit Code">
                      <Value>Failed</Value>
                    </NamedMeasurement>
                    <NamedMeasurement type="numeric/double" name="Execution Time">
                      <Value>0</Value>
                    </NamedMeasurement>
                    <NamedMeasurement type="text/string" name="Completion Status">
                      <Value>Completed</Value>
                    </NamedMeasurement>
                    <NamedMeasurement type="text/string" name="Command Line">
                      <Value>/bin/false</Value>
                    </NamedMeasurement>
                    <Measurement>
                      <Value></Value>
                    </Measurement>
                  </Results>
                </Test>
                <Test Status="{$flaky_status}">
                  <Name>flaky</Name>
                  <Path>.</Path>
                  <FullName>./flaky</FullName>
                  <FullCommandLine>/bin/flaky</FullCommandLine>
                  <Results>

            EOT;
        if (!$flaky_passed) {
            $retval .= <<<EOT
                        <NamedMeasurement type="text/string" name="Exit Code">
                          <Value>Failed</Value>
                        </NamedMeasurement>

                EOT;
        }
        $retval .= <<<EOT
                    <NamedMeasurement type="numeric/double" name="Execution Time">
                      <Value>{$flaky_time}</Value>
                    </NamedMeasurement>
                    <NamedMeasurement type="text/string" name="Completion Status">
                      <Value>Completed</Value>
                    </NamedMeasurement>
                    <NamedMeasurement type="text/string" name="Command Line">
                      <Value>/bin/flaky</Value>
                    </NamedMeasurement>
                    <Measurement>
                      <Value></Value>
                    </Measurement>
                  </Results>
                </Test>
                <Test Status="notrun">
                  <Name>notrun</Name>
                  <Path>.</Path>
                  <FullName>./notrun</FullName>
                  <FullCommandLine></FullCommandLine>
                  <Results>
                    <NamedMeasurement type="text/string" name="Command Line">
                      <Value></Value>
                    </NamedMeasurement>
                    <Measurement>
                      <Value>Unable to find executable: /tmp/notrun</Value>
                    </Measurement>
                  </Results>
                </Test>

            EOT;
        if ($include_sporadic) {
            $retval .= <<<EOT
                    <Test Status="passed">
                      <Name>sporadic</Name>
                      <Path>.</Path>
                      <FullName>./sporadic</FullName>
                      <FullCommandLine>/bin/true</FullCommandLine>
                      <Results>
                        <NamedMeasurement type="numeric/double" name="Execution Time">
                          <Value>0</Value>
                        </NamedMeasurement>
                        <NamedMeasurement type="text/string" name="Completion Status">
                          <Value>Completed</Value>
                        </NamedMeasurement>
                        <NamedMeasurement type="text/string" name="Command Line">
                          <Value>/bin/true</Value>
                        </NamedMeasurement>
                        <Measurement>
                          <Value></Value>
                        </Measurement>
                      </Results>
                    </Test>

                EOT;
        }
        $retval .= <<<EOT
                <EndDateTime>Nov 16 14:{$mm} EST</EndDateTime>
                <EndTestTime>{$timestamp}</EndTestTime>
                <ElapsedMinutes>0</ElapsedMinutes>
              </Testing>
            </Site>

            EOT;
        return $retval;
    }

    public function testTestHistory()
    {
        // Make sure we start from scratch each time the test is run.
        $this->project = new Project();
        if ($this->project->FindByName('TestHistory')) {
            remove_project_builds($this->project->Id);
            $this->project->Delete();
        }

        // Create test project.
        $this->login();
        $this->project = new Project();
        $this->project->Id = $this->createProject([
            'Name' => 'TestHistory',
        ]);
        $this->project->Fill();

        // Generate and submit our testing data.
        foreach (range(1, 5) as $i) {
            $minute = '0' . (3 + $i);
            $timestamp = 1447700623 + (60 * $i);
            $even = $i % 2;
            $xml_contents = $this->generateXML($minute, $timestamp, $even, !$even);
            $test_filename = "TestHistory_Test{$i}.xml";
            file_put_contents($test_filename, $xml_contents);
            if (!$this->submission('TestHistory', $test_filename)) {
                $this->fail("Failed to submit $test_filename");
            }
            unlink($test_filename);
        }

        // Verify no errors in the log.
        $this->assertTrue($this->checkLog($this->logfilename) !== false);

        // Get the IDs for the five builds that we just created.
        $builds = \DB::select(
            DB::raw(
                "SELECT b.id AS buildid, b2g.groupid
            FROM build b
            JOIN build2group b2g ON (b2g.buildid = b.id)
            WHERE name='TestHistory'
            ORDER BY b.starttime")
        );
        $this->assertEqual(5, count($builds));
        $groupid = $builds[0]->groupid;
        $buildids = [];
        foreach ($builds as $build) {
            $buildids[] = $build->buildid;
        }

        // Verify handling of bad 'previous_builds' parameter.
        $url = "{$this->url}/api/v1/viewTest.php?buildid={$buildids[4]}&groupid={$groupid}&previous_builds=exit(1)&projectid={$this->project->Id}&tests%5B%5D=fails&tests%5B%5D=flaky&tests%5B%5D=notrun&tests%5B%5D=passes&tests%5B%5D=sporadic&time_begin=2015-11-16T01:00:00&time_end=2015-11-17T01:00:00";
        $client = $this->getGuzzleClient();
        $response = $client->request('GET', $url, ['http_errors' => false]);
        $expected = '{"tests":[{"name":"fails","summary":"Broken","summaryclass":"error"},{"name":"flaky","summary":"Unstable","summaryclass":"warning"},{"name":"notrun","summary":"Inactive","summaryclass":"warning"},{"name":"passes","summary":"Stable","summaryclass":"normal"},{"name":"sporadic","summary":"Stable","summaryclass":"normal"}]}';
        $this->assertEqual($expected, strval($response->getBody()));

        // Verify that testing history matches what we expect.
        $previous_buildids = "{$buildids[4]},+{$buildids[3]},+{$buildids[2]},+{$buildids[1]}";
        $url = "{$this->url}/api/v1/viewTest.php?buildid={$buildids[4]}&groupid={$groupid}&previous_builds={$previous_buildids}&projectid={$this->project->Id}&tests%5B%5D=fails&tests%5B%5D=flaky&tests%5B%5D=notrun&tests%5B%5D=passes&tests%5B%5D=sporadic&time_begin=2015-11-16T01:00:00&time_end=2015-11-17T01:00:00";
        $client = $this->getGuzzleClient();
        $response = $client->request('GET',
            $url,
            ['http_errors' => false]);
        $jsonobj = json_decode($response->getBody(), true);
        foreach ($jsonobj['tests'] as $test) {
            $history = $test['history'];
            switch ($test['name']) {
                case 'fails':
                    if ($history != 'Broken') {
                        $this->fail("Expected history for test 'fails' to be 'Broken', instead found '$history'");
                    }
                    break;
                case 'notrun':
                    if ($history != 'Inactive') {
                        $this->fail("Expected history for test 'notrun' to be 'Inactive', instead found '$history'");
                    }
                    break;
                case 'flaky':
                    if ($history != 'Unstable') {
                        $this->fail("Expected history for test 'flaky' to be 'Unstable', instead found '$history'");
                    }
                    break;
                case 'passes':
                    if ('passes' && $history != 'Stable') {
                        $this->fail("Expected history for test 'passes' to be 'Stable', instead found '$history'");
                    }
                    break;
                case 'sporadic':
                    if ('sporadic' && $history != 'Stable') {
                        $this->fail("Expected history for test 'sporadic' to be 'Stable', instead found '$history'");
                    }
                    break;
                default:
                    $this->fail("Unexpected test encountered: {$test['name']}");
                    break;
            }
        }

        // Verify test graphs for our 'flaky' test.
        $test_result = \DB::select(
            "SELECT id FROM test WHERE name = 'flaky' AND projectid = {$this->project->Id}");
        $testid = $test_result[0]->id;

        // test graph
        $this->get("{$this->url}/api/v1/testGraph.php?testid={$testid}&buildid={$buildids[4]}&type=time");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        // Execution time
        $this->assertEqual('Execution Time (seconds)', $jsonobj[0]['label']);
        $this->assertEqual(0, $jsonobj[0]['data'][0]['y']);
        $this->assertEqual(1, $jsonobj[0]['data'][1]['y']);
        $this->assertEqual(0, $jsonobj[0]['data'][2]['y']);
        $this->assertEqual(1, $jsonobj[0]['data'][3]['y']);
        $this->assertEqual(0, $jsonobj[0]['data'][4]['y']);

        // Acceptable range
        $this->assertEqual('Acceptable Range', $jsonobj[1]['label']);
        $this->assertEqual(0, $jsonobj[1]['data'][0]['y']);
        $this->assertEqual(3.98, $jsonobj[1]['data'][1]['y']);
        $this->assertEqual(3.98, $jsonobj[1]['data'][2]['y']);
        $this->assertEqual(4.03, $jsonobj[1]['data'][3]['y']);
        $this->assertEqual(4.03, $jsonobj[1]['data'][4]['y']);

        // status graph
        $flaky_ids = [];
        foreach (range(0, 4) as $i) {
            $flaky_ids[] = \DB::table('build2test')
                ->where('buildid', '=', $buildids[$i])
                ->where('testid', '=', $testid)
                ->value('id');
        }
        $this->assertEqual(5, count($flaky_ids));

        $this->get("{$this->url}/api/v1/testGraph.php?testid={$testid}&buildid={$buildids[4]}&type=status");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);

        // Passing tests
        $this->assertEqual('Passed', $jsonobj[0]['label']);
        $this->assertEqual(2, count($jsonobj[0]['data']));
        $this->assertEqual($flaky_ids[1], $jsonobj[0]['data'][0]['buildtestid']);
        $this->assertEqual($flaky_ids[3], $jsonobj[0]['data'][1]['buildtestid']);

        // Failing tests
        $this->assertEqual('Failed', $jsonobj[1]['label']);
        $this->assertEqual(3, count($jsonobj[1]['data']));
        $this->assertEqual($flaky_ids[0], $jsonobj[1]['data'][0]['buildtestid']);
        $this->assertEqual($flaky_ids[2], $jsonobj[1]['data'][1]['buildtestid']);
        $this->assertEqual($flaky_ids[4], $jsonobj[1]['data'][2]['buildtestid']);

        // Verify next/previous/current for our sporadic test.
        $test_result = \DB::select(
            "SELECT id FROM test WHERE name = 'sporadic' AND projectid = {$this->project->Id}");
        $testid = $test_result[0]->id;
        $sporadic_ids = [];
        foreach (range(0, 4, 2) as $i) {
            $sporadic_ids[] = \DB::table('build2test')
                ->where('buildid', '=', $buildids[$i])
                ->where('testid', '=', $testid)
                ->value('id');
        }
        $this->assertEqual(3, count($sporadic_ids));

        // Verify menus for sporadic #0.
        $this->get("{$this->url}/api/v1/testDetails.php?buildtestid={$sporadic_ids[0]}");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        // It belongs to build #0.
        $this->assertEqual($buildids[0], $jsonobj['test']['buildid']);
        // No previous.
        $this->assertFalse($jsonobj['menu']['previous']);
        // Next points to the sporadic #1
        $this->assertEqual("/test/{$sporadic_ids[1]}", $jsonobj['menu']['next']);
        // Current points to sporadic #2
        $this->assertEqual("/test/{$sporadic_ids[2]}", $jsonobj['menu']['current']);

        // Verify menus for sporadic #1.
        $this->get("{$this->url}/api/v1/testDetails.php?buildtestid={$sporadic_ids[1]}");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        // It belongs to build #2.
        $this->assertEqual($buildids[2], $jsonobj['test']['buildid']);
        // Previous points to sporadic #0.
        $this->assertEqual("/test/{$sporadic_ids[0]}", $jsonobj['menu']['previous']);
        // Next points to the sporadic #2
        $this->assertEqual("/test/{$sporadic_ids[2]}", $jsonobj['menu']['next']);
        // Current points to sporadic #2
        $this->assertEqual("/test/{$sporadic_ids[2]}", $jsonobj['menu']['current']);

        // Verify menus for sporadic #2.
        $this->get("{$this->url}/api/v1/testDetails.php?buildtestid={$sporadic_ids[2]}");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        // It belongs to build #4.
        $this->assertEqual($buildids[4], $jsonobj['test']['buildid']);
        // Previous points to sporadic #1.
        $this->assertEqual("/test/{$sporadic_ids[1]}", $jsonobj['menu']['previous']);
        // No next.
        $this->assertFalse($jsonobj['menu']['next']);
        // Current points to sporadic #2 (this buildtest).
        $this->assertEqual("/test/{$sporadic_ids[2]}", $jsonobj['menu']['current']);
    }
}
