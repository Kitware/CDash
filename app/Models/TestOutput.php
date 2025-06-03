<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $crc32
 * @property string $path
 * @property string $command
 * @property mixed $output # binary data
 *
 * @mixin Builder<TestOutput>
 */
class TestOutput extends Model
{
    protected $table = 'testoutput';

    public $timestamps = false;

    protected $fillable = [
        'path',
        'command',
        'output',
        'crc32',
    ];

    protected $casts = [
        'id' => 'integer',
        'crc32' => 'integer',
    ];

    /**
     * @return HasMany<Test, $this>
     */
    public function tests(): HasMany
    {
        return $this->hasMany(Test::class, 'outputid');
    }

    /**
     * Returns uncompressed test output.
     */
    public static function DecompressOutput(mixed $output): string
    {
        if (is_resource($output)) {
            $output = stream_get_contents($output);
        }

        if (!is_string($output)) {
            $output = '';
        }

        if (!config('cdash.use_compression')) {
            return $output;
        }

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
        $decoded = base64_decode($output);
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
        // encoded or not.
        // Assume postgres means encoded and mysql means not.
        if (config('database.default') === 'pgsql') {
            return $decoded;
        }
        return $output;
    }
}
