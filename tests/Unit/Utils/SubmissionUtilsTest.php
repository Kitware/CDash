<?php

namespace Tests\Unit\Utils;

use App\Utils\SubmissionUtils;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SubmissionUtilsTest extends TestCase
{
    private const MD5 = 'd41d8cd98f00b204e9800998ecf8427e';

    public function testXmlFilename(): void
    {
        $filename = SubmissionUtils::xmlFilename('MyProject', 'tokenhash', self::MD5);

        $this->assertMatchesRegularExpression('/^MyProject_-_tokenhash_-_[0-9a-f-]{36}_-_' . self::MD5 . '\.xml$/', $filename);
        $this->assertSame([
            'projectname' => 'MyProject',
            'token_hash' => 'tokenhash',
            'md5' => self::MD5,
        ], SubmissionUtils::parseFilename($filename));
        $this->assertFalse(SubmissionUtils::isBuildMetadataFilename($filename));
    }

    public function testXmlFilenameWithoutTokenOrMd5(): void
    {
        $filename = SubmissionUtils::xmlFilename('MyProject', '', '');

        $this->assertMatchesRegularExpression('/^MyProject_-__-_[0-9a-f-]{36}_-_\.xml$/', $filename);
        $this->assertSame([
            'projectname' => 'MyProject',
            'token_hash' => '',
            'md5' => '',
        ], SubmissionUtils::parseFilename($filename));
        $this->assertFalse(SubmissionUtils::isBuildMetadataFilename($filename));
    }

    public function testDataFilename(): void
    {
        $filename = SubmissionUtils::dataFilename('MyProject', 'tokenhash', 'GcovTar', 42, self::MD5, 'tar');

        $this->assertSame('MyProject_-_tokenhash_-_GcovTar_-_42_-_' . self::MD5 . '_-_.tar', $filename);
        $this->assertSame([
            'projectname' => 'MyProject',
            'token_hash' => 'tokenhash',
            'md5' => self::MD5,
        ], SubmissionUtils::parseFilename($filename));
        $this->assertFalse(SubmissionUtils::isBuildMetadataFilename($filename));
    }

    public function testDataFilenameWithSeparatorInExtension(): void
    {
        $filename = SubmissionUtils::dataFilename('MyProject', 'tokenhash', 'GcovTar', 42, self::MD5, 'tar_-_gz');

        $this->assertSame('MyProject_-_tokenhash_-_GcovTar_-_42_-_' . self::MD5 . '_-_.tar_-_gz', $filename);
        $this->assertSame([
            'projectname' => 'MyProject',
            'token_hash' => 'tokenhash',
            'md5' => self::MD5,
        ], SubmissionUtils::parseFilename($filename));
        $this->assertFalse(SubmissionUtils::isBuildMetadataFilename($filename));
    }

    public function testBuildMetadataFilename(): void
    {
        $filename = SubmissionUtils::buildMetadataFilename('MyProject', 'tokenhash', '1b4e28ba-2fa1-11d2-883f-0016d3cca427');

        $this->assertSame('MyProject_-_tokenhash_-_build-metadata_-_1b4e28ba-2fa1-11d2-883f-0016d3cca427_-__-_.json', $filename);
        $this->assertSame([
            'projectname' => 'MyProject',
            'token_hash' => 'tokenhash',
            'md5' => '',
        ], SubmissionUtils::parseFilename($filename));
        $this->assertTrue(SubmissionUtils::isBuildMetadataFilename($filename));
    }

    /**
     * @return array<string,array{string}>
     */
    public static function malformedFilenames(): array
    {
        return [
            'one segment' => ['MyProject.xml'],
            'two segments' => ['MyProject_-_tokenhash.xml'],
            'three segments' => ['MyProject_-_tokenhash_-_uuid.xml'],
            'five segments' => ['MyProject_-_tokenhash_-_GcovTar_-_42_-_' . self::MD5 . '.tar'],
        ];
    }

    #[DataProvider('malformedFilenames')]
    public function testParseFilenameRejectsMalformedFilename(string $filename): void
    {
        $this->assertNull(SubmissionUtils::parseFilename($filename));
    }
}
