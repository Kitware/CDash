<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $buildcommandid
 * @property int $size
 * @property string $name
 *
 * @mixin Builder<BuildCommandOutput>
 */
class BuildCommandOutput extends Model
{
    protected $table = 'buildcommandoutputs';

    public $timestamps = false;

    protected $fillable = [
        'size',
        'name',
    ];

    protected $casts = [
        'id' => 'integer',
        'size' => 'integer',
    ];

    /**
     * @return BelongsTo<BuildCommand, $this>
     */
    public function command(): BelongsTo
    {
        return $this->belongsTo(BuildCommand::class, 'buildcommandid');
    }
}
