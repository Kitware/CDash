<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $crc32
 * @property string $path
 * @property string $command
 * @property mixed $output  # binary data
 * @property int $testid
 *
 * @mixin Builder<TestOutput>
 */
class TestOutput extends Model
{
    protected $table = 'testoutput';

    public $timestamps = false;

    protected $fillable = [
        'testid',
        'path',
        'command',
        'output',
        'crc32',
    ];

    protected $casts = [
        'id' => 'integer',
        'crc32' => 'integer',
        'testid' => 'integer',
    ];

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
     * @return HasMany<TestMeasurement>
     */
    public function testMeasurements(): HasMany
    {
        return $this->hasMany(TestMeasurement::class, 'outputid');
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
