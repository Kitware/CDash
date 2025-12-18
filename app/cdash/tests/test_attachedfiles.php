<?php

require_once __DIR__ . '/cdash_test_case.php';

use CDash\Database;
use CDash\Model\Project;

class AttachedFilesTestCase extends KWWebTestCase
{
    private ?Project $project;

    public function __construct()
    {
        parent::__construct();
        $this->project = null;
    }

    public function __destruct()
    {
        // Delete project & build created by this test.
        if ($this->project !== null) {
            remove_project_builds($this->project->Id);
            App\Models\Project::findOrFail((int) $this->project->Id)->delete();
        }
    }

    public function testAttachedFiles(): void
    {
        // Create test project.
        $this->login();
        $this->project = new Project();
        $this->project->Id = $this->createProject([
            'Name' => 'AttachedFiles',
        ]);
        $this->project->Fill();

        // Submit our testing data.
        $this->submission('AttachedFiles', __DIR__ . '/data/AttachedFileTest.xml');

        // Get the buildtest we created.
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT b2t.id AS buildtestid
            FROM build b
            JOIN build2test b2t ON (b2t.buildid = b.id)
            WHERE b.projectid = :projectid');
        $db->execute($stmt, [':projectid' => $this->project->Id]);
        $buildtestid = $stmt->fetchColumn();

        // Download, decompress, and examine the attached file.
        $downloaded_file = sys_get_temp_dir() . '/output.txt.tgz';
        $decompressed_file = sys_get_temp_dir() . '/output.txt';
        $client = $this->getGuzzleClient();
        $client->request('GET',
            "{$this->url}/api/v1/testDetails.php?buildtestid={$buildtestid}&fileid=1",
            ['sink' => $downloaded_file]);

        if (!file_exists($downloaded_file)) {
            $this->fail('File download failed');
        }

        $phar = new PharData($downloaded_file);
        $phar->extractTo(sys_get_temp_dir());
        if (!file_exists($decompressed_file)) {
            $this->fail('output.txt does not exist after decompress');
        }

        $contents = file_get_contents($decompressed_file);
        $this->assertTrue(str_contains($contents, 'This is my test output'));

        unlink($downloaded_file);
        unlink($decompressed_file);
    }
}
