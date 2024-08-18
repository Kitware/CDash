<?php

namespace App\Console\Commands;

use App\Utils\SubmissionUtils;
use Illuminate\Console\Command;
use App\Exceptions\CDashXMLValidationException;

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

        // process each of the input files
        $has_errors = false;
        $has_skipped = false;
        foreach ($xml_files_args as $input_xml_file) {
            // determine the file type by peeking at its contents
            if (!file_exists($input_xml_file)) {
                $this->error("ERROR: Input file does not exist: '{$input_xml_file}'");
                $has_skipped = true;
                continue;
            }
            $xml_file_handle = fopen($input_xml_file, 'r');
            if ($xml_file_handle === false) {
                $this->error("ERROR: Could not open file: '{$input_xml_file}'");
                $has_skipped = true;
                continue;
            }

            try {
                $xml_schema = SubmissionUtils::get_xml_type($xml_file_handle, $input_xml_file)['xml_schema'];
            } catch (CDashXMLValidationException $e) {
                foreach ($e->getDecodedMessage() as $error) {
                    $this->error($error);
                }
                $has_errors = true;
                continue;
            } finally {
                fclose($xml_file_handle);
            }

            if (!isset($xml_schema)) {
                $this->warn("WARNING: Skipped input file '{$input_xml_file}' as validation"
                               ." of this file format is currently not supported.");
                $has_skipped = true;
                continue;
            }

            // run the validator and collect errors if there are any
            try {
                SubmissionUtils::validate_xml($input_xml_file, $xml_schema);
            } catch (CDashXMLValidationException $e) {
                foreach ($e->getDecodedMessage() as $error) {
                    $this->error($error);
                }
                $has_errors = true;
                continue;
            }

            $this->line("Validated file: {$input_xml_file}.");
        }

        // finally, report the results
        if ($has_errors) {
            $this->error("FAILED: Some XML file checks did not pass!");
            return Command::FAILURE;
        } elseif ($has_skipped) {
            $this->error("FAILED: Some XML file checks were skipped!");
            return Command::FAILURE;
        } else {
            $this->line("SUCCESS: All XML file checks passed.");
            return Command::SUCCESS;
        }
    }
}
