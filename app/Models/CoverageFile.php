<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property string $fullpath
 * @property string $file
 * @property int $crc32
 *
 * @mixin Builder<CoverageFile>
 */
class CoverageFile extends Model
{
    protected $table = 'coveragefile';

    public $timestamps = false;

    protected $fillable = [
        'fullpath',
        'file',
        'crc32',
    ];

    protected $casts = [
        'crc32' => 'integer',
    ];

    /**
     * @return HasManyThrough<Build>
     */
    public function builds(): HasManyThrough
    {
        return $this->hasManyThrough(Build::class, Coverage::class, 'fileid', 'id', 'id', 'buildid');
    }
}
