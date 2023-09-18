<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

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
 * @property int $showcoveragecode
 * @property int $sharelabelfilters
 * @property int $authenticatesubmissions
 * @property int $viewsubprojectslink
 *
 * @method static Builder forUser(?User $user = null)
 *
 * @mixin Builder<Project>
 */
class Project extends Model
{
    protected $table = 'project';

    public $timestamps = false;

    protected $fillable = [
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
        'showcoveragecode',
        'sharelabelfilters',
        'authenticatesubmissions',
        'viewsubprojectslink',
    ];

    protected $casts = [
        'id' => 'integer',
        'imageid' => 'integer',
        'public' => 'integer',
        'coveragethreshold' => 'integer',
        // TODO: figure out boolean vs int issues with the rest of the variables...
    ];

    public const PROJECT_ADMIN = 2;
    public const SITE_MAINTAINER = 1;
    public const PROJECT_USER = 0;

    public const ACCESS_PRIVATE = 0;
    public const ACCESS_PUBLIC = 1;
    public const ACCESS_PROTECTED = 2;

    /**
     * Get the users who have been added to this project.
     *
     * Note: This is *not* all of the users who have access to this project!
     *
     * @return BelongsToMany<User>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user2project', 'projectid', 'userid');
    }

    /**
     * Get the users who have the administrator role for this project
     *
     * @return BelongsToMany<User>
     */
    public function administrators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user2project', 'projectid', 'userid')
            ->wherePivot('role', self::PROJECT_ADMIN);
    }

    /**
     * Get the users who maintain a site for this project
     *
     * @return BelongsToMany<User>
     */
    public function siteMaintainers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user2project', 'projectid', 'userid')
            ->wherePivot('role', self::SITE_MAINTAINER);
    }

    /**
     * Get the projects available to the specified user, or the current user if no user specified.
     * Available as a query builder function: Project::forUser(?User)->...
     *
     * @param Builder<self> $query
     */
    public function scopeForUser(Builder $query, ?User $user = null): void
    {
        if ($user === null) {
            $user = Auth::user();
        }

        if ($user === null) {
            $query->where('public', self::ACCESS_PUBLIC);
        } elseif (!$user->IsAdmin()) {
            $query->whereHas('users', function ($query2) use ($user) {
                $query2->where('user.id', $user->id);
                $query2->orWhere('public', self::ACCESS_PUBLIC);
                $query2->orWhere('public', self::ACCESS_PROTECTED);
            });
        }
        // Else, this is an admin user, so we shouldn't apply any filters...
    }

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
