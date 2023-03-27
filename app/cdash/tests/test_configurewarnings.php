<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Database;
use CDash\Model\BuildConfigure;

class ConfigureWarningTestCase extends KWWebTestCase
{
    protected $ProjectId;

    public function __construct()
    {
        parent::__construct();
    }

    public function testConfigureWarning()
    {
        $warning_lines = array(
            'CMake Warning (dev) at some/file/path:1234 (MESSAGE):',
            'WARNING: blah blah blah');

        $non_warning_lines = array(
            'warning: blah blah blah',
            'WARNING : blah blah blah',
            'WARNING some other text: blah blah blah',
            'This warning is for project developers. Use -Wno-dev to suppress it.',
            '<<< Configuring library with warnings >>>',
            'library warnings................. : yes');

        foreach ($warning_lines as $line) {
            if (!BuildConfigure::IsConfigureWarning($line)) {
                $this->fail("This was not considered a configure warning when it should be: $line");
            }
        }

        foreach ($non_warning_lines as $line) {
            if (BuildConfigure::IsConfigureWarning($line)) {
                $this->fail("This was considered a configure warning when it should not be: $line");
            }
        }
    }

    public function testConfigureWarningDiff()
    {
        // Create a project for this test.
        $settings = [
            'Name' => 'ConfigureWarningProject',
            'Description' => 'ConfigureWarningProject'
        ];
        $this->ProjectId = $this->createProject($settings);
        if ($this->ProjectId < 1) {
            $this->fail('Failed to create project');
            return;
        }

        // Submit our testing data.
        $dir = dirname(__FILE__) . '/data/ConfigureWarnings';
        $this->submission('ConfigureWarningProject', "$dir/1.xml");
        $this->submission('ConfigureWarningProject', "$dir/2.xml");

        // Make sure the +1 warning gets associated with the correct build.
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT id FROM build WHERE projectid = :projectid ORDER BY starttime');
        $db->execute($stmt, [':projectid' => $this->ProjectId]);
        $buildid1 = $stmt->fetchColumn();
        $buildid2 = $stmt->fetchColumn();

        $stmt = $db->prepare('SELECT difference FROM configureerrordiff WHERE buildid = :buildid');
        $db->execute($stmt, [':buildid' => $buildid1]);
        $num_warnings = $stmt->fetchColumn();
        if ($num_warnings !== false) {
            $this->fail('Found an unexpected row for first build');
        }
        $db->execute($stmt, [':buildid' => $buildid2]);
        $num_warnings = $stmt->fetchColumn();
        if ($num_warnings != 1) {
            $this->fail("Expected 1 but got $num_warnings for second build");
        }
        $this->deleteProject($this->ProjectId);
    }
}
