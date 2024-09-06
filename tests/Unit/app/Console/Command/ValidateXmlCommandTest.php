<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Command;

/**
 * Tests the submission:validate artisan command.
 */
class ValidateXmlCommandTest extends TestCase
{
    /**
     * Formats the arguments to pass to the artisan command
     *
     * @return array<string,array<string>>
     */
    private function formatCommandParams()
    {
        $data_dir = base_path()."/tests/data/XmlValidation";
        $file_paths = [];
        foreach (func_get_args() as $file) {
            $file_paths[] = "{$data_dir}/{$file}";
        }
        return [
            'xml_file' => $file_paths,
        ];
    }

    /**
     * Handle common assertions for a 'success' validation outcome
     *
     * @param string $output, int $rc, string $msg
     * @return void
     */
    private function assertValidationSuccess(string $output, int $rc, string $msg): void
    {
        $this::assertMatchesRegularExpression("/All XML file checks passed./", $output, $msg);
        $this::assertEquals(Command::SUCCESS, $rc, "Passing validation should return a SUCCESS return code");
    }

    /**
     * Handle common assertions for a 'failure' validation outcome
     *
     * @param string $output, int $rc, string $msg, bool $skipped_files
     * @return void
     */
    private function assertValidationFailure(string $output, int $rc, string $msg, bool $skipped_files=false): void
    {
        $output_regex = $skipped_files ? "/Some XML file checks were skipped!/" : "/Some XML file checks did not pass!/";
        $this::assertMatchesRegularExpression($output_regex, $output, $msg);
        $this::assertEquals(Command::FAILURE, $rc, "Failed validation should return a FAILURE return code");
    }

    /**
     * Tests validating a single valid file
     *
     * @return void
     */
    public function testSingleValidXml(): void
    {
        $params = $this->formatCommandParams("valid_Build.xml");
        $rc = Artisan::call('submission:validate', $params);
        $output = trim(Artisan::output());
        $this->assertValidationSuccess($output, $rc, "A single valid Build XML file should pass.");
    }

    /**
     * Tests validating an invalid file path
     *
     * @return void
     */
    public function testInvalidFilePath(): void
    {
        $params = $this->formatCommandParams("no_such_file.xml");
        $rc = Artisan::call('submission:validate', $params);
        $output = trim(Artisan::output());
        $this->assertValidationFailure($output, $rc, "An invalid file path should fail.", $skipped=true);
    }

    /**
     * Tests validating a single file of unknown schema
     *
     * @return void
     */
    public function testUnknownSchema(): void
    {
        $params = $this->formatCommandParams("invalid_type.xml");
        $rc = Artisan::call('submission:validate', $params);
        $output = trim(Artisan::output());
        $this->assertValidationFailure($output, $rc, "An XML file that doesn't match any supported schemas should fail.");
    }

    /**
     * Tests validating a single file that does not adhere to its schema
     *
     * @return void
     */
    public function testSingleInvalidXml(): void
    {
        $params = $this->formatCommandParams("invalid_Configure.xml");
        $rc = Artisan::call('submission:validate', $params);
        $output = trim(Artisan::output());
        $this->assertValidationFailure($output, $rc, "An XML file that doesn't adhere to its corresponding schema should fail.");
    }

    /**
     * Tests validating a single file with invalid syntax
     *
     * @return void
     */
    public function testSingleInvalidSyntaxXml(): void
    {
        $params = $this->formatCommandParams("invalid_syntax_Build.xml");
        $rc = Artisan::call('submission:validate', $params);
        $output = trim(Artisan::output());
        $this->assertValidationFailure($output, $rc, "An XML file with syntax errors should fail.");
    }
    /**
     * Tests validating multiple valid files
     *
     * @return void
     */
    public function testMultipleValidXml(): void
    {
        $params = $this->formatCommandParams("valid_Build.xml", "valid_Test.xml");
        $rc = Artisan::call('submission:validate', $params);
        $output = trim(Artisan::output());
        $this->assertValidationSuccess($output, $rc, "Multiple valid XML files should pass.");
    }

    /**
     * Tests validating multiple files where some are invalid
     *
     * @return void
     */
    public function testMultipleFilesFailure(): void
    {
        $params = $this->formatCommandParams("valid_Build.xml", "invalid_Configure.xml", "valid_Test.xml");
        $rc = Artisan::call('submission:validate', $params);
        $output = trim(Artisan::output());
        $this->assertValidationFailure($output, $rc, "Validation of a set of input files should fail when one or more errors occur.");
    }
}
