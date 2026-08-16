<?php

namespace App\Models;

use App\Enums\TargetType;
use Database\Factories\TargetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $buildid
 * @property string $name
 * @property TargetType $type
 *
 * @mixin Builder<Target>
 */
class Target extends Model
{
    /** @use HasFactory<TargetFactory> */
    use HasFactory;

    protected $table = 'targets';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'type',
    ];

    protected $casts = [
        'id' => 'integer',
        'buildid' => 'integer',
        'type' => TargetType::class,
    ];

    /**
     * @return BelongsTo<Build, $this>
     */
    public function build(): BelongsTo
    {
        return $this->belongsTo(Build::class, 'buildid');
    }

    /**
     * @return BelongsToMany<Label, $this>
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'label2target', 'targetid', 'labelid');
    }

    /**
     * @return HasMany<BuildCommand, $this>
     */
    public function commands(): HasMany
    {
        return $this->hasMany(BuildCommand::class, 'targetid');
    }
}
