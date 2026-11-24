<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToReadFile;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

class XmlValidationService extends AbstractService
{
    /**
     * Encodings in which "<!DOCTYPE" can only be written as those nine ASCII bytes.  Anything added here has to keep
     * that true, which rules out encodings like UTF-7 and UTF-16.
     */
    private const SUPPORTED_ENCODINGS = ['utf-8', 'utf8', 'us-ascii', 'ascii', 'iso-8859-1'];

    private const XML_WHITESPACE = [' ', "\t", "\r", "\n"];

    /**
     * The longest XML declaration that's accepted.  Real ones are under 100 bytes.
     */
    private const MAX_DECLARATION_LENGTH = 1024;

    /**
     * How much of a file is read at a time when looking for a document type declaration.
     */
    private const CHUNK_SIZE = 1048576;

    private const DOCUMENT_TYPE_START = '<!DOCTYPE';

    /**
     * Validate an XML file against a schema.
     *
     * @param string $path a local file, or a file on the default storage disk
     *
     * @return array<string> the validation errors, if any
     *
     * @throws FileNotFoundException
     * @throws UnableToReadFile
     */
    public static function validate(string $path, string $schema_path): array
    {
        // let us control the failures so we can continue
        // parsing files instead of crashing midway
        libxml_use_internal_errors(true);

        $copy = null;
        if (is_file($path)) {
            $local_path = $path;
        } elseif (!Storage::fileExists($path)) {
            throw new FileNotFoundException($path);
        } elseif (config('filesystems.default') === 'local') {
            $local_path = Storage::path($path);
        } else {
            $copy = self::temporaryCopy($path);
            $local_path = stream_get_meta_data($copy)['uri'] ?? '';
        }

        $file = $copy ?? @fopen($local_path, 'rb');
        if ($file === false) {
            throw UnableToReadFile::fromLocation($path);
        }

        try {
            // libxml can't safely parse every document type declaration, the only place entities can be declared,
            // and neither LIBXML_PARSEHUGE parsing nor schema validation reliably limits entity expansion.  So files
            // that might have one aren't loaded.
            $errors = self::documentTypeErrors($file, $path);
            if ($errors === []) {
                libxml_clear_errors();
                $xml = new DOMDocument();
                // DOMDocument::load() percent-decodes paths, so this relies on paths not containing "%" to load the
                // file that was checked.
                $xml->load($local_path, LIBXML_PARSEHUGE);

                // run the validator and collect errors if there are any.
                if (!$xml->schemaValidate($schema_path)) {
                    $errors = self::takeLibxmlErrors($path);
                }
            }
        } finally {
            fclose($file);
        }

        return $errors;
    }

    /**
     * Errors for a file that might have a document type declaration, found without parsing it.  In the supported
     * encodings, a document type declaration can only be written as the bytes "<!DOCTYPE", so a file in one of them
     * without those bytes has none.
     *
     * @param resource $file
     *
     * @return array<string>
     *
     * @throws UnableToReadFile
     */
    private static function documentTypeErrors($file, string $path): array
    {
        rewind($file);
        $chunk = self::readChunk($file, $path);

        // A byte order mark for another encoding, or the first bytes of a file in EBCDIC, aren't "<" or whitespace.
        $without_bom = str_starts_with($chunk, "\xEF\xBB\xBF") ? substr($chunk, 3) : $chunk;
        if ($without_bom !== '' && !in_array($without_bom[0], ['<', ...self::XML_WHITESPACE], true)) {
            return ["WARNING: Files have to be in UTF-8, US-ASCII or ISO-8859-1, and start with \"<\" or whitespace, to be validated: {$path}"];
        }

        // Only an XML declaration at the very start of the file can change the encoding.  Real ones are short, so
        // one that doesn't end within the first 1 KiB isn't supported.
        if (str_starts_with($without_bom, '<?xml') && in_array(substr($without_bom, 5, 1), self::XML_WHITESPACE, true)) {
            $end = strpos(substr($without_bom, 0, self::MAX_DECLARATION_LENGTH), '?>');
            if ($end === false) {
                return ["WARNING: Unsupported XML declaration: {$path}"];
            }
            $declaration = substr($without_bom, 0, $end);
            if (preg_match('/encoding\s*=\s*(["\'])(.*?)\1/', $declaration, $matches) === 1) {
                if (!in_array(strtolower($matches[2]), self::SUPPORTED_ENCODINGS, true)) {
                    return ["WARNING: Only files in UTF-8, US-ASCII or ISO-8859-1 can be validated: {$path}"];
                }
            } elseif (str_contains(strtolower($declaration), 'encoding')) {
                // An encoding this can't read is rejected, rather than risk libxml reading it differently.
                return ["WARNING: Unsupported XML declaration: {$path}"];
            }
        }

        // Files in UTF-16 or UTF-32 contain null bytes, which no XML file in a supported encoding can contain.
        // The end of each chunk is kept, in case "<!DOCTYPE" spans two chunks.
        $previous_end = '';
        while ($chunk !== '') {
            if (str_contains($chunk, "\0")) {
                return ["WARNING: Files containing null bytes can't be validated: {$path}"];
            }
            $window = $previous_end . $chunk;
            if (str_contains($window, self::DOCUMENT_TYPE_START)) {
                return ["WARNING: Files containing \"<!DOCTYPE\", even in a comment or CDATA section, can't be validated: {$path}"];
            }
            $previous_end = substr($window, 1 - strlen(self::DOCUMENT_TYPE_START));
            $chunk = self::readChunk($file, $path);
        }

        return [];
    }

    /**
     * Copy a file from storage to a temporary local file, because DOMDocument::load() takes a path.  PHP deletes the
     * copy when it's closed, or when the script ends.
     *
     * @return resource
     *
     * @throws UnableToReadFile
     */
    private static function temporaryCopy(string $path)
    {
        $source = Storage::readStream($path);
        if ($source === null) {
            throw UnableToReadFile::fromLocation($path);
        }

        try {
            $copy = @tmpfile();
            if ($copy === false || @stream_copy_to_stream($source, $copy) === false) {
                throw UnableToReadFile::fromLocation($path, 'Unable to write a temporary copy to ' . sys_get_temp_dir());
            }
        } finally {
            fclose($source);
        }

        return $copy;
    }

    /**
     * @param resource $file
     *
     * @throws UnableToReadFile
     */
    private static function readChunk($file, string $path): string
    {
        $chunk = @fread($file, self::CHUNK_SIZE);
        if ($chunk === false) {
            throw UnableToReadFile::fromLocation($path);
        }

        return $chunk;
    }

    /**
     * Collect and clear the errors libxml has reported for a file.
     *
     * @return array<string>
     */
    private static function takeLibxmlErrors(string $path): array
    {
        $errors = [];
        foreach (libxml_get_errors() as $error) {
            if ($error->level === LIBXML_ERR_ERROR || $error->level === LIBXML_ERR_FATAL) {
                $errors[] = "WARNING: {$error->message} in {$path}, line: {$error->line}, column: {$error->column}";
            }
        }
        libxml_clear_errors();

        return $errors;
    }
}
