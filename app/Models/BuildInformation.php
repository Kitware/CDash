<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TODO: This relation has a one-to-one relationship with the build relation,
 *       and should probably be converted to additional columns on that table.
 *
 * @property int $buildid
 * @property string $osname
 * @property string $osplatform
 * @property string $osrelease
 * @property string $osversion
 * @property string $compilername
 * @property string $compilerversion
 *
 * @mixin Builder<BuildInformation>
 */
class BuildInformation extends Model
{
    protected $table = 'buildinformation';

    protected $primaryKey = 'buildid';

    public $timestamps = false;

    protected $fillable = [
        'buildid',
        'osname',
        'osplatform',
        'osrelease',
        'osversion',
        'compilername',
        'compilerversion',
    ];

    protected $casts = [
        'buildid' => 'integer',
    ];

    /**
     * @return BelongsTo<Build, self>
     */
    public function build(): BelongsTo
    {
        return $this->belongsTo(Build::class, 'id');
    }

    /**
     * TODO: Re-evaluate whether this function is necessary
     */
    public function SetValue(string $tag, string $value): void
    {
        switch ($tag) {
            case 'OSNAME':
                $this->osname = $value;
                break;
            case 'OSRELEASE':
                $this->osrelease = $value;
                break;
            case 'OSVERSION':
                $this->osversion = $value;
                break;
            case 'OSPLATFORM':
                $this->osplatform = $value;
                break;
            case 'COMPILERNAME':
                $this->compilername = $value;
                break;
            case 'COMPILERVERSION':
                $this->compilerversion = $value;
                break;
        }
    }
}
