<?php

namespace App\Models;

use Database\Factories\NoteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $text
 * @property string $name
 *
 * @mixin Builder<Note>
 */
class Note extends Model
{
    /** @use HasFactory<NoteFactory> */
    use HasFactory;

    protected $table = 'note';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'text',
    ];

    protected $casts = [
        'id' => 'integer',
    ];

    /**
     * @return BelongsToMany<Build, $this>
     */
    public function builds(): BelongsToMany
    {
        return $this->belongsToMany(Build::class, 'build2note', 'noteid', 'buildid')
            ->withPivot('time');
    }
}
