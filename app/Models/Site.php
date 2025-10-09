<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $ip
 * @property string $latitude
 * @property string $longitude
 * @property bool $outoforder
 * @property SiteInformation|null $mostRecentInformation
 *
 * @mixin Builder<Site>
 */
class Site extends Model
{
    protected $table = 'site';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'ip',
        'latitude',
        'longitude',
        'outoforder',
    ];

    protected $casts = [
        'outoforder' => 'boolean',
    ];

    /**
     * @return HasMany<SiteInformation, $this>
     */
    public function information(): HasMany
    {
        return $this->hasMany(SiteInformation::class, 'siteid');
    }

    /**
     * Get the most recent information available.  If a date is provided, get the
     * most recent information available as of that date.
     *
     * @return HasOne<SiteInformation, $this>
     */
    public function mostRecentInformation(?Carbon $date = null): HasOne
    {
        return $this->information()
            ->one()
            ->ofMany(['timestamp' => 'max'], function (Builder $query) use ($date): void {
                if ($date !== null) {
                    $query->where('timestamp', '<=', $date);
                }
            });
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function maintainers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'site2user', 'siteid', 'userid');
    }
}
