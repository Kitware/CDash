<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $buildid
 * @property int $numfiles
 * @property bool $recheck
 *
 * @mixin Builder<PendingSubmissions>
 */
class PendingSubmissions extends Model
{
    protected $table = 'pending_submissions';

    public $timestamps = false;

    // TODO: add a dedicated ID column, leaving buildid as only a foreign key column.
    // Alternatively, just move these columns to the build table.
    protected $primaryKey = 'buildid';

    protected $fillable = [
        'buildid',
        'numfiles',
        'recheck',
    ];

    protected $casts = [
        'buildid' => 'integer',
        'numfiles' => 'integer',
        'recheck' => 'boolean',
    ];
}
