<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthToken extends Model
{
    const SCOPE_FULL_ACCESS = 'full_access';
    const SCOPE_SUBMIT_ONLY = 'submit_only';

    // Eloquent requires this since we use a non-default creation date column
    // and have no updated timestamp column at all.
    const CREATED_AT = 'created';
    const UPDATED_AT = null;

    protected $table = 'authtoken';

    protected $primaryKey = 'hash';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'hash',
        'userid',
        'created',
        'expires',
        'description',
        'projectid',
        'scope'
    ];
}
