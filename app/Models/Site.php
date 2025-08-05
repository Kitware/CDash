<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

    public function save(array $options = []): bool
    {
        if (strlen($this->ip) === 0) {
            $this->LookupIP();
        }

        // Get the geolocation
        if (strlen($this->latitude) === 0) {
            $location = get_geolocation($this->ip);
            $this->latitude = $location['latitude'];
            $this->longitude = $location['longitude'];
        }

        try {
            return parent::save($options);
        } catch (QueryException) {
            Log::warning("Failed to save Site {$this->name}");
            return false;
        }
    }

    private function LookupIP(): void
    {
        global $PHP_ERROR_SUBMISSION_ID;
        $submission_id = $PHP_ERROR_SUBMISSION_ID;

        // In the async case, look up the IP recorded when the file was
        // originally submitted...
        if ($submission_id) {
            $this->ip = DB::select('SELECT ip FROM submission2ip WHERE submissionid = ?', [$submission_id])[0]->ip;
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $this->ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $this->ip = '';
        }
    }
}
