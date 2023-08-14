<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Builder<BuildNote>
 */
class BuildNote extends Model
{
    public $timestamps = false;
    public $incrementing = false;

    protected $table = 'build2note';
    protected $fillable = ['buildid', 'noteid', 'time'];
    protected $primaryKey = null;

    /**
     * Get the note for this buildnote
     *
     * @return BelongsTo<Note, self>
     */
    public function note()
    {
        return $this->belongsTo('App\Models\Note', 'noteid');
    }
}
