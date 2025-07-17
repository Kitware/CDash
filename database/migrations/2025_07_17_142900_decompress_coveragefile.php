<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // We start by deleting any coverage files which are no longer referenced.  In theory, nothing
        // should be deleted, but there are often missed rows in practice.  Since this is such a slow
        // operation, it's worthwhile to do this check to make sure we're only decompressing data we
        // actually need.
        DB::delete('
            DELETE FROM coveragefile
            WHERE NOT EXISTS (
                SELECT 1 FROM coverage
                WHERE fileid = coveragefile.id
            )
        ');

        DB::statement('ALTER TABLE coveragefile ADD COLUMN IF NOT EXISTS new_file text');

        $max_id = 0;
        while (true) {
            $batch_size = 10;
            $batch = DB::select("
                SELECT
                    id,
                    file
                FROM coveragefile
                WHERE id > ? AND file IS NOT NULL -- file can apparently be NULL.
                ORDER BY id ASC
                LIMIT $batch_size
            ", [$max_id]);

            // No rows to migrate.  Probably a new database...
            if (count($batch) === 0) {
                break;
            }

            $values_prepare_string = '';
            $values = [];
            foreach ($batch as $row) {
                // Start the next batch after the last ID in this set.  Ordered by id, so guaranteed to be the largest ID.
                $max_id = (int) $row->id;
                $values_prepare_string .= '(?::integer,?),';
                $values[] = (int) $row->id;

                $file = stream_get_contents($row->file);
                if ($file === false) {
                    throw new Exception('Error reading stream from database.');
                }
                $decompressed = $this->decompress($file);
                if (mb_detect_encoding($decompressed, 'UTF-8', true) === false) {
                    $decompressed = mb_convert_encoding($decompressed, 'UTF-8', 'UTF-8');
                    if ($decompressed === false) {
                        echo "Unable to convert coveragefile #{$row->id} to UTF-8\n";
                        $decompressed = '';
                    }
                }
                $values[] = $decompressed;
            }

            // Remove the trailing comma...
            $values_prepare_string = rtrim($values_prepare_string, ',');

            DB::update("
                UPDATE coveragefile
                SET new_file = temp.file
                FROM (VALUES $values_prepare_string) AS temp(id, file)
                WHERE coveragefile.id = temp.id
            ", $values);

            if (count($batch) < $batch_size) {
                break;
            }
        }

        DB::statement('ALTER TABLE coveragefile DROP COLUMN file');
        DB::statement('ALTER TABLE coveragefile RENAME COLUMN new_file TO file');
    }

    public function down(): void
    {
    }

    private function decompress(string $file): string
    {
        // This file could be:
        // - compressed
        // - compressed and base64 encoded
        // - base64 encoded but not compressed
        // - neither base64 encoded nor compressed

        // Check if file is compressed.
        // Note that compression is always applied before base64 encoding.
        @$decompressed = gzuncompress($file);
        if ($decompressed !== false) {
            return $decompressed;
        }

        // Check if file is compressed and base64 encoded.
        $decoded = base64_decode($file, true);
        if ($decoded !== false) {
            @$decompressed = gzuncompress($decoded);
            if ($decompressed !== false) {
                return $decompressed;
            }
        }

        // File must not be compressed.
        // If base64_decode failed then we can safely assume this file
        // wasn't encoded.
        if ($decoded === false) {
            return $file;
        }

        // Otherwise it's difficult to tell if the file was actually base64
        // encoded or not.  Take a guess and assume it was encoded.
        return $decoded;
    }
};
