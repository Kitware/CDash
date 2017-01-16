<?php

require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';

class ExtractTarTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testExtractTarArchiveTarWithInvalidFile()
    {
        $result = extract_tar_archive_tar(dirname(__FILE__) . '/../config/config.php', 'foo');

        $this->assertFalse($result);
        $this->assertTrue(is_readable($this->logfilename));

        $logfileContents = file_get_contents($this->logfilename);

        $this->assertTrue($this->findString($logfileContents, 'ERROR'));
        $this->assertTrue($this->findString($logfileContents, 'extract_tar'));
    }
}
