<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

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
    private function formatCommandParams(string ...$files)
    {
        $data_dir = base_path('/tests/data/XmlValidation');
        $file_paths = [];
        foreach ($files as $file) {
            $file_paths[] = "{$data_dir}/{$file}";
        }
        return [
            'xml_file' => $file_paths,
        ];
    }

    /**
     * Handle common assertions for a 'success' validation outcome
     */
    private function assertValidationSuccess(string $output, int $rc, string $msg): void
    {
        $this::assertMatchesRegularExpression('/All XML file checks passed./', $output, $msg);
        $this::assertEquals(Command::SUCCESS, $rc, 'Passing validation should return a SUCCESS return code');
    }

    /**
     * Handle common assertions for a 'failure' validation outcome
     */
    private function assertValidationFailure(string $output, int $rc, string $msg, bool $skipped_files = false): void
    {
        $output_regex = $skipped_files ? '/Some XML file checks were skipped!/' : '/Some XML file checks did not pass!/';
        $this::assertMatchesRegularExpression($output_regex, $output, $msg);
        $this::assertEquals(Command::FAILURE, $rc, 'Failed validation should return a FAILURE return code');
    }

    /**
     * Tests validating a single valid file
     */
    public function testSingleValidXml(): void
    {
        $params = $this->formatCommandParams('valid_Build.xml');
        $rc = Artisan::call('submission:validate', $params);
        $output = trim(Artisan::output());
        $this->assertValidationSuccess($output, $rc, 'A single valid Build XML file should pass.');
    }

    /**
     * Tests validating an invalid file path
     */
    public function testInvalidFilePath(): void
    {
        $params = $this->formatCommandParams('no_such_file.xml');
        $rc = Artisan::call('submission:validate', $params);
        $output = trim(Artisan::output());
        $this->assertValidationFailure($output, $rc, 'An invalid file path should fail.', $skipped = true);
    }

    /**
     * Tests validating a single file of unknown schema
     */
    public function testUnknownSchema(): void
    {
        $params = $this->formatCommandParams('invalid_type.xml');
        $rc = Artisan::call('submission:validate', $params);
        $output = trim(Artisan::output());
        $this->assertValidationFailure($output, $rc, "An XML file that doesn't match any supported schemas should fail.");
    }

    /**
     * Tests validating a single file that does not adhere to its schema
     */
    public function testSingleInvalidXml(): void
    {
        $params = $this->formatCommandParams('invalid_Configure.xml');
        $rc = Artisan::call('submission:validate', $params);
        $output = trim(Artisan::output());
        $this->assertValidationFailure($output, $rc, "An XML file that doesn't adhere to its corresponding schema should fail.");
    }

    /**
     * Tests validating a single file with invalid syntax
     */
    public function testSingleInvalidSyntaxXml(): void
    {
        $params = $this->formatCommandParams('invalid_syntax_Build.xml');
        $rc = Artisan::call('submission:validate', $params);
        $output = trim(Artisan::output());
        $this->assertValidationFailure($output, $rc, 'An XML file with syntax errors should fail.');
    }

    /**
     * Tests validating a single file with a document type declaration
     */
    public function testSingleXmlWithDocumentType(): void
    {
        $params = $this->formatCommandParams('invalid_doctype_Configure.xml');
        $rc = Artisan::call('submission:validate', $params);
        $output = trim(Artisan::output());
        $this->assertValidationFailure($output, $rc, 'An XML file with a document type declaration should fail.');
        $this->assertStringContainsString('Files containing "<!DOCTYPE"', $output);
    }

    /**
     * Tests validating a single file with "<!DOCTYPE" in a comment
     */
    public function testSingleXmlWithDocumentTypeInComment(): void
    {
        $params = $this->formatCommandParams('invalid_doctype_in_comment_Configure.xml');
        $rc = Artisan::call('submission:validate', $params);
        $output = trim(Artisan::output());
        $this->assertValidationFailure($output, $rc, 'An XML file with "<!DOCTYPE" anywhere in it should fail.');
        $this->assertStringContainsString('Files containing "<!DOCTYPE"', $output);
    }

    /**
     * Tests validating a single file declared to be in an unsupported encoding
     */
    public function testSingleXmlWithUnsupportedEncoding(): void
    {
        $params = $this->formatCommandParams('invalid_encoding_Configure.xml');
        $rc = Artisan::call('submission:validate', $params);
        $output = trim(Artisan::output());
        $this->assertValidationFailure($output, $rc, 'An XML file declared in an unsupported encoding should fail.');
        $this->assertStringContainsString('Only files in UTF-8, US-ASCII or ISO-8859-1', $output);
    }

    /**
     * Tests validating valid files with comments, processing instructions, a byte order mark, or no XML declaration
     * before the root element
     */
    public function testMultipleValidXmlWithContentBeforeRoot(): void
    {
        $this->assertStringStartsWith(
            "\xEF\xBB\xBF",
            (string) file_get_contents(base_path('tests/data/XmlValidation/valid_no_declaration_Configure.xml')),
            'This file should start with a byte order mark.',
        );

        $params = $this->formatCommandParams('valid_before_root_Configure.xml', 'valid_no_declaration_Configure.xml');
        $rc = Artisan::call('submission:validate', $params);
        $output = trim(Artisan::output());
        $this->assertValidationSuccess($output, $rc, 'Valid XML files with this content before the root element should pass.');
    }

    /**
     * Tests validating multiple valid files
     */
    public function testMultipleValidXml(): void
    {
        $params = $this->formatCommandParams('valid_Configure1.xml', 'valid_Configure2.xml', 'valid_Build.xml', 'valid_Test.xml');
        $rc = Artisan::call('submission:validate', $params);
        $output = trim(Artisan::output());
        $this->assertValidationSuccess($output, $rc, 'Multiple valid XML files should pass.');
    }

    /**
     * Tests validating multiple files where some are invalid
     */
    public function testMultipleFilesFailure(): void
    {
        $params = $this->formatCommandParams('valid_Build.xml', 'invalid_Configure.xml', 'valid_Test.xml');
        $rc = Artisan::call('submission:validate', $params);
        $output = trim(Artisan::output());
        $this->assertValidationFailure($output, $rc, 'Validation of a set of input files should fail when one or more errors occur.');
    }
}
