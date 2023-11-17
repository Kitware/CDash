<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property int $id
 * @property int $projectid
 * @property string $name
 *
 * @mixin Builder<Test>
 */
class Test extends Model
{
    protected $table = 'test';

    protected $fillable = [
        'name',
        'projectid',
    ];

    protected $casts = [
        'id' => 'integer',
        'projectid' => 'integer',
    ];

    public $timestamps = false;

    public const FAILED = 'failed';
    public const PASSED = 'passed';
    public const OTHER_FAULT = 'OTHER_FAULT';
    public const TIMEOUT = 'Timeout';
    public const NOTRUN = 'notrun';
    public const DISABLED = 'Disabled';

    /**
     * @return BelongsTo<Project, self>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'projectid');
    }
}
