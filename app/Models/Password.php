<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $userid
 * @property string $password
 * @property Carbon $date
 *
 * TODO: Determine if created_at and updated_at columns are necessary
 *
 * @mixin Builder<Password>
 */
class Password extends Model
{
    protected $table = 'password';

    protected $fillable = [
        'userid',
        'password',
        'date',
    ];

    protected $casts = [
        'id' => 'integer',
        'userid' => 'integer',
        'date' => 'datetime',
    ];

    protected $hidden = [
        'password',
    ];
}
