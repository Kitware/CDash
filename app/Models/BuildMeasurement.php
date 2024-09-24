<?php

namespace App\Models;

use App\Enums\BuildMeasurementType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $buildid
 * @property string $name
 * @property string $source
 * @property BuildMeasurementType $type
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
        'source',
        'type',
        'value',
    ];

    protected $casts = [
        'id' => 'integer',
        'buildid' => 'integer',
        'type' => BuildMeasurementType::class,
    ];

    /**
     * @return HasOne<Build>
     */
    public function build(): HasOne
    {
        return $this->hasOne(Build::class, 'id', 'buildid');
    }
}
