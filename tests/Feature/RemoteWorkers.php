<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RemoteWorkers extends TestCase
{
    protected function setUp() : void
    {
        parent::setUp();

        URL::forceRootUrl('http://localhost');
        Config::set('cdash.remote_workers', true);
        Config::set('cdash.backup_timeframe', 0);
    }

    public function testRemoteWorkerAPIAccess() : void
    {
        Storage::put('inbox/delete_me', 'please delete me');
        $_SERVER["REQUEST_METHOD"] = 'DELETE';
        $_REQUEST['filename'] = encrypt('inbox/delete_me');

        $response = $this
            ->delete('/api/v1/deleteSubmissionFile.php');
        $response->assertOk();
        self::assertFalse(Storage::exists('inbox/delete_me'));
    }

    public function testRemoteWorkerAPIAccessWithInvalidKey() : void
    {
        Storage::put('inbox/delete_me', 'please delete me');
        $_SERVER["REQUEST_METHOD"] = 'DELETE';
        // Not encrypted, will fail.
        $_REQUEST['filename'] = 'inbox/delete_me';

        $response = $this
            ->delete('/api/v1/deleteSubmissionFile.php');
        $response->assertConflict();
        self::assertTrue(Storage::exists('inbox/delete_me'));
        Storage::delete('inbox/delete_me');
    }

    public function testStoreUploadedFile() : void
    {
        Storage::fake('upload');
        $file = UploadedFile::fake()->image('my_upload.jpg');

        // Unencrypted case.
        $response = $this->post('/api/v1/store_upload', [
            'sha1sum' => 'asdf',
            'file' => $file,
        ]);
        $response->assertConflict();
        $response->assertSeeText('This feature is disabled');

        // Encrypted but sha mismatch.
        $response = $this->post('/api/v1/store_upload', [
            'sha1sum' => encrypt('asdf'),
            'file' => $file,
        ]);
        $response->assertBadRequest();
        $response->assertSeeText('Uploaded file does not match expected sha1sum');
    }
}
