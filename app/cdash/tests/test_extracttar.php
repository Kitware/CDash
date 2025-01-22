<?php

class ExtractTarTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testExtractTarArchiveTarWithInvalidFile()
    {
        $result = extract_tar('this_file_does_not_exist');

        $this->assertTrue($result === '');
        $this->assertTrue(is_readable($this->logfilename));

        $logfileContents = file_get_contents($this->logfilename);

        $this->assertTrue($this->findString($logfileContents, 'ERROR'));
        $this->assertTrue($this->findString($logfileContents, 'extract_tar'));
    }
}
