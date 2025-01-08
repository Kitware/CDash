<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $filename
 * @property Carbon $finished_at
 *
 * @mixin Builder<SuccessfulJob>
 */
class SuccessfulJob extends Model
{
    protected $table = 'successful_jobs';

    public $timestamps = false;

    protected $fillable = [
        'filename',
    ];

    protected $casts = [
        'finished_at' => 'datetime',
    ];
}
