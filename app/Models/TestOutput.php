<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestOutput extends Model
{
    protected $table = 'testoutput';
    protected $fillable = ['testid', 'path', 'command', 'output', 'crc32'];

    public $timestamps = false;

    /**
     * Get the test record for this output.
     *
     * @return BelongsTo<Test, self>
     */
    public function test(): BelongsTo
    {
        return $this->belongsTo('App\Models\Test', 'testid');
    }

    /**
     * Returns uncompressed test output.
     */
    public static function DecompressOutput($output)
    {
        if (!config('cdash.use_compression')) {
            return $output;
        }
        if (config('database.default') === 'pgsql') {
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
