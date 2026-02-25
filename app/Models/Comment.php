<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $buildid
 * @property int $userid
 * @property string $text
 * @property Carbon $timestamp
 * @property int $status
 *
 * @mixin Builder<Comment>
 */
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    public const STATUS_NORMAL = 0;
    public const STATUS_FIX_IN_PROGRESS = 1;
    public const STATUS_FIXED = 2;

    public $timestamps = false;

    protected $table = 'comments';

    protected $fillable = [
        // TODO: Evaluate whether userid and buildid should be fillable or not
        'userid',
        'buildid',
        'text',
        'status',
        'timestamp',
    ];

    protected $casts = [
        'id' => 'integer',
        'buildid' => 'integer',
        'userid' => 'integer',
        'timestamp' => 'datetime',
        'status' => 'integer',
    ];

    /**
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'userid');
    }

    /**
     * @return HasOne<Build, $this>
     */
    public function build(): HasOne
    {
        return $this->hasOne(Build::class, 'id', 'buildid');
    }
}
