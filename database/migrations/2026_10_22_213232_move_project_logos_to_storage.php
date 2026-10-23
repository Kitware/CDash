<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration {
    /**
     * Project logos used to be stored in the image table alongside test images.  Move them to
     * logos/<project id>/<sha1 of contents>.<extension> on the default storage disk instead.
     *
     * The image rows are left in place because the image table deduplicates by checksum, so a logo
     * may also be used by a test.  `php artisan db:clean` removes the rows which are no longer used.
     *
     * Files written here are not removed if the migration fails, but the paths are derived from the
     * file contents, so running the migration again overwrites them.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE project ADD COLUMN logo_path text');

        // Logos are only migrated if they are one of the types accepted by the upload form.
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/bmp' => 'bmp',
            'image/x-ms-bmp' => 'bmp',
            'image/webp' => 'webp',
        ];
        $finfo = new finfo(FILEINFO_MIME_TYPE);

        $projects = DB::select('SELECT id, imageid FROM project WHERE imageid IS NOT NULL ORDER BY id');
        foreach ($projects as $project) {
            $images = DB::select('SELECT img FROM image WHERE id = ?', [(int) $project->imageid]);
            if ($images === []) {
                continue;
            }

            $contents = stream_get_contents($images[0]->img);
            if ($contents === false) {
                throw new Exception('Error reading stream from database.');
            }

            // The image's extension column contains a mix of MIME types and file extensions, so detect
            // the type from the contents instead.
            $mime_type = $finfo->buffer($contents);
            if ($mime_type === false || !array_key_exists($mime_type, $extensions)) {
                echo "Unable to migrate logo for project #{$project->id} with unsupported type '{$mime_type}'\n";
                continue;
            }

            $logo_path = "logos/{$project->id}/" . sha1($contents) . ".{$extensions[$mime_type]}";
            if (!Storage::put($logo_path, $contents)) {
                throw new Exception("Unable to store logo for project #{$project->id}.");
            }
            DB::update('UPDATE project SET logo_path = ? WHERE id = ?', [$logo_path, (int) $project->id]);
        }

        DB::statement('ALTER TABLE project DROP COLUMN imageid');
    }

    public function down(): void
    {
    }
};
