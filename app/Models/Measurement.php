<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
    protected $fillable = [
        'projectid',
        'name',
        'position',
    ];

    public $timestamps = false;
}
