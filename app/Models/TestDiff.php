<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestDiff extends Model
{
    const TEST_TYPE_NOTRUN = 0;
    const TEST_TYPE_FAILED = 1;
    const TEST_TYPE_PASSED = 2;
    const TEST_TYPE_FAILED_TIMESTATUS = 3;

    protected $table = 'testdiff';
    protected $fillable = ['buildid', 'type', 'difference_positive', 'difference_negative'];
    public $timestamps = false;
}
