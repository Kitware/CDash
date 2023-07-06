<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';

use CDash\Database;
use CDash\Model\Project;

class ViewSubProjectsLinkOptionTestCase extends KWWebTestCase
{
    private $project;

    public function __construct()
    {
        parent::__construct();
    }

    private function getTrilinosLink($projects)
    {
        foreach ($projects as $project) {
            if ($project['name'] == 'Trilinos') {
                return $project['link'];
            }
        }
        return '';
    }

    // Note: this test reuses existing data from 'actualtrilinossubmission'.
    public function testViewSubProjectsLinkOption()
    {
        // Verify default behavior: viewProjects.php links to
        // viewSubProjects.php for this project.
        $this->get("{$this->url}/api/v1/viewProjects.php");
        $content = $this->getBrowser()->getContent();
        $json_array = json_decode($content, true);
        $this->assertEqual('viewSubProjects.php?project=Trilinos', $this->getTrilinosLink($json_array['projects']));

        // Turn this option off in the database and verify that we now
        // get a link to index.php instead.
        \DB::table('project')
            ->where('name', 'Trilinos')
            ->update(['viewsubprojectslink' => 0]);
        $this->get("{$this->url}/api/v1/viewProjects.php");
        $content = $this->getBrowser()->getContent();
        $json_array = json_decode($content, true);
        $this->assertEqual('index.php?project=Trilinos', $this->getTrilinosLink($json_array['projects']));

        // Turn the option back on.
        \DB::table('project')
            ->where('name', 'Trilinos')
            ->update(['viewsubprojectslink' => 1]);
    }
}
