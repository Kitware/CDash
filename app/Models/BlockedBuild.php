<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $projectid
 * @property string $buildname
 * @property string $sitename
 * @property string $ipaddress
 *
 * @mixin Builder<BlockedBuild>
 */
class BlockedBuild extends Model
{
    protected $table = 'blockbuild';

    public $timestamps = false;

    protected $fillable = [
        'projectid',
        'buildname',
        'sitename',
        'ipaddress',
    ];

    protected $casts = [
        'id' => 'integer',
        'projectid' => 'integer',
    ];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'id', 'projectid');
    }
}
