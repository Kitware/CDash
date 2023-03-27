<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';

use CDash\Model\Project;

class NumericUpdateTestCase extends KWWebTestCase
{
    private $project;

    public function __construct()
    {
        parent::__construct();
        $this->project = null;
    }

    public function __destruct()
    {
        // Delete project & build created by this test.
        if ($this->project) {
            remove_project_builds($this->project->Id);
            $this->project->Delete();
        }
    }

    public function testNumericUpdate()
    {
        // Create test project.
        $this->login();
        $this->project = new Project();
        $this->project->Id = $this->createProject([
            'Name' => 'NumericUpdate',
        ]);
        $this->project->Fill();

        // Submit our testing data.
        $this->submission('NumericUpdate', dirname(__FILE__) . '/data/UpdateNumeric.xml');

        // Check index.php, make sure it shows the expected revision.
        $this->get($this->url . '/api/v1/index.php?project=NumericUpdate&date=2020-06-01');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);
        $this->assertTrue('084161' === $buildgroup['builds'][0]['update']['files']);

        // Verify Revision and PriorRevision on viewUpdate.php.
        $buildid = $buildgroup['builds'][0]['id'];
        $this->get("{$this->url}/api/v1/viewUpdate.php?buildid={$buildid}");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertTrue('08416132611118b6817515907055112111663314' === $jsonobj['update']['revision']);
        $this->assertTrue('07416132611118b6817515907055112111663314' === $jsonobj['update']['priorrevision']);
    }
}
