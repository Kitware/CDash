<?php

use League\Flysystem\UnableToReadFile;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

class ExtractTarTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testExtractTarArchiveTarWithInvalidFile(): void
    {
        $exception_thrown = false;
        try {
            extract_tar('this_file_does_not_exist');
        } catch (FileNotFoundException|UnableToReadFile) {
            $exception_thrown = true;
        }
        if (!$exception_thrown) {
            $this->fail('No Exception thrown when expected');
        }
    }
}
