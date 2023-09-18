<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $projectid
 * @property string $name
 * @property int $position
 *
 * @mixin Builder<Measurement>
 */
class Measurement extends Model
{
    protected $table = 'measurement';

    public $timestamps = false;

    protected $fillable = [
        'projectid',
        'name',
        'position',
    ];

    protected $casts = [
        'id' => 'integer',
        'projectid' => 'integer',
        'position' => 'integer',
    ];

    /**
     * @return BelongsTo<Project, self>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'id', 'projectid');
    }
}
