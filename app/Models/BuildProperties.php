<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * This table has a one-to-one relationship with the build table.  The buildid is the primary key.
 * In the future, we should consider whether this table should simply be converted to a column on
 * the build table.
 *
 * @property int $buildid
 * @property string $properties
 *
 * @mixin Builder<BuildProperties>
 */
class BuildProperties extends Model
{
    protected $table = 'buildproperties';

    protected $primaryKey = 'buildid';

    public $timestamps = false;

    protected $fillable = [
        'buildid',
        'properties',
    ];
}
