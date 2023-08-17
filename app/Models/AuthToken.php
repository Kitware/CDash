<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthToken extends Model
{
    public const SCOPE_FULL_ACCESS = 'full_access';
    public const SCOPE_SUBMIT_ONLY = 'submit_only';

    // Eloquent requires this since we use a non-default creation date column
    // and have no updated timestamp column at all.
    public const CREATED_AT = 'created';
    public const UPDATED_AT = null;

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
        'scope',
    ];
}
