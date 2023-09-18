<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $text
 * @property string $name
 * @property int $crc32
 *
 * @mixin Builder<Note>
 */
class Note extends Model
{
    protected $table = 'note';

    public $timestamps = false;

    protected $fillable = [
        'crc32',
        'name',
        'text',
    ];

    protected $casts = [
        'id' => 'integer',
        'crc32' => 'integer',
    ];

    /**
     * @return BelongsToMany<Build>
     */
    public function builds(): BelongsToMany
    {
        return $this->belongsToMany(Build::class, 'build2note', 'noteid', 'buildid')
            ->withPivot('time');
    }
}
