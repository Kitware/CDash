<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DOMDocument;

class ValidateXml extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'submission:validate
                            { xml_file : the XML file to be validated }
                            { xsd_file : the schema file to validate against }';

    /**
     * The console command description.
     *
     * @var string|null
     */
    protected $description = 'Validate XML submission files';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $input_xml_file = $this->argument('xml_file');
        $schema_file = $this->argument('xsd_file');

        // load the input files to be validated
        $xml = new DOMDocument();
        $xml->load($input_xml_file);

        // run the validator. let it throw errors if there
        // are any, since it prints nice error messages.
        // FIXME: this might crash if the file is too big...
        //  change this to a streaming parser as opposed to
        //  loading the whole file into memory!
        $xml->schemaValidate($schema_file);

        // if the validation succeeded, return 0
        return Command::SUCCESS;
    }
}
