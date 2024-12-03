<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
use Tests\Traits\CreatesSubmissions;

require_once dirname(__FILE__) . '/cdash_test_case.php';

class DeleteSubProjectTestCase extends KWWebTestCase
{
    use CreatesSubmissions;

    public function testDeleteSubProject()
    {
        $this->get($this->url . '/api/v1/viewSubProjects.php?project=Trilinos');
        $this->assertText('FEApp');

        echo "submitting data/DeleteSubProject/Project.xml\n";
        $file = dirname(__FILE__) . '/data/DeleteSubProject/Project.xml';

        $this->submitFiles('Trilinos', [$file]);

        $this->get($this->url . '/api/v1/viewSubProjects.php?project=Trilinos');
        $this->assertNoText('FEApp');

        $this->deleteLog($this->logfilename);
    }
}
