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
 * @property string $output
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
}
