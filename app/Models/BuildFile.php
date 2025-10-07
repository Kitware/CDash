<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $type
 * @property string $filename
 * @property string $md5
 * @property int $buildid
 *
 * @mixin Builder<BuildFile>
 */
class BuildFile extends Model
{
    protected $table = 'buildfile';

    public $timestamps = false;

    protected $fillable = [
        'type',
        'filename',
        'md5',
        'buildid',
    ];

    /**
     * @return BelongsTo<Build, $this>
     */
    public function build(): BelongsTo
    {
        return $this->belongsTo(Build::class, 'buildid');
    }
}
