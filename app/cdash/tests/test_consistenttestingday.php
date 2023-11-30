<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';

use App\Utils\TestingDay;
use CDash\Database;
use CDash\Model\Build;
use CDash\Model\Project;

class ConsistentTestingDayTestCase extends KWWebTestCase
{
    private $project;

    public function __construct()
    {
        parent::__construct();
        $this->project = null;
    }

    public function __destruct()
    {
        // Delete project & builds created by this test.
        if ($this->project) {
            remove_project_builds($this->project->Id);
            $this->project->Delete();
        }
    }

    public function testConsistentTestingDay()
    {
        // Create test project.
        $this->login();
        $this->project = new Project();
        $this->project->Id = $this->createProject([
            'Name' => 'ConsistentTestingDay',
            'NightlyTime' => '16:30:00 America/New_York',
        ]);
        $this->project->Fill();

        // Submit our testing data.
        $dir = dirname(__FILE__) . '/data/TestingDay';
        $this->submission('ConsistentTestingDay', "$dir/Test_1.xml");
        $this->submission('ConsistentTestingDay', "$dir/Test_2.xml");

        // Get the builds we created.
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT id, name from build where projectid = :projectid');
        $db->execute($stmt, [':projectid' => $this->project->Id]);
        $rows = $stmt->fetchAll();
        $this->assertEqual(count($rows), 2);
        $before = new Build();
        $after = new Build();
        foreach ($rows as $row) {
            if ($row['name'] === 'before') {
                $before->Id = $row['id'];
            } elseif ($row['name'] === 'after') {
                $after->Id = $row['id'];
            } else {
                $this->fail("Unexpected build name {$row['name']}");
            }
        }

        // Make sure they belong to the expected testing days.
        $before->FillFromId($before->Id);
        $after->FillFromId($after->Id);
        $this->assertEqual('2020-05-12', TestingDay::get($this->project, $before->StartTime));
        $this->assertEqual('2020-05-13', TestingDay::get($this->project, $after->StartTime));

        // Check index.php, make sure it shows these builds on the correct days.
        $this->get($this->url . '/api/v1/index.php?project=ConsistentTestingDay&date=2020-05-12');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);
        $this->assertEqual(1, count($buildgroup['builds']));
        $this->assertEqual('before', $buildgroup['builds'][0]['buildname']);

        $this->get($this->url . '/api/v1/index.php?project=ConsistentTestingDay&date=2020-05-13');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);
        $this->assertEqual(1, count($buildgroup['builds']));
        $this->assertEqual('after', $buildgroup['builds'][0]['buildname']);

        // Similarly check queryTest.php.
        $this->get($this->url . '/api/v1/queryTests.php?project=ConsistentTestingDay&date=2020-05-12');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertEqual(1, count($jsonobj['builds']));
        $this->assertEqual('before', $jsonobj['builds'][0]['buildName']);

        $this->get($this->url . '/api/v1/queryTests.php?project=ConsistentTestingDay&date=2020-05-13');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertEqual(1, count($jsonobj['builds']));
        $this->assertEqual('after', $jsonobj['builds'][0]['buildName']);

        // Different time zone case.
        $this->project->SetNightlyTime('10:00:00 America/Denver');
        $this->project->Save();
        $this->submission('ConsistentTestingDay', "$dir/Build_1.xml");

        // This build occurred slightly before the nightly start time,
        // so verify it shows up on the correct day.
        $this->get($this->url . '/api/v1/index.php?project=ConsistentTestingDay&date=2020-06-10');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);
        $this->assertEqual(1, count($buildgroup['builds']));

        $this->get($this->url . '/api/v1/index.php?project=ConsistentTestingDay&date=2020-06-11');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertEqual(0, count($jsonobj['buildgroups']));
    }
}
