<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $userid
 * @property int $buildid
 * @property int $category
 * @property Carbon $time
 *
 * @mixin Builder<BuildEmail>
 */
class BuildEmail extends Model
{
    protected $table = 'buildemail';

    public $timestamps = false;

    protected $fillable = [
        'userid',
        'buildid',
        'category',
        'time',
    ];

    protected $casts = [
        'id' => 'integer',
        'userid' => 'integer',
        'buildid' => 'integer',
        'category' => 'integer',
        'time' => 'datetime',
    ];

    /**
     * @return BelongsTo<Build, $this>
     */
    public function build(): BelongsTo
    {
        return $this->belongsTo(Build::class, 'buildid');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userid');
    }
}
