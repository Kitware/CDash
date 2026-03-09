<?php

namespace App\Models;

use Database\Factories\RepositoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $projectid
 * @property string $url
 * @property string $username
 * @property string $password
 * @property string $branch
 *
 * @mixin Builder<Repository>
 */
class Repository extends Model
{
    /** @use HasFactory<RepositoryFactory> */
    use HasFactory;

    protected $table = 'repositories';

    public $timestamps = false;

    protected $fillable = [
        'url',
        'username',
        'password',
        'branch',
    ];

    protected $casts = [
        'projectid' => 'integer',
    ];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'projectid');
    }
}
