<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class EnableAsynchronousTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testEnableAsynchronous()
    {
        $filename = dirname(__FILE__) . '/../config/config.local.php';
        $handle = fopen($filename, 'r');
        $contents = fread($handle, filesize($filename));
        fclose($handle);
        unset($handle);
        $handle = fopen($filename, 'w');
        $lines = explode("\n", $contents);
        foreach ($lines as $line) {
            if (strpos($line, '?>') !== false) {
                fwrite($handle, '// test config settings injected by file [' . __FILE__ . "]\n");
                fwrite($handle, '$CDASH_ASYNCHRONOUS_SUBMISSION = true;' . "\n");
            }
            if ($line != '') {
                fwrite($handle, "$line\n");
            }
        }
        fclose($handle);
        unset($handle);
        $this->pass('Passed');
    }
}
