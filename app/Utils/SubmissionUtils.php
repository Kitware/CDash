<?php

declare(strict_types=1);

namespace App\Utils;

class SubmissionUtils
{

    /**
     * Figure out what type of XML file this is
     * @return array<string,mixed>
     */
    public static function get_xml_type(mixed $filehandle): array
    {
        $file = '';
        $handler = null;
        // read file contents until we recognize its elements
        while ($file === '' && !feof($filehandle)) {
            $content = fread($filehandle, 8192);
            if ($content === false) {
                // if read failed, fallback onto default null values
                break;
            }
            if (str_contains($content, '<Update')) {
                // Should be first otherwise confused with Build
                $handler = \UpdateHandler::class;
                $file = 'Update';
            } elseif (str_contains($content, '<Build')) {
                $handler = \BuildHandler::class;
                $file = 'Build';
            } elseif (str_contains($content, '<Configure')) {
                $handler = \ConfigureHandler::class;
                $file = 'Configure';
            } elseif (str_contains($content, '<Testing')) {
                $handler = \TestingHandler::class;
                $file = 'Test';
            } elseif (str_contains($content, '<CoverageLog')) {
                // Should be before coverage
                $handler = \CoverageLogHandler::class;
                $file = 'CoverageLog';
            } elseif (str_contains($content, '<Coverage')) {
                $handler = \CoverageHandler::class;
                $file = 'Coverage';
            } elseif (str_contains($content, '<report')) {
                $handler = \CoverageJUnitHandler::class;
                $file = 'CoverageJUnit';
            } elseif (str_contains($content, '<Notes')) {
                $handler = \NoteHandler::class;
                $file = 'Notes';
            } elseif (str_contains($content, '<DynamicAnalysis')) {
                $handler = \DynamicAnalysisHandler::class;
                $file = 'DynamicAnalysis';
            } elseif (str_contains($content, '<Project')) {
                $handler = \ProjectHandler::class;
                $file = 'Project';
            } elseif (str_contains($content, '<Upload')) {
                $handler = \UploadHandler::class;
                $file = 'Upload';
            } elseif (str_contains($content, '<testsuite')) {
                $handler = \TestingJUnitHandler::class;
                $file = 'TestJUnit';
            } elseif (str_contains($content, '<Done')) {
                $handler = \DoneHandler::class;
                $file = 'Done';
            }
        }

        // restore the file descriptor to beginning of file
        rewind($filehandle);

        return [
            'file_handle' => $filehandle,
            'xml_handler' => $handler,
            'xml_type' => $file,
        ];
    }
}
