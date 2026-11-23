<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubmissionStatus;
use Database\Factories\SubmissionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A submitted file and the progress of its processing.
 *
 * @property string $id
 * @property int $projectid
 * @property int|null $buildid
 * @property string $type
 * @property string|null $filename
 * @property string $path
 * @property SubmissionStatus $status
 * @property int $attempts
 * @property string|null $error
 * @property Carbon $received_at
 * @property Carbon|null $next_attempt_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 *
 * @mixin Builder<Submission>
 */
class Submission extends Model
{
    /** @use HasFactory<SubmissionFactory> */
    use HasFactory;
    use HasUuids;

    protected $table = 'submissions';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'projectid',
        'buildid',
        'type',
        'filename',
        'path',
        'status',
        'attempts',
        'error',
        'received_at',
        'next_attempt_at',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'projectid' => 'integer',
        'buildid' => 'integer',
        'status' => SubmissionStatus::class,
        'attempts' => 'integer',
        'received_at' => 'datetime',
        'next_attempt_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'projectid');
    }

    /**
     * @return BelongsTo<Build, $this>
     */
    public function build(): BelongsTo
    {
        return $this->belongsTo(Build::class, 'buildid');
    }
}
