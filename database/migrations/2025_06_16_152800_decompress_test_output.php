<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE testoutput ADD COLUMN IF NOT EXISTS output_new text');

        $max_id = 0;
        while (true) {
            $batch_size = 5000;
            $batch = DB::select("
                SELECT
                    id,
                    output
                FROM testoutput
                WHERE id > ?
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

                $output = stream_get_contents($row->output);
                if ($output === false) {
                    throw new Exception('Error reading stream from database.');
                }
                $values[] = $this->decompressOutput($output);
            }

            // Remove the trailing comma...
            $values_prepare_string = rtrim($values_prepare_string, ',');

            DB::update("
                UPDATE testoutput
                SET output_new = temp.output
                FROM (VALUES $values_prepare_string) AS temp(id, output)
                WHERE testoutput.id = temp.id
            ", $values);

            if (count($batch) < $batch_size) {
                break;
            }
        }

        DB::statement('ALTER TABLE testoutput DROP COLUMN output');
        DB::statement('ALTER TABLE testoutput RENAME COLUMN output_new TO output');
        DB::statement('ALTER TABLE testoutput ALTER COLUMN output SET NOT NULL');
    }

    public function down(): void
    {
        // This migration is irreversible
    }

    private function decompressOutput(string $output): string
    {
        // This output could be:
        // - compressed
        // - compressed and base64 encoded
        // - base64 encoded but not compressed
        // - neither base64 encoded nor compressed

        // Check if output is compressed.
        // Note that compression is always applied before base64 encoding.
        @$decompressed = gzuncompress($output);
        if ($decompressed !== false) {
            return $decompressed;
        }

        // Check if output is compressed and base64 encoded.
        $decoded = base64_decode($output, true);
        if ($decoded !== false) {
            @$decompressed = gzuncompress($decoded);
            if ($decompressed !== false) {
                return $decompressed;
            }
        }

        // Output must not be compressed.
        // If base64_decode failed then we can safely assume this output
        // wasn't encoded.
        if ($decoded === false) {
            return $output;
        }

        // Otherwise it's difficult to tell if the output was actually base64
        // encoded or not.  Take a guess and assume it was encoded.
        return $decoded;
    }
};
