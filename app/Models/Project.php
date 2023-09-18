<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $description
 * @property string $homeurl
 * @property string $cvsurl
 * @property string $bugtrackerurl
 * @property string $bugtrackerfileurl
 * @property string $bugtrackernewissueurl
 * @property string $bugtrackertype
 * @property string $documentationurl
 * @property int $imageid
 * @property int $public
 * @property int $coveragethreshold
 * @property string $testingdataurl
 * @property string $nightlytime
 * @property string $googletracker
 * @property int $emaillowcoverage
 * @property int $emailtesttimingchanged
 * @property int $emailbrokensubmission
 * @property int $emailredundantfailures
 * @property int $emailadministrator
 * @property int $showipaddresses
 * @property string $cvsviewertype
 * @property int $testtimestd
 * @property int $testtimestdthreshold
 * @property int $showtesttime
 * @property int $testtimemaxstatus
 * @property int $emailmaxitems
 * @property int $emailmaxchars
 * @property int $displaylabels
 * @property int $autoremovetimeframe
 * @property int $autoremovemaxbuilds
 * @property int $uploadquota
 * @property string $webapikey
 * @property int $tokenduration
 * @property int $showcoveragecode
 * @property int $sharelabelfilters
 * @property int $authenticatesubmissions
 * @property int $viewsubprojectslink
 *
 * @mixin Builder<Project>
 */
class Project extends Model
{
    protected $table = 'project';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'description',
        'homeurl',
        'cvsurl',
        'bugtrackerurl',
        'bugtrackerfileurl',
        'bugtrackernewissueurl',
        'bugtrackertype',
        'documentationurl',
        'imageid',
        'public',
        'coveragethreshold',
        'testingdataurl',
        'nightlytime',
        'googletracker',
        'emaillowcoverage',
        'emailtesttimingchanged',
        'emailbrokensubmission',
        'emailredundantfailures',
        'emailadministrator',
        'showipaddresses',
        'cvsviewertype',
        'testtimestd',
        'testtimestdthreshold',
        'showtesttime',
        'testtimemaxstatus',
        'emailmaxitems',
        'emailmaxchars',
        'displaylabels',
        'autoremovetimeframe',
        'autoremovemaxbuilds',
        'uploadquota',
        'webapikey',
        'tokenduration',
        'showcoveragecode',
        'sharelabelfilters',
        'authenticatesubmissions',
        'viewsubprojectslink',
    ];

    /**
     * Get the subprojects as of a specified date, or the latest subprojects if no date specified.
     *
     * @return HasMany<SubProject>
     */
    public function subprojects(?Carbon $date = null): HasMany
    {
        if ($date === null) {
            $date = Carbon::now()->setTimezone('UTC');
        }

        return $this->hasMany(SubProject::class, 'projectid', 'id')
            ->where('starttime', '<=', Carbon::now()->setTimezone('UTC'))
            ->where(function ($query) use ($date) {
                $query->where('endtime', '>', $date)
                    ->orWhere('endtime', '=', Carbon::create(1980));
            });
    }

    /**
     * @return HasMany<Measurement>
     */
    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class, 'projectid', 'id');
    }
}
