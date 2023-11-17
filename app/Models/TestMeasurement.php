<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $outputid
 * @property string $name
 * @property string $type
 * @property string $value
 *
 * @mixin Builder<TestMeasurement>
 */
class TestMeasurement extends Model
{
    protected $table = 'testmeasurement';

    public $timestamps = false;

    protected $fillable = [
        'outputid',
        'name',
        'type',
        'value',
    ];

    protected $casts = [
        'id' => 'integer',
        'outputid' => 'integer',
    ];
}
