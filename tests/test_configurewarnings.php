<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Model\BuildConfigure;

class ConfigureWarningTestCase extends KWWebTestCase
{
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
}
