<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $buildcommandid
 * @property string $name
 * @property string $type
 * @property string $value
 *
 * @mixin Builder<BuildMeasurement>
 */
class BuildMeasurement extends Model
{
    protected $table = 'buildmeasurements';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'type',
        'value',
    ];

    protected $casts = [
        'id' => 'integer',
        'buildcommandid' => 'integer',
    ];
}
