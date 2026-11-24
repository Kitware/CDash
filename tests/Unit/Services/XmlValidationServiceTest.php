<?php

namespace Tests\Unit\Services;

use App\Services\XmlValidationService;
use League\Flysystem\UnableToReadFile;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClassConstant;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Tests\TestCase;

class XmlValidationServiceTest extends TestCase
{
    private const DATA_DIR = 'tests/data/XmlValidation/';
    private const SCHEMA = 'app/Validators/Schemas/Configure.xsd';

    /**
     * @return array<string, array{string, string}>
     */
    public static function unsupportedFiles(): array
    {
        return [
            'UTF-16 with a byte order mark' => ['invalid_utf16_Configure.xml', 'Files have to be in UTF-8, US-ASCII or ISO-8859-1'],
            'UTF-16 without a byte order mark' => ['invalid_null_byte_Configure.xml', 'Files containing null bytes'],
            'EBCDIC' => ['invalid_ebcdic_Configure.xml', 'Files have to be in UTF-8, US-ASCII or ISO-8859-1'],
            'byte order mark and an unsupported encoding' => ['invalid_bom_encoding_Configure.xml', 'Only files in UTF-8, US-ASCII or ISO-8859-1'],
            'XML declaration longer than 1 KiB' => ['invalid_long_declaration_Configure.xml', 'Unsupported XML declaration'],
            'unquoted encoding' => ['invalid_unquoted_encoding_Configure.xml', 'Unsupported XML declaration'],
            '"<!DOCTYPE" across two reads' => ['invalid_doctype_at_chunk_boundary_Configure.xml', 'Files containing "<!DOCTYPE"'],
        ];
    }

    /**
     * @throws FileNotFoundException
     * @throws UnableToReadFile
     */
    #[DataProvider('unsupportedFiles')]
    public function testRejectsUnsupportedFiles(string $file_name, string $expected_error): void
    {
        $errors = XmlValidationService::validate(base_path(self::DATA_DIR . $file_name), base_path(self::SCHEMA));

        $this->assertCount(1, $errors);
        $this->assertStringContainsString($expected_error, $errors[0]);
    }

    /**
     * Some fixtures only test what they're meant to while their exact bytes are kept.
     */
    public function testFixturesHaveTheBytesTheirTestsNeed(): void
    {
        $ebcdic = (string) file_get_contents(base_path(self::DATA_DIR . 'invalid_ebcdic_Configure.xml'));
        $this->assertStringStartsWith("\x4C\x6F\xA7\x94", $ebcdic, 'This file should start with "<?xm" in EBCDIC.');

        $chunk_size = (new ReflectionClassConstant(XmlValidationService::class, 'CHUNK_SIZE'))->getValue();
        $this->assertIsInt($chunk_size);
        $document_type_start = strpos(
            (string) file_get_contents(base_path(self::DATA_DIR . 'invalid_doctype_at_chunk_boundary_Configure.xml')),
            '<!DOCTYPE',
        );
        $this->assertSame(
            $chunk_size - strlen('<!DOCTYPE') + 1,
            $document_type_start,
            'Only the last byte of "<!DOCTYPE" should be in the second read.',
        );
    }
}
