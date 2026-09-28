<?php

namespace Tests\Feature\Submission;

use App\Enums\SubmissionValidationType;
use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

class XmlValidationTest extends TestCase
{
    use CreatesProjects;
    use CreatesSubmissions;
    use DatabaseTransactions;

    protected Project $project;

    public function setUp(): void
    {
        parent::setUp();
        $this->project = $this->makePublicProject();
    }

    public function submit(string $fileName, int $expected_status = 200): void
    {
        $file = base_path("tests/data/XmlValidation/$fileName");
        $this->makeSubmission($this->project->name, $file, $expected_status);
    }

    /** Check that error messages are logged but submission succeeds
     *  when the environment variable is not set
     */
    public function testSubmissionValidationNoEnv(): void
    {
        $this->submit('invalid_Configure.xml');
        $this->submit('invalid_syntax_Build.xml');
        $this->submit('valid_Configure1.xml');
        $this->submit('valid_Configure2.xml');
        $this->submit('valid_Build.xml');
        $this->submit('valid_instrumentation_Build.xml');
    }

    /** Check that error messages are logged but submission succeeds
     *  when the environment variable is set but to false
     */
    public function testSubmissionValidationSilent(): void
    {
        config(['cdash.validate_submissions' => SubmissionValidationType::SILENT]);
        $this->submit('invalid_Configure.xml');
        $this->submit('invalid_syntax_Build.xml');
        $this->submit('valid_Configure1.xml');
        $this->submit('valid_Configure2.xml');
        $this->submit('valid_Build.xml');
        $this->submit('valid_instrumentation_Build.xml');
    }

    /** Check that error messages are logged but submission succeeds
     *  when the environment variable is set but to WARN
     */
    public function testSubmissionValidationWarn(): void
    {
        config(['cdash.validate_submissions' => SubmissionValidationType::WARN]);
        $this->submit('invalid_Configure.xml');
        $this->submit('invalid_syntax_Build.xml');
        $this->submit('valid_Configure1.xml');
        $this->submit('valid_Configure2.xml');
        $this->submit('valid_Build.xml');
        $this->submit('valid_instrumentation_Build.xml');
    }

    /** Check that the submission is dependent upon passing validation
     *  when the environment variable is set to REJECT
     */
    public function testSubmissionValidationReject(): void
    {
        config(['cdash.validate_submissions' => SubmissionValidationType::REJECT]);
        $this->submit('invalid_Configure.xml', 400);
        $this->submit('invalid_syntax_Build.xml', 400);
        $this->submit('valid_Configure1.xml');
        $this->submit('valid_Configure2.xml');
        $this->submit('valid_Build.xml');
        $this->submit('valid_instrumentation_Build.xml');
    }

    public function tearDown(): void
    {
        $this->project->delete();

        parent::tearDown();
    }
}
