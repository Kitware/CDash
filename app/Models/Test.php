<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Test extends Model
{
    protected $table = 'test';
    protected $fillable = ['name', 'projectid'];

    public $timestamps = false;

    const FAILED = 'failed';
    const PASSED = 'passed';
    const OTHER_FAULT = 'OTHER_FAULT';
    const TIMEOUT = 'Timeout';
    const NOTRUN = 'notrun';
    const DISABLED = 'Disabled';
}
