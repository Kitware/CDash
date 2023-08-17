<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Test extends Model
{
    protected $table = 'test';
    protected $fillable = ['name', 'projectid'];

    public $timestamps = false;

    public const FAILED = 'failed';
    public const PASSED = 'passed';
    public const OTHER_FAULT = 'OTHER_FAULT';
    public const TIMEOUT = 'Timeout';
    public const NOTRUN = 'notrun';
    public const DISABLED = 'Disabled';
}
