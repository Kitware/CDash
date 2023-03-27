<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestDiff extends Model
{
    protected $table = 'testdiff';
    protected $fillable = ['buildid', 'type', 'difference_positive', 'difference_negative'];
    public $timestamps = false;
}
