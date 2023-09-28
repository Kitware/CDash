<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
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

    protected $fillable = [
        'outputid',
        'name',
        'type',
        'value',
    ];

    protected $casts = [
        'outputid' => 'integer',
    ];

    public $timestamps = false;
}
