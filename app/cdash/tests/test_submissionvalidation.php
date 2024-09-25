<?php

use App\Exceptions\XMLValidationException;
use Tests\Traits\CreatesSubmissions;

class SubmissionValidationTestCase extends KWWebTestCase
{
    use CreatesSubmissions;

    protected string $ConfigFile ="";
    protected mixed $Original="";

    public function submit(string $fileName) : bool
    {
        $fileFolder = dirname(__FILE__) . '/../../../tests/data/XmlValidation';
        $file = "$fileFolder/$fileName";
        try {
            $this->submitFiles('PublicDashboard', [$file]);
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    /**
     *
     * @throws XMLValidationException
    */
    public function testSubmissionValidation() : void
    {

        $this->ConfigFile = dirname(__FILE__) . '/../../../.env';
        $this->Original = file_get_contents($this->ConfigFile);
        file_put_contents($this->ConfigFile, "VALIDATE_XML_SUBMISSIONS=true\n", FILE_APPEND | LOCK_EX);

        $this->assertFalse($this->submit("invalid_Configure.xml"), "Submission of invalid_syntax_Build.xml was succeessful when it should have failed.");
        $this->assertFalse($this->submit("invalid_syntax_Build.xml"), "Submission of invalid_syntax_Build.xml was succeessful when it should have failed.");
        $this->assertTrue($this->submit("valid_Build.xml"), "Submission of valid_Build.xml was not successful when it should have passed.");

        file_put_contents($this->ConfigFile, $this->Original);
    }
}
