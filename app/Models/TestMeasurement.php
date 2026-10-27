<?php

namespace App\Models;

use Database\Factories\TestMeasurementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $testid
 * @property string $name
 * @property string $type
 * @property string $value
 *
 * @mixin Builder<TestMeasurement>
 */
class TestMeasurement extends Model
{
    /** @use HasFactory<TestMeasurementFactory> */
    use HasFactory;

    protected $table = 'testmeasurement';

    public $timestamps = false;

    protected $fillable = [
        'testid',
        'name',
        'type',
        'value',
    ];

    protected $casts = [
        'id' => 'integer',
        'testid' => 'integer',
    ];

    /**
     * @return BelongsTo<Test, $this>
     */
    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class, 'testid');
    }
}
