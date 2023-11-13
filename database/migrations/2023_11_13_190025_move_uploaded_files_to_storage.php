<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @throws UnexpectedValueException
     * @return void
     */
    public function up()
    {
        if (!file_exists(base_path('app/cdash/public/upload'))) {
            // Return early if it looks like this migration has
            // already been performed.
            echo "Skipping this migration because public/upload does not exist\n";
            return;
        }

        Storage::makeDirectory('upload');

        // Recurse through the old upload directory, moving files (not symlinks)
        // into the new location under storage/app/upload.
        $dir = new RecursiveDirectoryIterator(base_path('app/cdash/public/upload'));
        $itr = new RecursiveIteratorIterator($dir);
        foreach ($itr as $info) {
            if (!$itr->isLink() && $itr->isFile()) {
                rename($info->getRealPath(), Storage::path("upload/{$info->getBaseName()}"));
            }
        }

        // Remove the old upload directory.
        DeleteDirectory(base_path('app/cdash/public/upload'));
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Non-reversible.
    }
};
