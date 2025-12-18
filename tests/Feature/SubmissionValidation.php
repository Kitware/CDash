<?php

namespace Tests\Feature;

use App\Models\Project;
use Exception;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

class SubmissionValidation extends TestCase
{
    use CreatesProjects;
    use CreatesSubmissions;

    protected string $ConfigFile = '';
    protected mixed $Original = '';
    protected Project $project;

    public function setUp(): void
    {
        parent::setUp();
        $this->ConfigFile = base_path('.env');
        $this->Original = file_get_contents($this->ConfigFile);
        $this->project = $this->makePublicProject();
    }

    public function writeEnvEntry(string $value): void
    {
        file_put_contents($this->ConfigFile, "VALIDATE_SUBMISSIONS={$value}\n", FILE_APPEND | LOCK_EX);
    }

    public function submit(string $fileName): bool
    {
        $file = base_path("tests/data/XmlValidation/$fileName");
        try {
            $this->submitFiles($this->project->name, [$file]);
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    /** Check that error messages are logged but submission succeeds
     *  when the environment variable is not set
     */
    public function testSubmissionValidationNoEnv(): void
    {
        $this::assertTrue($this->submit('invalid_Configure.xml'), 'Submission of invalid_Configure.xml was not successful when it should have passed.');
        $this::assertTrue($this->submit('invalid_syntax_Build.xml'), 'Submission of invalid_syntax_Build.xml  was not successful when it should have passed.');
        $this::assertTrue($this->submit('valid_Configure1.xml'), 'Submission of valid_Configure1.xml was not successful when it should have passed.');
        $this::assertTrue($this->submit('valid_Configure2.xml'), 'Submission of valid_Configure2.xml was not successful when it should have passed.');
        $this::assertTrue($this->submit('valid_Build.xml'), 'Submission of valid_Build.xml was not successful when it should have passed.');
    }

    /** Check that error messages are logged but submission succeeds
     *  when the environment variable is set but to false
     */
    public function testSubmissionValidationSilent(): void
    {
        $this->writeEnvEntry('SILENT');
        $this::assertTrue($this->submit('invalid_Configure.xml'), 'Submission of invalid_Configure.xml was not successful when it should have passed.');
        $this::assertTrue($this->submit('invalid_syntax_Build.xml'), 'Submission of invalid_syntax_Build.xml  was not successful when it should have passed.');
        $this::assertTrue($this->submit('valid_Configure1.xml'), 'Submission of valid_Configure1.xml was not successful when it should have passed.');
        $this::assertTrue($this->submit('valid_Configure2.xml'), 'Submission of valid_Configure2.xml was not successful when it should have passed.');
        $this::assertTrue($this->submit('valid_Build.xml'), 'Submission of valid_Build.xml was not successful when it should have passed.');
    }

    /** Check that error messages are logged but submission succeeds
     *  when the environment variable is set but to WARN
     */
    public function testSubmissionValidationWarn(): void
    {
        $this->writeEnvEntry('WARN');
        $this::assertTrue($this->submit('invalid_Configure.xml'), 'Submission of invalid_Configure.xml was not successful when it should have passed.');
        $this::assertTrue($this->submit('invalid_syntax_Build.xml'), 'Submission of invalid_syntax_Build.xml  was not successful when it should have passed.');
        $this::assertTrue($this->submit('valid_Configure1.xml'), 'Submission of valid_Configure1.xml was not successful when it should have passed.');
        $this::assertTrue($this->submit('valid_Configure2.xml'), 'Submission of valid_Configure2.xml was not successful when it should have passed.');
        $this::assertTrue($this->submit('valid_Build.xml'), 'Submission of valid_Build.xml was not successful when it should have passed.');
    }

    /** Check that the submission is dependent upon passing validation
     *  when the environment variable is set to REJECT
     */
    public function testSubmissionValidationReject(): void
    {
        $this->writeEnvEntry('REJECT');
        $this::assertFalse($this->submit('invalid_Configure.xml'), 'Submission of invalid_Configure.xml was successful when it should have failed.');
        $this::assertFalse($this->submit('invalid_syntax_Build.xml'), 'Submission of invalid_syntax_Build.xml was successful when it should have failed.');
        $this::assertTrue($this->submit('valid_Configure1.xml'), 'Submission of valid_Configure1.xml was not successful when it should have passed.');
        $this::assertTrue($this->submit('valid_Configure2.xml'), 'Submission of valid_Configure2.xml was not successful when it should have passed.');
        $this::assertTrue($this->submit('valid_Build.xml'), 'Submission of valid_Build.xml was not successful when it should have passed.');
    }

    public function tearDown(): void
    {
        $this->project->delete();

        file_put_contents($this->ConfigFile, $this->Original);
        parent::tearDown();
    }
}
