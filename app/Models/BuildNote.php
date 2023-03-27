<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuildNote extends Model
{
    public $timestamps = false;
    public $incrementing = false;

    protected $table = 'build2note';
    protected $fillable = ['buildid', 'noteid', 'time'];
    protected $primaryKey = null;

    /**
     * Get the note for this buildnote
     */
    public function note()
    {
        return $this->belongsTo('App\Models\Note', 'noteid');
    }
}
