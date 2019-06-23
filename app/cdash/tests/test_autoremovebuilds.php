<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/pdo.php';

class AutoRemoveBuildsTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function launchViaCommandLine($arg1)
    {
        global $cdashpath;
        global $PHP_EXE;
        $cmd = "\"$PHP_EXE\" \"$cdashpath/scripts/autoRemoveBuilds.php\"";
        if ($arg1) {
            $cmd = $cmd . " \"$arg1\"";
        }
        echo "Running command line:\n";
        echo "  cmd='${cmd}'\n";
        passthru($cmd);
        echo "\n";
        echo "Done with command line\n";
    }

    public function testAutoRemoveBuilds()
    {
        global $configure;
        $dir = $configure['svnroot'];

        chdir($dir);
        $argv[0] = 'autoRemoveBuilds.php';
        $argc = 1;

        ob_start();
        $this->launchViaCommandLine('');
        $output = ob_get_contents();
        ob_end_clean();

        if (strpos($output, 'Usage: php') === false) {
            $this->fail("Expected output not found from autoRemoveBuilds.php.\n$output\n");
        }

        chdir($dir);
        $argv[0] = 'autoRemoveBuilds.php';
        $argv[1] = 'InsightExample';
        $argc = 2;

        ob_start();
        $this->launchViaCommandLine('InsightExample');
        $output = ob_get_contents();
        ob_end_clean();

        if (strpos($output, 'removing builds for InsightExample') === false) {
            $this->fail("Expected output not found from autoRemoveBuilds.php.\n$output\n");
            $error = 1;
        } elseif (strpos($output, 'removing old buildids') === false) {
            $this->fail("Autoremovebuilds failed to remove old build by buildgroup setting.\n$output\n");
            $error = 1;
        } else {
            $this->pass('Passed');
            $error = 0;
        }

        return $error;
    }
}
