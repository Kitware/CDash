<?php

namespace Tests\Feature;

use App\Models\Project;

use Tests\Traits\CreatesSubmissions;
use Tests\Traits\CreatesProjects;
use Tests\TestCase;

class SubmissionValidation extends TestCase
{
    use CreatesSubmissions;
    use CreatesProjects;

    protected string $ConfigFile ="";
    protected mixed $Original="";
    protected Project|null $project = null;

    public function setUp() : void
    {
        parent::setUp();
        $this->ConfigFile = dirname(__FILE__) . '/../../.env';
        $this->Original = file_get_contents($this->ConfigFile);
        file_put_contents($this->ConfigFile, "VALIDATE_XML_SUBMISSIONS=true\n", FILE_APPEND | LOCK_EX);
        $this->project = $this->makePublicProject();
    }


    public function submit(string $fileName) : bool
    {
        $fileFolder = dirname(__FILE__) . '/../data/XmlValidation';
        $file = "$fileFolder/$fileName";
        try {
            $this->submitFiles($this->project->name, [$file]);
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }

    public function testSubmissionValidation() : void
    {
        $this::assertFalse($this->submit("invalid_Configure.xml"), "Submission of invalid_syntax_Build.xml was successful when it should have failed.");
        $this::assertFalse($this->submit("invalid_syntax_Build.xml"), "Submission of invalid_syntax_Build.xml was successful when it should have failed.");
        $this::assertTrue($this->submit("valid_Build.xml"), "Submission of valid_Build.xml was not successful when it should have passed.");
    }


    public function tearDown() : void
    {
        if ($this->project !== null) {
            $this->project->delete();
        }

        file_put_contents($this->ConfigFile, $this->Original);
        parent::tearDown();
    }
}
