<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $metric_id
 * @property ?int $user_id
 * @property string $value
 * @property Carbon $created_at
 *
 * @mixin Builder<ProjectMetricValue>
 */
class ProjectMetricValue extends Model
{
    // Values are never edited, so there is no updated timestamp column.
    public const UPDATED_AT = null;

    protected $table = 'project_metric_values';

    protected $fillable = [
        'metric_id',
        'user_id',
        'value',
        'created_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'metric_id' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<ProjectMetric, $this>
     */
    public function metric(): BelongsTo
    {
        return $this->belongsTo(ProjectMetric::class, 'metric_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
