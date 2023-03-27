<?php

namespace App\Models;

use CDash\Models\Build;

use Illuminate\Database\Eloquent\Model;

class TestOutput extends Model
{
    protected $table = 'testoutput';
    protected $fillable = ['testid', 'path', 'command', 'output', 'crc32'];

    public $timestamps = false;

    /**
     * Returns uncompressed test output.
     *
     * @return string
     */
    public static function DecompressOutput($output)
    {
        if (!config('cdash.use_compression')) {
            return $output;
        }
        if (config('database.default') == 'pgsql') {
            if (is_resource($output)) {
                $output = base64_decode(stream_get_contents($output));
            } else {
                $output = base64_decode($output);
            }
        }
        @$uncompressedrow = gzuncompress($output);
        if ($uncompressedrow !== false) {
            $output = $uncompressedrow;
        }
        return $output;
    }
}
