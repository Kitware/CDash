<?php

namespace App\Models;

use Database\Factories\ProjectMetricFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property int $position
 *
 * @mixin Builder<ProjectMetric>
 */
class ProjectMetric extends Model
{
    /** @use HasFactory<ProjectMetricFactory> */
    use HasFactory;

    protected $table = 'project_metrics';

    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'name',
        'position',
    ];

    protected $casts = [
        'id' => 'integer',
        'project_id' => 'integer',
        'position' => 'integer',
    ];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * @return HasMany<ProjectMetricValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(ProjectMetricValue::class, 'metric_id');
    }

    /**
     * Values submitted together share a creation time, so the ID breaks ties.
     *
     * @return HasOne<ProjectMetricValue, $this>
     */
    public function latestValue(): HasOne
    {
        return $this->hasOne(ProjectMetricValue::class, 'metric_id')->latestOfMany(['created_at', 'id']);
    }
}
