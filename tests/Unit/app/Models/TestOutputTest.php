<?php

namespace Tests\Unit\app\Models;

use App\Models\TestOutput;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Tests the TestOutput model.
 */
class TestOutputTest extends TestCase
{
    protected static string $expected = 'this is my test output';

    protected function validateDecompressTestOutputFromStream(string $value): void
    {
        $fp = tmpfile();
        if ($fp === false) {
            $this::fail('Failed to create temporary file');
        }
        fwrite($fp, $value);
        fseek($fp, 0);
        $this::assertEquals(TestOutput::DecompressOutput($fp), TestOutputTest::$expected);
        fclose($fp);
    }

    /**
     * Data provider for testDecompressOutput
     *
     * @return array<string,array<string>>
     */
    public static function compressedOutputProvider()
    {
        return [
            'compressed' => [(string) gzcompress(TestOutputTest::$expected)],
            'compressed and encoded' => [base64_encode((string) gzcompress(TestOutputTest::$expected))],
        ];
    }

    /**
     * Test the various types of input that TestOutput::DecompressOutput()
     * may receive.
     */
    #[DataProvider('compressedOutputProvider')]
    public function testDecompressOutput(string $value): void
    {
        $this::assertEquals(TestOutput::DecompressOutput($value), TestOutputTest::$expected);
        $this->validateDecompressTestOutputFromStream($value);
    }

    /**
     * Data provider for testDecompressOutputDatabaseSpecificBehavior
     *
     * @return array<string,array<string>>
     */
    public static function databaseSpecificCompressedOutputProvider()
    {
        return [
            'MySQL' => ['mysql', TestOutputTest::$expected],
            'Postgres' => ['pgsql', base64_encode(TestOutputTest::$expected)],
        ];
    }

    /**
     * Test MySQL and Postgres specific behavior of TestOutput::DecompressOutput()
     */
    #[DataProvider('databaseSpecificCompressedOutputProvider')]
    public function testDecompressOutputDatabaseSpecificBehavior(string $db_type, string $value): void
    {
        config(['database.default' => $db_type]);
        $this::assertEquals(TestOutput::DecompressOutput($value), TestOutputTest::$expected);
        $this->validateDecompressTestOutputFromStream($value);
    }
}
