<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $projectid
 * @property string $url
 * @property string $username
 * @property string $password
 * @property string $branch
 *
 * @mixin Builder<Repository>
 */
class Repository extends Model
{
    protected $table = 'repositories';

    public $timestamps = false;

    protected $fillable = [
        'url',
        'username',
        'password',
        'branch',
    ];

    protected $casts = [
        'projectid' => 'integer',
    ];
}
