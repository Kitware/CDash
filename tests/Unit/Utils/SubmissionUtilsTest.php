<?php

namespace Tests\Unit\Utils;

use App\Utils\SubmissionUtils;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToWriteFile;
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

    /**
     * @return array<string,array{string}>
     */
    public static function wellFormedMd5s(): array
    {
        return [
            'lowercase' => [self::MD5],
            'uppercase' => [strtoupper(self::MD5)],
        ];
    }

    #[DataProvider('wellFormedMd5s')]
    public function testIsValidMD5AcceptsWellFormedHash(string $md5): void
    {
        $this->assertTrue(SubmissionUtils::isValidMD5($md5));
    }

    /**
     * @return array<string,array{string}>
     */
    public static function malformedMd5s(): array
    {
        return [
            'empty' => [''],
            'too short' => [substr(self::MD5, 1)],
            'too long' => [self::MD5 . 'f'],
            'not hexadecimal' => ['z' . substr(self::MD5, 1)],
            'path' => ['../' . substr(self::MD5, 3)],
            'trailing newline' => [self::MD5 . "\n"],
        ];
    }

    #[DataProvider('malformedMd5s')]
    public function testIsValidMD5RejectsMalformedHash(string $md5): void
    {
        $this->assertFalse(SubmissionUtils::isValidMD5($md5));
    }

    public function testIsFileMD5CorrectAcceptsMatchingHash(): void
    {
        $stream = $this->makeStream('contents');

        $this->assertTrue(SubmissionUtils::isFileMD5Correct($stream, md5('contents')));
        $this->assertSame('contents', stream_get_contents($stream));
    }

    public function testIsFileMD5CorrectAcceptsUppercaseMatchingHash(): void
    {
        $stream = $this->makeStream('contents');

        $this->assertTrue(SubmissionUtils::isFileMD5Correct($stream, strtoupper(md5('contents'))));
    }

    public function testIsFileMD5CorrectRejectsMismatchedHash(): void
    {
        $stream = $this->makeStream('contents');

        $this->assertFalse(SubmissionUtils::isFileMD5Correct($stream, self::MD5));
    }

    public function testStoreInInbox(): void
    {
        Storage::fake();

        $this->assertTrue(SubmissionUtils::storeInInbox('file.xml', $this->makeStream('contents')));

        $this->assertSame(['inbox/file.xml'], Storage::allFiles());
        $this->assertSame('contents', Storage::get('inbox/file.xml'));
    }

    public function testStoreInInboxReturnsFalseWhenWriteFails(): void
    {
        Exceptions::fake();
        Storage::shouldReceive('put')
            ->once()
            ->with('inbox/file.xml', 'contents')
            ->andThrow(UnableToWriteFile::atLocation('inbox/file.xml'));

        $this->assertFalse(SubmissionUtils::storeInInbox('file.xml', 'contents'));

        Exceptions::assertReported(UnableToWriteFile::class);
    }

    public function testMarkDeferredSubmissions(): void
    {
        Storage::fake();

        SubmissionUtils::markDeferredSubmissions();

        $this->assertSame(['DB_WAS_DOWN'], Storage::allFiles());
    }

    public function testQueueDeferredSubmissions(): void
    {
        Storage::fake();
        Storage::put('DB_WAS_DOWN', '');
        $artisan = Artisan::spy();

        SubmissionUtils::queueDeferredSubmissions();

        $this->assertSame([], Storage::allFiles());
        $artisan->shouldHaveReceived('call')->once()->with('submission:queue');
    }

    public function testQueueDeferredSubmissionsDoesNothingWithoutMarker(): void
    {
        Storage::fake();
        $artisan = Artisan::spy();

        SubmissionUtils::queueDeferredSubmissions();

        $this->assertSame([], Storage::allFiles());
        $artisan->shouldNotHaveReceived('call');
    }

    /**
     * @return resource
     */
    private function makeStream(string $contents)
    {
        $stream = fopen('php://memory', 'r+');
        $this->assertIsResource($stream);
        fwrite($stream, $contents);
        rewind($stream);
        return $stream;
    }
}
