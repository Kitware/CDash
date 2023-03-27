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
        $filename = dirname(__FILE__) . '/../../../.env';
        // Using .env, we no longer have to worry about being inside the closing PHP bracket.
        $injectedText = "// test config settings injected by file [' . __FILE__ . ']\nCDASH_ASYNCHRONOUS_SUBMISSION = true";
        file_put_contents($filename, $injectedText, FILE_APPEND);
        $this->pass('Passed');
    }
}
