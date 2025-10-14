<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $updateid
 * @property string $filename
 * @property Carbon $checkindate
 * @property string $author
 * @property string $email
 * @property string $committer
 * @property string $committeremail
 * @property string $log
 * @property string $revision
 * @property string $priorrevision
 * @property string $status // MODIFIED | CONFLICTING | UPDATED
 *
 * @mixin Builder<BuildUpdateFile>
 */
class BuildUpdateFile extends Model
{
    protected $table = 'updatefile';

    public $timestamps = false;

    protected $fillable = [
        'updateid',
        'filename',
        'checkindate',
        'author',
        'email',
        'committer',
        'committeremail',
        'log',
        'revision',
        'priorrevision',
        'status',
    ];

    protected $casts = [
        'id' => 'integer',
        'updateid' => 'integer',
        'checkindate' => 'datetime',
    ];
}
