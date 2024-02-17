<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\MigrationTest;

class UploadStorageMigration extends MigrationTest
{
    protected function tearDown() : void
    {
        parent::tearDown();
    }

    /**
     * Test case for the migration that moves our uploaded files from 'public/'
     * to 'storage/'.
     *
     * @return void
     */
    public function testUploadStorageMigration()
    {
        // Rollback the relevant migration.
        Artisan::call('migrate:rollback', [
            '--path' => 'database/migrations/2023_11_13_190025_move_uploaded_files_to_storage.php',
            '--force' => true]);

        // Stage some dummy data.
        $old_upload_dir = base_path('app/cdash/public/upload/abc');
        if (is_dir($old_upload_dir)) {
            DeleteDirectory($old_upload_dir);
        }
        mkdir($old_upload_dir, 0o755, true);
        $old_filename = "{$old_upload_dir}/abc";
        $symlink_filename = "{$old_upload_dir}/foo.txt";
        file_put_contents($old_filename, 'I am a real file');
        symlink($old_filename, $symlink_filename);

        // Run the migration under test.
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2023_11_13_190025_move_uploaded_files_to_storage.php',
            '--force' => true]);

        // Verify that the migration worked as intended.
        $this::assertFileExists(Storage::path('upload/abc'));
        $this::assertDirectoryDoesNotExist(base_path('app/cdash/public/upload'));

        // Clean up.
        Storage::delete('upload/abc');
    }
}
