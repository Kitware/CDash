<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';



class SubmissionValidationTestCase extends KWWebTestCase
{
    protected $PDO;
    protected $ConfigFile;
    protected $Original;

    public function _construct()
    {

        parent::__construct();
    }

    public function submit($fileName)
    {
        $rep = dirname(__FILE__) . '/../../../tests/data/XmlValidation';
        $file = "$rep/$fileName";

        return $this->submission('PublicDashboard', $file);
    }

    public function testSubmissionValidation()
    {

        $this->ConfigFile = dirname(__FILE__) . '/../../../.env';
        $this->Original = file_get_contents($this->ConfigFile);
        file_put_contents($this->ConfigFile, "VALIDATE_XML_SUBMISSIONS=true\n", FILE_APPEND | LOCK_EX);

        $this->assertFalse($this->submit("invalid_Configure.xml"), "Submission of invalid_syntax_Build.xml was not succeessful");
        $this->assertFalse($this->submit("invalid_syntax_Build.xml"), "Submission of invalid_syntax_Build.xml was not succeessful");
        $this->assertTrue($this->submit("valid_Build.xml"), "Submission of valid_Build.xml has succeeded");

        file_put_contents($this->ConfigFile, $this->Original);
    }
}
