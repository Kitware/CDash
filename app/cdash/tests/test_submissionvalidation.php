<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';


use CDash\Model\Build;
use App\Models\BuildInformation;

class SubmissionValidationTestCase extends KWWebTestCase
{
    protected $PDO;
    protected $ConfigFile;
    protected $Original;

    public function _construct() {

        parent::__construct();
    }

    public function submit($fileName)
    {
        $rep = dirname(__FILE__) . '/../../../tests/data/XmlValidation';
        $file = "$rep/$fileName";
        #print_r($file);
        #print_r(file_get_contents($file));
        #print($this->submission('InsightExample', $file));
        if (false) {
            return false;
        }

        $this->assertTrue(true, "Submission of $file has succeeded");
    }

    public function testSubmissionValidation()
    {

        $this->ConfigFile = dirname(__FILE__) . '/../../../.env';
        $this->Original = file_get_contents($this->ConfigFile);
        #print_r($this->Original);
        file_put_contents($this->ConfigFile, "VALIDATE_XML_SUBMISSIONS=true\n", FILE_APPEND | LOCK_EX);

        
        $this->submit("invalid_Configure.xml");
        # $this->submit("valid_Build.xml");


        file_put_contents($this->ConfigFile, $this->Original);
    }   
}