<?php

namespace App\Console\Commands;

use App\Utils\SubmissionUtils;
use Illuminate\Console\Command;
use DOMDocument;

class ValidateXml extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'submission:validate
                            { xml_file* : the XML file(s) to be validated }';

    /**
     * The console command description.
     */
    protected $description = 'Validate XML submission files';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // parse all input files from command line
        $xml_files_args = $this->argument('xml_file');
        $schemas_dir = base_path()."/app/Validators/Schemas";

        // process each of the input files
        $has_errors = false;
        foreach ($xml_files_args as $input_xml_file) {
            // determine the file type by peeking at its contents
            $xml_file_handle = fopen($input_xml_file, 'r');
            if ($xml_file_handle === false) {
                $this->error("ERROR: Could not open file: '{$input_xml_file}'");
                $has_errors = true;
                continue;
            }
            $xml_type = SubmissionUtils::get_xml_type($xml_file_handle)['xml_type'];
            fclose($xml_file_handle);

            // verify we identified a valid xml type
            if ($xml_type === '') {
                $this->error("ERROR: Could not determine submission"
                            ." file type for: '{$input_xml_file}'");
                $has_errors = true;
                continue;
            }

            // verify we can find a corresponding schema file
            $schema_file = "{$schemas_dir}/{$xml_type}.xsd";
            if (!file_exists($schema_file)) {
                $this->error("ERROR: Could not find schema file '{$schema_file}'"
                            ." corresonding to input: '{$input_xml_file}'");
                $has_errors = true;
                continue;
            }

            // let us control the failures so we can continue
            // parsing all the files instead of crashing midway
            libxml_use_internal_errors(true);

            // load the input file to be validated
            $xml = new DOMDocument();
            $xml->load($input_xml_file, LIBXML_PARSEHUGE);

            // run the validator and collect errors if there are any
            if (!$xml->schemaValidate($schema_file)) {
                $errors = libxml_get_errors();
                foreach ($errors as $error) {
                    if ($error->level > 2) {
                        $this->error("ERROR: {$error->message} in {$error->file},"
                            ." line: {$error->line}, column: {$error->column}");
                    }
                }
                libxml_clear_errors();
                $has_errors = true;
                continue;
            }
            $this->line("Validated file: {$input_xml_file}.");
        }

        // finally, report the results
        if ($has_errors) {
            $this->error("FAILED: Some XML file checks did not pass!");
            return Command::FAILURE;
        } else {
            $this->line("SUCCESS: All XML file checks passed.");
            return Command::SUCCESS;
        }
    }
}
