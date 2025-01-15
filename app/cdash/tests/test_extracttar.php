<?php

class ExtractTarTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testExtractTarArchiveTarWithInvalidFile()
    {
        $result = extract_tar(dirname(__FILE__) . '/../config/config.php', 'foo');

        $this->assertFalse($result);
        $this->assertIsReadable($this->logfilename);

        $logfileContents = file_get_contents($this->logfilename);

        $this->assertTrue($this->findString($logfileContents, 'ERROR'));
        $this->assertTrue($this->findString($logfileContents, 'extract_tar'));
    }
}
