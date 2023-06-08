<?php

namespace App\Models;

use CDash\Config;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $name
 * @property string $ip
 * @property string $latitude
 * @property string $longitude
 * @property int $outoforder
 * @property SiteInformation $mostRecentInformation
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

    /**
     * @return HasMany<SiteInformation>
     */
    public function information(): HasMany
    {
        return $this->hasMany(SiteInformation::class, 'siteid');
    }

    /**
     * Get the most recent information available
     *
     * @return HasOne<SiteInformation>
     */
    public function mostRecentInformation(): HasOne
    {
        return $this->hasOne(SiteInformation::class, 'siteid')->ofMany('timestamp', 'max');
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

        return parent::save($options);
    }

    private function LookupIP(): void
    {
        global $PHP_ERROR_SUBMISSION_ID;
        $submission_id = $PHP_ERROR_SUBMISSION_ID;

        $config = Config::getInstance();
        // In the async case, look up the IP recorded when the file was
        // originally submitted...
        if ($submission_id) {
            $this->ip = DB::select('SELECT ip FROM submission2ip WHERE submissionid = ?', [$submission_id])[0]->ip;
        } elseif ($config->get('CDASH_REMOTE_ADDR')) {
            $this->ip = $config->get('CDASH_REMOTE_ADDR');
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $this->ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $this->ip = '';
        }
    }
}
